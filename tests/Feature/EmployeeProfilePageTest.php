<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeProfilePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        foreach (['shifts', 'grace_policies'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->nullable();
            $table->string('zkteco_uid')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('grace_policy_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('reports_to_id')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('cnic')->nullable();
            $table->string('job_title')->nullable();
            $table->date('joining_date')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('address')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('employment_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
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

    public function test_profile_shows_the_employees_own_details_without_account_actions(): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'employee', 'display_name' => 'Employee']);
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Accounts']);
        $user = User::factory()->create(['role_id' => $roleId, 'status' => 'Active']);

        $manager = Employee::create(['employee_id' => 'GP-001', 'first_name' => 'Ahmed', 'last_name' => 'Raza', 'employment_status' => 'active']);
        Employee::create([
            'employee_id' => 'GP-042', 'user_id' => $user->id, 'role_id' => $roleId, 'department_id' => $departmentId, 'reports_to_id' => $manager->id,
            'first_name' => 'Muhammad', 'last_name' => 'Jamshaid', 'email' => 'jamshaid@example.com', 'phone' => '0300-1234567',
            'cnic' => '42101-1234567-1', 'job_title' => 'Accountant', 'joining_date' => '2026-09-25', 'employment_status' => 'active',
        ]);

        $this->actingAs($user)->get(route('employee.profile'))
            ->assertOk()
            ->assertSee('Muhammad Jamshaid')
            ->assertSee('jamshaid@example.com')
            ->assertSee('GP-042')
            ->assertSee('Accounts')
            ->assertSee('Ahmed Raza')
            ->assertSee('25 Sep 2026')
            ->assertSee('0300-1234567')
            ->assertSee('42101-1234567-1')
            ->assertSee('Accountant')
            ->assertDontSee('Delete Account')
            ->assertDontSee('Deactivate Account')
            ->assertDontSee('EMP-2024-001')
            ->assertDontSee('Software Engineer');
    }

    public function test_admin_profile_shows_the_login_account_when_there_is_no_employee_record(): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'administrator', 'display_name' => 'Administrator']);
        $admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@example.com', 'role_id' => $roleId, 'status' => 'Active']);

        $this->actingAs($admin)->get(route('admin.profile'))
            ->assertOk()
            ->assertSee('Admin Profile')
            ->assertSee('Admin User')
            ->assertSee('admin@example.com')
            ->assertSee('Administrator')
            ->assertSee('no employee record')
            ->assertDontSee('ADM-001')
            ->assertDontSee('Delete')
            ->assertDontSee('Deactivate')
            ->assertSee('Change Password');
    }

    public function test_admin_can_change_their_name_email_and_password(): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'administrator', 'display_name' => 'Administrator']);
        $admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@example.com', 'role_id' => $roleId, 'status' => 'Active', 'password' => 'old-secret-1']);
        User::factory()->create(['email' => 'taken@example.com', 'role_id' => $roleId, 'status' => 'Active']);

        $this->actingAs($admin)->post(route('admin.profile.update'), ['name' => 'Ali Admin', 'email' => 'ali@example.com'])
            ->assertRedirect(route('admin.profile'))
            ->assertSessionHas('success', 'Profile updated.');
        $this->assertSame('Ali Admin', $admin->fresh()->name);
        $this->assertSame('ali@example.com', $admin->fresh()->email);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Profile updated']);

        // Somebody else's email cannot be taken
        $this->actingAs($admin)->post(route('admin.profile.update'), ['name' => 'Ali Admin', 'email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');
        $this->assertSame('ali@example.com', $admin->fresh()->email);

        // Wrong current password
        $this->actingAs($admin)->post(route('admin.profile.password'), ['current_password' => 'wrong', 'password' => 'new-secret-9', 'password_confirmation' => 'new-secret-9'])
            ->assertSessionHas('error', 'The current password is incorrect.');
        $this->assertTrue(Hash::check('old-secret-1', $admin->fresh()->password));

        // Confirmation must match
        $this->actingAs($admin)->post(route('admin.profile.password'), ['current_password' => 'old-secret-1', 'password' => 'new-secret-9', 'password_confirmation' => 'other'])
            ->assertSessionHasErrors('password');

        $this->actingAs($admin)->post(route('admin.profile.password'), ['current_password' => 'old-secret-1', 'password' => 'new-secret-9', 'password_confirmation' => 'new-secret-9'])
            ->assertSessionHas('success', 'Password changed.');
        $this->assertTrue(Hash::check('new-secret-9', $admin->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Password changed']);

        // Not for other roles
        $employeeRole = DB::table('roles')->insertGetId(['name' => 'employee']);
        $employee = User::factory()->create(['role_id' => $employeeRole, 'status' => 'Active']);
        $this->actingAs($employee)->post(route('admin.profile.password'), ['current_password' => 'x', 'password' => 'new-secret-9', 'password_confirmation' => 'new-secret-9'])->assertForbidden();
    }

    public function test_line_manager_and_manager_profiles_show_their_own_details_and_team_size(): void
    {
        $departmentId = DB::table('departments')->insertGetId(['name' => 'Sales']);

        foreach (['line_manager' => 'Line Manager', 'manager' => 'Manager'] as $role => $label) {
            $roleId = DB::table('roles')->insertGetId(['name' => $role, 'display_name' => $label]);
            $user = User::factory()->create(['role_id' => $roleId, 'status' => 'Active']);
            $lead = Employee::create([
                'employee_id' => "GP-{$role}", 'user_id' => $user->id, 'role_id' => $roleId, 'department_id' => $departmentId,
                'first_name' => 'Sara', 'last_name' => ucfirst($role), 'email' => "{$role}@example.com", 'phone' => '0311-0000000', 'employment_status' => 'active',
            ]);
            Employee::create(['employee_id' => "T1-{$role}", 'first_name' => 'Member', 'last_name' => 'One', 'reports_to_id' => $lead->id, 'employment_status' => 'active']);
            Employee::create(['employee_id' => "T2-{$role}", 'first_name' => 'Member', 'last_name' => 'Two', 'reports_to_id' => $lead->id, 'employment_status' => 'active']);
            Employee::create(['employee_id' => "T3-{$role}", 'first_name' => 'Member', 'last_name' => 'Old', 'reports_to_id' => $lead->id, 'employment_status' => 'inactive']);

            $this->actingAs($user)->get(route("{$role}.profile"))
                ->assertOk()
                ->assertSee("{$label} Profile")
                ->assertSee('Sara '.ucfirst($role))
                ->assertSee("{$role}@example.com")
                ->assertSee('Sales')
                ->assertSee('2 members')
                ->assertDontSee('Delete')
                ->assertDontSee('Deactivate')
                ->assertDontSee('Ahmad Khan')
                ->assertDontSee('Senior Team Lead');
        }
    }
}
