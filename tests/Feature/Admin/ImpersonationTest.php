<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\ImpersonationController;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $roles = [];

    private User $admin;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();

        foreach (['administrator', 'employee', 'line_manager'] as $name) {
            $this->roles[$name] = DB::table('roles')->insertGetId(['name' => $name]);
        }

        $this->admin = User::factory()->create(['name' => 'Admin User', 'role_id' => $this->roles['administrator'], 'status' => 'Active']);
        $this->employee = $this->person('Junaid', 'Jamil', 'employee');
    }

    public function test_admin_logs_in_as_an_employee_and_sees_the_banner(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.employee.login-as', $this->employee->id))
            ->assertRedirect(route('employee.dashboard'));

        $this->assertAuthenticatedAs($this->employee->user);
        $this->assertSame($this->admin->id, session(ImpersonationController::SESSION_KEY));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Logged in as employee', 'actor_id' => $this->admin->id]);

        $this->get(route('employee.profile'))
            ->assertOk()
            ->assertSee('You are logged in as')
            ->assertSee('Back to Admin');
    }

    public function test_back_to_admin_returns_to_the_admin_account(): void
    {
        $this->actingAs($this->admin)->post(route('admin.employee.login-as', $this->employee->id));

        $this->post(route('impersonation.stop'))->assertRedirect(route('admin.employee'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertNull(session(ImpersonationController::SESSION_KEY));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Returned from employee account', 'actor_id' => $this->admin->id]);
    }

    public function test_line_manager_account_opens_on_its_own_dashboard(): void
    {
        $lead = $this->person('Lina', 'Lead', 'line_manager');

        $this->actingAs($this->admin)
            ->post(route('admin.employee.login-as', $lead->id))
            ->assertRedirect(route('line_manager.dashboard'));

        $this->assertAuthenticatedAs($lead->user);
    }

    public function test_admin_accounts_and_inactive_employees_cannot_be_opened(): void
    {
        $otherAdmin = $this->person('Other', 'Admin', 'administrator');
        $inactive = $this->person('Old', 'Staff', 'employee', 'inactive');

        foreach ([$otherAdmin, $inactive] as $target) {
            $this->actingAs($this->admin)
                ->post(route('admin.employee.login-as', $target->id))
                ->assertSessionHas('error');

            $this->assertAuthenticatedAs($this->admin);
        }

        $this->assertDatabaseMissing('audit_logs', ['action' => 'Logged in as employee']);
    }

    public function test_only_admin_can_use_login_as(): void
    {
        $other = $this->person('Sara', 'Ahmed', 'employee');

        $this->actingAs($this->employee->user)
            ->post(route('admin.employee.login-as', $other->id))
            ->assertForbidden();

        $this->assertAuthenticatedAs($this->employee->user);
    }

    public function test_back_to_admin_without_a_login_as_signs_out(): void
    {
        $this->actingAs($this->employee->user)
            ->post(route('impersonation.stop'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    private function person(string $first, string $last, string $role, string $status = 'active'): Employee
    {
        $user = User::factory()->create([
            'name' => "{$first} {$last}",
            'role_id' => $this->roles[$role],
            'status' => $status === 'active' ? 'Active' : 'Inactive',
        ]);

        return Employee::create([
            'employee_id' => 'GP-'.$user->id,
            'user_id' => $user->id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $user->email,
            'role_id' => $this->roles[$role],
            'employment_status' => $status,
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
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id');
            $table->string('zkteco_uid')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('reports_to_id')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('employment_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('cycle_start_day')->nullable();
            $table->integer('cycle_end_day')->nullable();
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
