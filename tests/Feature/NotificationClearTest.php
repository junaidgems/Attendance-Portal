<?php

namespace Tests\Feature;

use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationClearTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->user = User::factory()->create(['status' => 'Active']);
    }

    public function test_clearing_one_notification_hides_it_from_the_bell(): void
    {
        $cleared = $this->notify($this->user, 'Leave approved', 'leave:1');
        $kept = $this->notify($this->user, 'Waiver approved', 'waiver:1');

        $this->actingAs($this->user)
            ->post(route('notifications.clear', $cleared))
            ->assertRedirect();

        $this->assertNotNull($cleared->fresh()->cleared_at);
        $this->assertNotNull($cleared->fresh()->read_at);
        $this->assertNull($kept->fresh()->cleared_at);

        $bell = $this->renderBell();
        $this->assertStringNotContainsString('Leave approved', $bell);
        $this->assertStringContainsString('Waiver approved', $bell);
        $this->assertStringContainsString('1 new', $bell);
    }

    public function test_clear_all_empties_the_bell_for_this_user_only(): void
    {
        $this->notify($this->user, 'Leave approved', 'leave:1');
        $this->notify($this->user, 'Waiver approved', 'waiver:1', read: true);
        $otherUser = User::factory()->create(['status' => 'Active']);
        $others = $this->notify($otherUser, 'Someone else', 'leave:2');

        $this->actingAs($this->user)
            ->post(route('notifications.clear-all'))
            ->assertRedirect();

        $this->assertSame(0, PortalNotification::where('user_id', $this->user->id)->whereNull('cleared_at')->count());
        $this->assertSame(0, PortalNotification::where('user_id', $this->user->id)->whereNull('read_at')->count());
        $this->assertNull($others->fresh()->cleared_at);

        $bell = $this->renderBell();
        $this->assertStringContainsString("You're all caught up.", $bell);
        $this->assertStringNotContainsString('Clear all', $bell);
    }

    public function test_cleared_notifications_are_not_sent_again(): void
    {
        $this->notify($this->user, 'Missing punch', 'missing:2026-10-05');

        $this->actingAs($this->user)->post(route('notifications.clear-all'));

        $created = PortalNotification::sendToUsers([$this->user->id], 'attendance', 'Missing punch', 'Again', null, 'missing:2026-10-05');

        $this->assertSame(0, $created);
        $this->assertSame(1, PortalNotification::count());
    }

    public function test_a_user_cannot_clear_someone_elses_notification(): void
    {
        $otherUser = User::factory()->create(['status' => 'Active']);
        $others = $this->notify($otherUser, 'Someone else', 'leave:2');

        $this->actingAs($this->user)
            ->post(route('notifications.clear', $others))
            ->assertForbidden();

        $this->assertNull($others->fresh()->cleared_at);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->post(route('notifications.clear-all'))->assertRedirect(route('login'));
    }

    private function notify(User $user, string $title, string $dedupeKey, bool $read = false): PortalNotification
    {
        return PortalNotification::create([
            'user_id' => $user->id,
            'type' => 'leave',
            'title' => $title,
            'message' => $title.' message',
            'dedupe_key' => $dedupeKey,
            'read_at' => $read ? now() : null,
        ]);
    }

    private function renderBell(): string
    {
        $this->actingAs($this->user);

        return view('attendence.partials.topbar')->render();
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
            $table->timestamps();
        });

        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('cycle_start_day')->nullable();
            $table->integer('cycle_end_day')->nullable();
            $table->boolean('email_notifications_enabled')->default(false);
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
    }
}
