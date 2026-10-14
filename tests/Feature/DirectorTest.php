<?php

namespace Tests\Feature;

use App\Models\CorrectionRequest;
use App\Models\DeductionWaiver;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MonthlyLock;
use App\Models\User;
use App\Models\WfhRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DirectorTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $roles = [];

    private User $admin;

    private Employee $director;

    private Employee $manager;

    private Employee $lineManager;

    private Employee $employee;

    private int $casualLeaveId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        MonthlyLock::forgetCache();
        Notification::fake();

        foreach (['administrator' => 'Administrator', 'director' => 'Director', 'manager' => 'Manager', 'line_manager' => 'Line Manager', 'employee' => 'Employee'] as $name => $label) {
            $this->roles[$name] = DB::table('roles')->insertGetId(['name' => $name, 'display_name' => $label]);
        }

        DB::table('attendance_settings')->insert([
            'standard_shift_start' => '09:00:00',
            'standard_shift_end' => '18:00:00',
            'late_arrival_grace_minutes' => 0,
            'early_departure_grace_minutes' => 0,
            'working_days' => 'Mon,Tue,Wed,Thu,Fri',
            'cycle_start_day' => 1,
            'cycle_end_day' => 31,
            'penalty_policy_enabled' => 1,
            'late_arrivals_per_casual_leave' => 3,
            'early_outs_per_casual_leave' => 3,
            'half_days_per_casual_leave' => 2,
        ]);
        DB::table('departments')->insert([['name' => 'Management'], ['name' => 'IT']]);
        $this->casualLeaveId = DB::table('leave_types')->insertGetId(['name' => 'Casual Leave', 'days_per_year' => 12]);

        $this->admin = User::factory()->create(['name' => 'Admin User', 'role_id' => $this->roles['administrator'], 'status' => 'Active']);
        $this->director = $this->person('Dana', 'Director', 'director', null, 1);
        $this->manager = $this->person('Mani', 'Manager', 'manager', $this->director, 1, '201');
        $this->lineManager = $this->person('Lina', 'Lead', 'line_manager', $this->manager, 2, '202');
        $this->employee = $this->person('Emma', 'Worker', 'employee', $this->lineManager, 2, '203');
    }

    protected function tearDown(): void
    {
        MonthlyLock::forgetCache();

        parent::tearDown();
    }

    public function test_a_managers_requests_go_to_their_director_and_nobody_elses_do(): void
    {
        $this->assertTrue(LeaveRequest::reviewingManagerFor($this->manager)->is($this->director));
        $this->assertTrue(LeaveRequest::directorFor($this->manager)->is($this->director));

        // A Line Manager or employee reporting to a Director still goes to Admin (Directors only handle Managers)
        $this->lineManager->update(['reports_to_id' => $this->director->id]);
        $this->assertNull(LeaveRequest::reviewingManagerFor($this->lineManager->fresh()));

        // A Manager reporting to nobody: Admin decides, as before
        $this->manager->update(['reports_to_id' => null]);
        $this->assertNull(LeaveRequest::reviewingManagerFor($this->manager->fresh()));
    }

    public function test_manager_request_shows_awaiting_director(): void
    {
        $leave = $this->leave();

        $this->assertSame('Awaiting Director', $leave->stageLabel());
    }

    public function test_director_approval_of_a_leave_is_final_and_admin_is_informed(): void
    {
        $leave = $this->leave();

        $this->actingAs($this->director->user)
            ->post(route('director.approvals.leave.approve', $leave->id), ['note' => 'Enjoy'])
            ->assertSessionHas('success');

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame(LeaveRequest::MANAGER_APPROVED, $leave->manager_status);
        $this->assertSame('Enjoy', $leave->manager_note);
        $this->assertSame('Dana Director', $leave->reviewed_by);

        $this->assertDatabaseHas('portal_notifications', ['user_id' => $this->manager->user_id, 'title' => 'Leave request approved']);
        $this->assertDatabaseHas('portal_notifications', ['user_id' => $this->admin->id]);
        $this->assertStringContainsString('No action is needed from Admin', DB::table('portal_notifications')->where('user_id', $this->admin->id)->value('message'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Approved by Director', 'actor_name' => 'Dana Director']);

        // Admin cannot change the Director's decision
        $this->actingAs($this->admin)
            ->put(route('admin.leave-request.update', $leave->id), ['status' => 'rejected'])
            ->assertSessionHas('error');
        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_director_rejection_needs_a_reason(): void
    {
        $leave = $this->leave();

        $this->actingAs($this->director->user)
            ->post(route('director.approvals.leave.reject', $leave->id), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->actingAs($this->director->user)
            ->post(route('director.approvals.leave.reject', $leave->id), ['note' => 'Busy week'])
            ->assertSessionHas('success');

        $this->assertSame('rejected', $leave->fresh()->status);
        $this->assertSame(LeaveRequest::MANAGER_REJECTED, $leave->fresh()->manager_status);
    }

    public function test_a_request_cannot_be_decided_twice(): void
    {
        $leave = $this->leave();

        $this->actingAs($this->director->user)->post(route('director.approvals.leave.approve', $leave->id));
        $this->actingAs($this->director->user)
            ->post(route('director.approvals.leave.reject', $leave->id), ['note' => 'Changed my mind'])
            ->assertSessionHas('error');

        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_director_approved_correction_changes_the_attendance(): void
    {
        DB::table('device_attendance')->insert(['uid' => '201', 'name' => 'Mani Manager', 'date' => '2026-10-05', 'check_in' => '09:00:00', 'check_out' => null, 'status' => 'On Time']);
        $correction = CorrectionRequest::create([
            'employee_id' => $this->manager->id, 'date' => '2026-10-05', 'change_type' => 'forgot-check-out',
            'requested_check_out' => '18:00', 'requested_correction' => 'Out 18:00', 'reason' => 'Forgot', 'status' => 'pending', 'lm_status' => 'pending',
        ]);

        $this->actingAs($this->director->user)
            ->post(route('director.approvals.correction.approve', $correction->id))
            ->assertSessionHas('success');

        $correction->refresh();
        $this->assertSame('approved', $correction->status);
        $this->assertSame('approved', $correction->lm_status);
        $this->assertSame($this->director->id, (int) $correction->lm_reviewed_by);
        $this->assertNotNull($correction->applied_at);
        $this->assertSame('18:00:00', DB::table('device_attendance')->where('uid', '201')->value('check_out'));
        $this->assertTrue($correction->isDirectorDecision());

        // Admin cannot approve or change it
        $this->actingAs($this->admin)
            ->post(route('admin.approvals.correction.reject', $correction->id), ['reason' => 'No'])
            ->assertSessionHas('error');
        $this->assertSame('approved', $correction->fresh()->status);
    }

    public function test_director_approved_waiver_is_applied(): void
    {
        DB::table('device_attendance')->insert(['uid' => '201', 'name' => 'Mani Manager', 'date' => '2026-10-05', 'check_in' => '09:30:00', 'check_out' => '18:00:00', 'status' => 'Late']);
        $waiver = DeductionWaiver::create(['employee_id' => $this->manager->id, 'date' => '2026-10-05', 'type' => 'late', 'reason' => 'Traffic', 'status' => 'pending', 'lm_status' => 'pending']);

        $this->actingAs($this->director->user)
            ->post(route('director.approvals.waiver.approve', $waiver->id))
            ->assertSessionHas('success');

        $this->assertSame('approved', $waiver->fresh()->status);
        $this->assertSame('late', DB::table('device_attendance')->where('uid', '201')->value('waived_types'));

        $this->actingAs($this->admin)
            ->post(route('admin.approvals.waiver.reject', $waiver->id), ['reason' => 'No'])
            ->assertSessionHas('error');
        $this->assertSame('approved', $waiver->fresh()->status);
    }

    public function test_director_decides_work_from_home(): void
    {
        $wfh = WfhRequest::create([
            'employee_id' => $this->manager->id, 'from_date' => '2026-10-20', 'to_date' => '2026-10-20', 'days' => 1,
            'reason' => 'Plumber', 'status' => 'pending', 'manager_id' => $this->director->id, 'manager_status' => LeaveRequest::MANAGER_PENDING,
        ]);

        $this->actingAs($this->director->user)
            ->post(route('director.approvals.wfh.approve', $wfh->id))
            ->assertSessionHas('success');

        $this->assertSame('approved', $wfh->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('admin.wfh.reject', $wfh->id), ['reason' => 'No'])
            ->assertSessionHas('error');
        $this->assertSame('approved', $wfh->fresh()->status);
    }

    // A Line Manager's / Manager's own request: their reviewer and Admin see it at the same time, whoever decides first

    public function test_admin_sees_and_may_decide_a_managers_request_before_the_director(): void
    {
        $leave = $this->leave();

        $this->actingAs($this->admin)->get(route('admin.approvals'))->assertOk()->assertSee('Mani Manager')->assertSee('you may decide now');
        $this->actingAs($this->admin)->get(route('admin.leaves'))->assertOk()->assertSee(route('admin.leave-request.approve', $leave->id));

        $this->actingAs($this->admin)
            ->post(route('admin.leave-request.approve', $leave->id), ['note' => 'Fine'])
            ->assertSessionHas('success');

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame(LeaveRequest::MANAGER_NOT_REQUIRED, $leave->manager_status);
        $this->assertSame('Admin User', $leave->reviewed_by);
        $this->assertDatabaseHas('portal_notifications', ['user_id' => $this->director->user_id, 'title' => 'Leave approved by Admin']);
        $this->assertDatabaseHas('portal_notifications', ['user_id' => $this->manager->user_id, 'title' => 'Leave request approved']);

        // It left the Director's queue (only History shows it) and the Director can no longer decide it
        $this->actingAs($this->director->user)->get(route('director.approvals'))->assertOk()
            ->assertDontSee(route('director.approvals.leave.approve', $leave->id), false);
        $this->actingAs($this->director->user)
            ->post(route('director.approvals.leave.reject', $leave->id), ['note' => 'No'])
            ->assertSessionHas('error');
        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_managers_decision_on_a_line_managers_request_is_final(): void
    {
        $leave = $this->leave($this->lineManager);
        $this->assertTrue($leave->manager->is($this->manager));

        $this->actingAs($this->manager->user)
            ->post(route('manager.approvals.leave.approve', $leave->id), ['note' => 'OK'])
            ->assertSessionHas('success', 'Leave request approved.');

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame(LeaveRequest::MANAGER_APPROVED, $leave->manager_status);
        $this->assertSame('Mani Manager', $leave->reviewed_by);
        $this->assertDatabaseHas('portal_notifications', ['user_id' => $this->lineManager->user_id, 'title' => 'Leave request approved']);
        $this->assertStringContainsString('No action is needed from Admin', DB::table('portal_notifications')->where('user_id', $this->admin->id)->value('message'));

        // Admin can no longer decide it
        $this->actingAs($this->admin)
            ->post(route('admin.leave-request.reject', $leave->id), ['reason' => 'No'])
            ->assertSessionHas('error', 'A decision has already been made on this request.');
        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_admin_may_decide_a_line_managers_request_before_the_manager(): void
    {
        $leave = $this->leave($this->lineManager);

        $this->actingAs($this->admin)
            ->post(route('admin.leave-request.reject', $leave->id), ['reason' => 'Busy week'])
            ->assertSessionHas('success');

        $this->assertSame('rejected', $leave->fresh()->status);
        $this->assertSame(LeaveRequest::MANAGER_NOT_REQUIRED, $leave->fresh()->manager_status);
        $this->assertDatabaseHas('portal_notifications', ['user_id' => $this->manager->user_id, 'title' => 'Leave rejected by Admin']);

        // The Manager's approve now fails: no longer awaiting them
        $this->actingAs($this->manager->user)
            ->post(route('manager.approvals.leave.approve', $leave->id))
            ->assertStatus(422);
        $this->assertSame('rejected', $leave->fresh()->status);
    }

    public function test_an_employees_request_still_waits_for_the_line_manager(): void
    {
        $leave = $this->leave($this->employee);

        $this->actingAs($this->admin)->get(route('admin.approvals'))->assertOk()->assertDontSee('Emma Worker');
        $this->actingAs($this->admin)
            ->post(route('admin.leave-request.approve', $leave->id))
            ->assertSessionHas('error', 'This request is still awaiting Line Manager review.');
        $this->assertSame('pending', $leave->fresh()->status);

        // The Line Manager's approval still only forwards it to Admin
        $this->actingAs($this->lineManager->user)
            ->post(route('line_manager.approvals.leave.approve', $leave->id))
            ->assertSessionHas('success', 'Leave approved and forwarded to Admin for final approval.');
        $this->assertSame('pending', $leave->fresh()->status);
        $this->assertSame(LeaveRequest::MANAGER_APPROVED, $leave->fresh()->manager_status);
    }

    public function test_manager_finally_decides_a_line_managers_correction_and_waiver(): void
    {
        DB::table('device_attendance')->insert(['uid' => '202', 'name' => 'Lina Lead', 'date' => '2026-10-05', 'check_in' => '09:30:00', 'check_out' => null, 'status' => 'Late']);
        $correction = CorrectionRequest::create([
            'employee_id' => $this->lineManager->id, 'date' => '2026-10-05', 'change_type' => 'forgot-check-out',
            'requested_check_out' => '18:00', 'requested_correction' => 'Out 18:00', 'reason' => 'Forgot', 'status' => 'pending', 'lm_status' => 'pending',
        ]);
        $waiver = DeductionWaiver::create(['employee_id' => $this->lineManager->id, 'date' => '2026-10-05', 'type' => 'late', 'reason' => 'Traffic', 'status' => 'pending', 'lm_status' => 'pending']);

        $this->actingAs($this->manager->user)
            ->post(route('manager.approvals.correction.approve', $correction->id))
            ->assertSessionHas('success', 'Correction request approved.');
        $this->assertSame('approved', $correction->fresh()->status);
        $this->assertSame('18:00:00', DB::table('device_attendance')->where('uid', '202')->value('check_out'));

        $this->actingAs($this->manager->user)
            ->post(route('manager.approvals.waiver.approve', $waiver->id), ['note' => 'Genuine'])
            ->assertSessionHas('success', 'Waiver request approved.');
        $this->assertSame('approved', $waiver->fresh()->status);
        $this->assertSame('late', DB::table('device_attendance')->where('uid', '202')->value('waived_types'));
    }

    public function test_admin_may_decide_a_managers_waiver_and_wfh_before_the_director(): void
    {
        $waiver = DeductionWaiver::create(['employee_id' => $this->manager->id, 'date' => '2026-10-05', 'type' => 'late', 'reason' => 'Traffic', 'status' => 'pending', 'lm_status' => 'pending']);
        $wfh = WfhRequest::create([
            'employee_id' => $this->manager->id, 'from_date' => '2026-10-20', 'to_date' => '2026-10-20', 'days' => 1,
            'reason' => 'Plumber', 'status' => 'pending', 'manager_id' => $this->director->id, 'manager_status' => LeaveRequest::MANAGER_PENDING,
        ]);

        $this->actingAs($this->admin)->get(route('admin.approvals'))->assertOk()->assertSee('Mani Manager');

        $this->actingAs($this->admin)->post(route('admin.approvals.waiver.approve', $waiver->id))->assertSessionHas('success');
        $this->assertSame('approved', $waiver->fresh()->status);
        $this->assertSame('not_required', $waiver->fresh()->lm_status);
        $this->assertDatabaseHas('portal_notifications', ['user_id' => $this->director->user_id, 'title' => 'Waiver approved by Admin']);

        $this->actingAs($this->admin)->post(route('admin.wfh.approve', $wfh->id))->assertSessionHas('success');
        $this->assertSame('approved', $wfh->fresh()->status);
        $this->assertSame(LeaveRequest::MANAGER_NOT_REQUIRED, $wfh->fresh()->manager_status);

        $this->actingAs($this->director->user)->post(route('director.approvals.wfh.reject', $wfh->id), ['note' => 'No'])->assertSessionHas('error');
        $this->assertSame('approved', $wfh->fresh()->status);
    }

    public function test_director_cannot_decide_requests_of_people_who_are_not_their_managers(): void
    {
        $otherDirector = $this->person('Omar', 'Other', 'director', null, 1);
        $leave = $this->leave();
        $employeeLeave = $this->leave($this->employee);

        $this->actingAs($otherDirector->user)
            ->post(route('director.approvals.leave.approve', $leave->id))
            ->assertForbidden();

        $this->actingAs($this->director->user)
            ->post(route('director.approvals.leave.approve', $employeeLeave->id))
            ->assertForbidden();

        $this->assertSame('pending', $leave->fresh()->status);
        $this->assertSame('pending', $employeeLeave->fresh()->status);
    }

    public function test_locked_month_cannot_be_decided(): void
    {
        DB::table('monthly_locks')->insert(['month_year' => '2026-10', 'is_locked' => true]);
        $leave = $this->leave();

        $this->actingAs($this->director->user)
            ->post(route('director.approvals.leave.approve', $leave->id))
            ->assertSessionHas('error');

        $this->assertSame('pending', $leave->fresh()->status);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function directorPages(): array
    {
        return [
            'dashboard' => ['director.dashboard'],
            'approvals' => ['director.approvals'],
            'company attendance' => ['director.team.attendance'],
            'patterns' => ['director.attendance.patterns'],
            'breakdown' => ['director.attendance.breakdown'],
            'employees' => ['director.employees'],
            'reports' => ['director.reports'],
            'profile' => ['director.profile'],
        ];
    }

    #[DataProvider('directorPages')]
    public function test_director_pages_open_and_others_cannot_see_them(string $route): void
    {
        DB::table('device_attendance')->insert(['uid' => '203', 'name' => 'Emma Worker', 'date' => now()->toDateString(), 'check_in' => '09:30:00', 'check_out' => null, 'status' => 'Late']);
        $this->leave();

        $this->actingAs($this->director->user)->get(route($route))->assertOk();
        $this->actingAs($this->manager->user)->get(route($route))->assertForbidden();
    }

    public function test_dashboard_and_approvals_show_the_pending_manager_request_with_a_badge(): void
    {
        $this->leave();

        $this->actingAs($this->director->user)
            ->get(route('director.dashboard'))
            ->assertOk()
            ->assertSee('Mani Manager')
            ->assertSee('Emma Worker');

        $this->actingAs($this->director->user)
            ->get(route('director.approvals'))
            ->assertOk()
            ->assertSee('Mani Manager')
            ->assertSee('Your decision is final', false);
    }

    public function test_automatic_penalty_leaves_are_not_in_the_approvals_history(): void
    {
        $decided = $this->leave();
        $this->actingAs($this->director->user)->post(route('director.approvals.leave.approve', $decided->id));

        LeaveRequest::create([
            'employee_id' => $this->manager->id, 'leave_type_id' => $this->casualLeaveId,
            'from_date' => '2026-10-07', 'to_date' => '2026-10-07', 'days' => 0.5,
            'reason' => 'Penalty: 3 late arrivals (01 Oct, 02 Oct, 05 Oct)', 'status' => 'approved',
            'reviewed_by' => 'System', 'manager_status' => LeaveRequest::MANAGER_NOT_REQUIRED,
            'is_penalty' => true, 'penalty_key' => '2026-10-01:late:1',
        ]);

        $this->actingAs($this->director->user)
            ->get(route('director.approvals'))
            ->assertOk()
            ->assertSee('Family event')
            ->assertDontSee('Penalty: 3 late arrivals');
    }

    public function test_company_attendance_can_be_filtered_by_department(): void
    {
        $this->actingAs($this->director->user)
            ->get(route('director.team.attendance', ['department' => 2]))
            ->assertOk()
            ->assertSee('Emma Worker')
            ->assertDontSee('Mani Manager');
    }

    public function test_report_exports_a_csv(): void
    {
        $response = $this->actingAs($this->director->user)->get(route('director.reports.export'));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('"Emp ID",Employee,Department,"Working Days"', $csv);
        $this->assertStringContainsString('Emma Worker', $csv);
        $this->assertStringNotContainsString('Dana Director', $csv);
    }

    private function leave(?Employee $applicant = null): LeaveRequest
    {
        $applicant ??= $this->manager;
        $reviewer = LeaveRequest::reviewingManagerFor($applicant);

        return LeaveRequest::create([
            'employee_id' => $applicant->id,
            'leave_type_id' => $this->casualLeaveId,
            'from_date' => '2026-10-21',
            'to_date' => '2026-10-21',
            'days' => 1,
            'reason' => 'Family event',
            'status' => 'pending',
            'manager_id' => $reviewer?->id,
            'manager_status' => $reviewer ? LeaveRequest::MANAGER_PENDING : LeaveRequest::MANAGER_NOT_REQUIRED,
        ])->load('employee');
    }

    private function person(string $first, string $last, string $role, ?Employee $reportsTo, int $departmentId, ?string $uid = null): Employee
    {
        $user = User::factory()->create(['name' => "{$first} {$last}", 'role_id' => $this->roles[$role], 'status' => 'Active']);

        return Employee::create([
            'employee_id' => 'GP-'.$user->id,
            'zkteco_uid' => $uid,
            'user_id' => $user->id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $user->email,
            'department_id' => $departmentId,
            'role_id' => $this->roles[$role],
            'reports_to_id' => $reportsTo?->id,
            'job_title' => ucfirst($role),
            'joining_date' => '2026-01-01',
            'employment_status' => 'active',
        ])->load('user');
    }

    /**
     * The portal tables have no migrations in this repository, so the columns used here are created for the test.
     */
    private function createSchema(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('credentials_sent_at')->nullable();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('saturday_start_time')->nullable();
            $table->time('saturday_end_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('grace_policies', function (Blueprint $table) {
            $table->id();
            $table->integer('late_grace_minutes')->nullable();
            $table->integer('early_grace_minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('employee_id');
            $table->string('zkteco_uid')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('grace_policy_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('cnic')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('reports_to_id')->nullable();
            $table->string('job_title')->nullable();
            $table->date('joining_date')->nullable();
            $table->string('employment_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->time('standard_shift_start')->nullable();
            $table->time('standard_shift_end')->nullable();
            $table->integer('late_arrival_grace_minutes')->nullable();
            $table->integer('early_departure_grace_minutes')->nullable();
            $table->string('working_days')->nullable();
            $table->integer('cycle_start_day')->nullable();
            $table->integer('cycle_end_day')->nullable();
            $table->boolean('penalty_policy_enabled')->default(false);
            $table->integer('late_arrivals_per_casual_leave')->nullable();
            $table->integer('early_outs_per_casual_leave')->nullable();
            $table->integer('half_days_per_casual_leave')->nullable();
            $table->boolean('email_notifications_enabled')->default(false);
        });

        Schema::create('device_attendance', function (Blueprint $table) {
            $table->id();
            $table->string('uid');
            $table->string('name')->nullable();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('status')->nullable();
            $table->integer('late_minutes')->default(0);
            $table->integer('early_out_minutes')->default(0);
            $table->string('source')->nullable();
            $table->string('waived_types')->nullable();
            $table->string('adjustment')->nullable();
            $table->string('adjustment_note')->nullable();
            $table->string('adjusted_by')->nullable();
            $table->timestamp('adjusted_at')->nullable();
            $table->time('original_check_in')->nullable();
            $table->time('original_check_out')->nullable();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->date('from_date');
            $table->date('to_date')->nullable();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('days_per_year', 5, 1);
            $table->string('color')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 5, 1);
            $table->boolean('is_half_day')->default(false);
            $table->string('half_day_session')->nullable();
            $table->text('reason')->nullable();
            $table->string('status');
            $table->text('admin_note')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_status')->nullable();
            $table->text('manager_note')->nullable();
            $table->timestamp('manager_reviewed_at')->nullable();
            $table->boolean('is_penalty')->default(false);
            $table->string('penalty_key')->nullable();
            $table->string('document_path')->nullable();
            $table->timestamps();
        });

        Schema::create('correction_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('change_type');
            $table->string('current_record')->nullable();
            $table->string('requested_correction')->nullable();
            $table->string('requested_check_in')->nullable();
            $table->string('requested_check_out')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('reason')->nullable();
            $table->string('status');
            $table->string('lm_status')->nullable();
            $table->text('lm_note')->nullable();
            $table->unsignedBigInteger('lm_reviewed_by')->nullable();
            $table->timestamp('lm_reviewed_at')->nullable();
            $table->text('manager_note')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deduction_waivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('type');
            $table->text('reason')->nullable();
            $table->string('status');
            $table->string('lm_status')->nullable();
            $table->text('lm_note')->nullable();
            $table->unsignedBigInteger('lm_reviewed_by')->nullable();
            $table->timestamp('lm_reviewed_at')->nullable();
            $table->text('manager_note')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wfh_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('from_date');
            $table->date('to_date');
            $table->integer('days');
            $table->text('reason')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_status')->nullable();
            $table->text('manager_note')->nullable();
            $table->timestamp('manager_reviewed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('monthly_locks', function (Blueprint $table) {
            $table->id();
            $table->string('month_year');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('portal_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->string('link')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'dedupe_key']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('category');
            $table->string('action');
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
