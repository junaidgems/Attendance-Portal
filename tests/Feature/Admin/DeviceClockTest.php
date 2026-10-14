<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\BiometricSyncController;
use App\Models\User;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeviceClockTest extends TestCase
{
    use RefreshDatabase;

    /** Time written to the fake device clock. */
    public static ?DateTimeImmutable $written = null;

    private User $admin;

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

        self::$written = null;
        $this->admin = User::factory()->create(['role_id' => DB::table('roles')->insertGetId(['name' => 'administrator']), 'status' => 'Active']);
    }

    public function test_admin_sets_a_wrong_device_clock_to_pakistan_time(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 04:00:00', 'UTC')); // 09:00 AM in Pakistan
        $this->fakeDevice('2026-10-08 21:00:00');                       // device shows 09:00 PM

        $this->actingAs($this->admin)
            ->post(route('admin.biometric.device-clock'))
            ->assertSessionHas('success', 'Device clock set to 09:00 AM (it was 12h 0m ahead).');

        $this->assertSame('2026-10-08 09:00:00', self::$written->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Device clock set by Admin']);
    }

    public function test_page_shows_device_time_our_time_and_the_difference(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 04:00:00', 'UTC')); // 09:00 AM in Pakistan
        $this->fakeDevice('2026-10-08 21:00:00');

        $this->actingAs($this->admin)
            ->getJson(route('admin.biometric.device-clock.status'))
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'device' => '08 Oct 2026, 09:00:00 PM',
                'office' => '08 Oct 2026, 09:00:00 AM',
                'difference' => '12h 0m ahead',
                'ok' => false,
            ]);
    }

    public function test_page_shows_not_answering_when_the_device_is_off(): void
    {
        $this->fakeDevice(null);

        $this->actingAs($this->admin)
            ->getJson(route('admin.biometric.device-clock.status'))
            ->assertOk()
            ->assertExactJson(['success' => false, 'message' => 'The device is not answering.']);
    }

    public function test_device_that_does_not_answer_shows_an_error(): void
    {
        $this->fakeDevice(null);

        $this->actingAs($this->admin)
            ->post(route('admin.biometric.device-clock'))
            ->assertSessionHas('error');

        $this->assertNull(self::$written);
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_only_admin_can_set_the_device_clock(): void
    {
        $this->fakeDevice('2026-10-08 21:00:00');
        $employee = User::factory()->create(['role_id' => DB::table('roles')->insertGetId(['name' => 'employee']), 'status' => 'Active']);

        $this->actingAs($employee)->post(route('admin.biometric.device-clock'))->assertForbidden();

        $this->assertNull(self::$written);
    }

    /**
     * Fake device clock (null = the device does not answer).
     */
    private function fakeDevice(?string $clock): void
    {
        $this->app->bind(BiometricSyncController::class, fn () => new class($clock) extends BiometricSyncController
        {
            public function __construct(private ?string $fakeClock) {}

            protected function readDeviceClock(): DateTimeImmutable
            {
                return $this->fakeClock === null
                    ? throw new \RuntimeException('Device did not answer')
                    : new DateTimeImmutable($this->fakeClock);
            }

            protected function writeDeviceClock(DateTimeImmutable $time): void
            {
                DeviceClockTest::$written = $time;
            }
        });
    }
}
