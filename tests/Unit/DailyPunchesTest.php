<?php

namespace Tests\Unit;

use App\Console\Commands\SyncDeviceAttendance;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DailyPunchesTest extends TestCase
{
    public function test_evening_punch_stored_as_check_in_is_the_check_out(): void
    {
        // Qazi Naeem, 1 Oct: the device stored both punches as "CheckIn"
        $days = SyncDeviceAttendance::dailyPunches([
            $this->punch(1, '2026-10-01 09:45:13', 'CheckIn'),
            $this->punch(1, '2026-10-01 17:34:31', 'CheckIn'),
        ]);

        $this->assertSame(['uid' => '1', 'date' => '2026-10-01', 'check_in' => '09:45:13', 'check_out' => '17:34:31'], $days['1_2026-10-01']);
    }

    public function test_wrong_out_key_in_the_morning_still_gives_the_right_order(): void
    {
        $days = SyncDeviceAttendance::dailyPunches([
            $this->punch(2, '2026-10-01 18:05:00', 'CheckIn'),
            $this->punch(2, '2026-10-01 09:02:00', 'CheckOut'),
        ]);

        $this->assertSame('09:02:00', $days['2_2026-10-01']['check_in']);
        $this->assertSame('18:05:00', $days['2_2026-10-01']['check_out']);
    }

    public function test_repeated_scans_at_arrival_are_not_a_check_out(): void
    {
        $days = SyncDeviceAttendance::dailyPunches([
            $this->punch(3, '2026-10-01 09:00:10', 'CheckIn'),
            $this->punch(3, '2026-10-01 09:00:50', 'CheckIn'),
            $this->punch(3, '2026-10-01 09:05:00', 'CheckIn'),
        ]);

        $this->assertSame('09:00:10', $days['3_2026-10-01']['check_in']);
        $this->assertNull($days['3_2026-10-01']['check_out']);
    }

    public function test_single_punch_has_no_check_out_and_days_and_people_are_kept_apart(): void
    {
        $days = SyncDeviceAttendance::dailyPunches([
            $this->punch(4, '2026-10-01 09:00:00', 'CheckIn'),
            $this->punch(4, '2026-10-02 09:10:00', 'CheckIn'),
            $this->punch(4, '2026-10-02 17:40:00', 'CheckOut'),
            $this->punch(5, '2026-10-02 10:00:00', 'CheckIn'),
        ]);

        $this->assertNull($days['4_2026-10-01']['check_out']);
        $this->assertSame('17:40:00', $days['4_2026-10-02']['check_out']);
        $this->assertNull($days['5_2026-10-02']['check_out']);
        $this->assertCount(3, $days);
    }

    public function test_ten_minutes_apart_counts_as_a_check_out(): void
    {
        $days = SyncDeviceAttendance::dailyPunches([
            $this->punch(6, '2026-10-01 09:00:00', 'CheckIn'),
            $this->punch(6, '2026-10-01 09:10:00', 'CheckIn'),
        ]);

        $this->assertSame('09:10:00', $days['6_2026-10-01']['check_out']);
    }

    private function punch(int $userId, string $at, string $state): object
    {
        return (object) ['userId' => $userId, 'recordedAt' => Carbon::parse($at), 'punchState' => (object) ['name' => $state]];
    }
}
