<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'zkteco_uid',
        'shift_id',
        'grace_policy_id',   // ✅ yahan change kiya
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'cnic',
        'department_id',
        'role_id',
        'reports_to_id',
        'job_title',
        'joining_date',
        'date_of_birth',
        'gender',
        'address',
        'emergency_contact',
        'employment_status',
        'off_days',
        'wfh_days',
        'probation_until',
    ];

    /**
     * Default probation: 3 months from the joining date (Admin may set any date).
     */
    public const PROBATION_MONTHS = 3;

    private static ?bool $hasProbation = null;

    /**
     * The probation_until column exists (until it is added, nobody is on probation).
     */
    public static function hasProbationColumn(): bool
    {
        return self::$hasProbation ??= Schema::hasColumn('employees', 'probation_until');
    }

    public static function forgetProbationCache(): void
    {
        self::$hasProbation = null;
    }

    /**
     * On probation through the "probation until" date: no paid leave, every deduction is a salary day.
     */
    public function isOnProbation(): bool
    {
        $until = $this->probation_until ?? null;

        return $until && Carbon::parse($until)->endOfDay()->isFuture();
    }

    /**
     * Days of a leave type the employee is entitled to in the leave year: none while on probation.
     */
    public function leaveAllowance(?LeaveType $type): float
    {
        if (! $type || $this->isOnProbation()) {
            return 0.0;
        }

        return (float) $type->days_per_year;
    }

    /**
     * Weekday keys as stored in off_days / wfh_days and the Attendance Policy working days.
     */
    public const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    private static ?bool $hasSchedule = null;

    /**
     * The off_days / wfh_days columns exist (until they are added, nobody has fixed off or WFH days).
     */
    public static function hasScheduleColumns(): bool
    {
        return self::$hasSchedule ??= Schema::hasColumn('employees', 'wfh_days');
    }

    public static function forgetScheduleCache(): void
    {
        self::$hasSchedule = null;
    }

    /**
     * "Sat,Sun" → ['Sat', 'Sun'] (unknown values dropped).
     *
     * @return array<int, string>
     */
    public static function parseWeekdays(?string $days): array
    {
        return array_values(array_intersect(self::WEEKDAYS, array_map('trim', explode(',', (string) $days))));
    }

    /**
     * Weekdays the employee is always off (their own weekend, e.g. Saturday), set by Admin on the employee.
     *
     * @return array<int, string>
     */
    public function offDays(): array
    {
        return self::parseWeekdays($this->off_days ?? null);
    }

    /**
     * Weekdays the employee always works from home (no request needed; portal check-in / out as on approved WFH).
     *
     * @return array<int, string>
     */
    public function wfhDays(): array
    {
        return self::parseWeekdays($this->wfh_days ?? null);
    }

    /**
     * Is this a working day for the employee: one of the Attendance Policy working days that is not one of
     * their own off days.
     */
    public function worksOn(Carbon|string $date, ?object $settings): bool
    {
        $weekday = Carbon::parse($date)->format('D');
        $workingDays = $settings ? explode(',', (string) $settings->working_days) : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

        return in_array($weekday, $workingDays, true) && ! in_array($weekday, $this->offDays(), true);
    }

    /**
     * A fixed Work From Home weekday of the employee (counts like an approved WFH day).
     */
    public function isFixedWfhDay(Carbon|string $date): bool
    {
        return in_array(Carbon::parse($date)->format('D'), $this->wfhDays(), true);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'reports_to_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'reports_to_id');
    }

    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function shift()
    {

        return $this->belongsTo(Shift::class);
    }

    public function gracePolicy()
    {
        return $this->belongsTo(GracePolicy::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function correctionRequests()
    {
        return $this->hasMany(CorrectionRequest::class);
    }

    /**
     * Shift rules for a date (Saturday timings on Saturday), falling back to the Attendance Policy standard shift.
     *
     * - half_day_max: worked minutes at or below this = Half Day (half of that day's shift: 8h → 4h, Saturday 5h → 2.5h)
     * - full_day_min: worked minutes at or above this = full day (shift length minus the early-departure grace)
     *
     * @return array{start: string, end: string, shift_minutes: int, half_day_max: int, full_day_min: int}
     */
    public function shiftTimesOn(Carbon|string $date, ?object $settings): array
    {
        $times = $this->shift
            ? $this->shift->timesOn($date)
            : ['start' => $settings->standard_shift_start ?? '09:00:00', 'end' => $settings->standard_shift_end ?? '18:00:00'];

        $day = Carbon::parse($date)->format('Y-m-d');
        $shiftMinutes = max(0, (int) Carbon::parse($day.' '.$times['start'])->diffInMinutes(Carbon::parse($day.' '.$times['end']), false));
        $earlyGrace = (int) ($this->gracePolicy->early_grace_minutes ?? ($settings->early_departure_grace_minutes ?? 0));

        return $times + [
            'shift_minutes' => $shiftMinutes,
            'half_day_max' => intdiv($shiftMinutes, 2),
            'full_day_min' => max(0, $shiftMinutes - $earlyGrace),
        ];
    }

    /**
     * Attendance Policy "Half day if check-in is later than N minutes after the shift start" (empty = off):
     * arriving that late counts the day as a Half Day (instead of Late), however long the employee stayed.
     * Days before "Count Penalties From" keep their old status.
     */
    public static function isHalfDayByLateArrival(?self $employee, string $date, ?string $checkIn, ?object $settings): bool
    {
        $minutes = (int) ($settings->half_day_after_late_minutes ?? 0);

        if ($minutes <= 0 || ! $checkIn) {
            return false;
        }

        $countFrom = self::penaltiesCountFrom($settings);
        if ($countFrom && Carbon::parse($date)->toDateString() < $countFrom) {
            return false;
        }

        $start = $employee
            ? $employee->shiftTimesOn($date, $settings)['start']
            : ($settings->standard_shift_start ?? '09:00:00');

        return Carbon::parse($date.' '.$checkIn)->gt(Carbon::parse($date.' '.$start)->addMinutes($minutes));
    }

    /**
     * A day with only one device punch: a punch in the second half of the shift is the check-out
     * (the morning punch was missed or the device was down), a punch before that is the check-in.
     */
    public static function singlePunchIsCheckOut(?self $employee, string $date, string $time, ?object $settings): bool
    {
        $rules = $employee
            ? $employee->shiftTimesOn($date, $settings)
            : ['start' => $settings->standard_shift_start ?? '09:00:00', 'end' => $settings->standard_shift_end ?? '18:00:00'];

        $start = Carbon::parse($date.' '.$rules['start']);
        $shiftMinutes = max(0, (int) $start->diffInMinutes(Carbon::parse($date.' '.$rules['end']), false));

        return Carbon::parse($date.' '.$time)->gte($start->addMinutes(intdiv($shiftMinutes, 2)));
    }

    /**
     * Late and early-out minutes for one day, using the employee's shift and grace policy
     * (falls back to the Attendance Policy standard shift and grace).
     *
     * Late = check-in after (shift start + late grace).
     * Early out = check-out before (shift end − early grace) AND less than the full working time.
     *
     * @return array{late_minutes: int, early_out_minutes: int}
     */
    public static function punctuality(?self $employee, string $date, ?string $checkIn, ?string $checkOut, ?object $settings): array
    {
        $result = ['late_minutes' => 0, 'early_out_minutes' => 0];

        if (! $checkIn) {
            return $result;
        }

        ['start' => $shiftStart, 'end' => $shiftEnd] = $employee
            ? $employee->shiftTimesOn($date, $settings)
            : ['start' => $settings->standard_shift_start ?? '09:00:00', 'end' => $settings->standard_shift_end ?? '18:00:00'];

        // Full working time for the day: shift length minus the early-departure grace
        $fullDayMinutes = $employee
            ? $employee->shiftTimesOn($date, $settings)['full_day_min']
            : max(0, (int) Carbon::parse($date.' '.$shiftStart)->diffInMinutes(Carbon::parse($date.' '.$shiftEnd), false) - (int) ($settings->early_departure_grace_minutes ?? 0));
        $lateGrace = (int) ($employee?->gracePolicy->late_grace_minutes ?? ($settings->late_arrival_grace_minutes ?? 0));
        $earlyGrace = (int) ($employee?->gracePolicy->early_grace_minutes ?? ($settings->early_departure_grace_minutes ?? 0));

        $in = Carbon::parse($date.' '.$checkIn);
        $lateFrom = Carbon::parse($date.' '.$shiftStart)->addMinutes($lateGrace);
        $result['late_minutes'] = $in->gt($lateFrom) ? (int) $lateFrom->diffInMinutes($in) : 0;

        if ($checkOut) {
            $out = Carbon::parse($date.' '.$checkOut);
            $earlyUntil = Carbon::parse($date.' '.$shiftEnd)->subMinutes($earlyGrace);

            // A single punch (out not after in) is a missing punch, not an early out.
            // Leaving before the shift end is fine when the full working time was completed
            // (e.g. came at 07:00, left at 15:00 on a 09:30–17:30 shift).
            if ($out->gt($in) && $out->lt($earlyUntil) && (int) $in->diffInMinutes($out) < $fullDayMinutes) {
                $result['early_out_minutes'] = (int) $out->diffInMinutes($earlyUntil);
            }
        }

        return $result;
    }

    /**
     * First day of attendance data: the device sync does not save older punches, and earlier days
     * are shown without a status (not as absent).
     */
    public const ATTENDANCE_START_DATE = '2026-09-25';

    /**
     * Roles that lead a team (review requests of the people reporting to them).
     */
    public const TEAM_LEAD_ROLES = ['line_manager', 'manager'];

    /**
     * Casual Leave deducted when a penalty limit is reached (0.5 = half a day, 1 = a full day):
     * 3 late arrivals → 0.5, 3 early outs → 0.5, 2 half-days worked → 1.
     */
    public const PENALTY_LEAVE_DAYS = ['late' => 0.5, 'early_out' => 0.5, 'half_day' => 1, 'absent' => 1];

    /**
     * Lower-cased role name, e.g. "Line_manager" → "line_manager".
     */
    public function roleKey(): string
    {
        return strtolower((string) $this->role?->name);
    }

    public function isTeamLead(): bool
    {
        return in_array($this->roleKey(), self::TEAM_LEAD_ROLES, true);
    }

    /**
     * A Director sees the whole company and makes the final decision on the requests of the Managers
     * reporting to them (they do not submit requests of their own).
     */
    public function isDirector(): bool
    {
        return $this->roleKey() === 'director';
    }

    /**
     * Route-name prefix of this person's own portal: "employee", "line_manager", "manager" or "director".
     */
    public function portalPrefix(): string
    {
        return match (true) {
            $this->isDirector() => 'director',
            $this->isTeamLead() => $this->roleKey(),
            default => 'employee',
        };
    }

    /**
     * "Line Manager" / "Manager" — for messages and labels.
     */
    public function roleLabel(): string
    {
        return $this->role?->display_name ?: ucwords(str_replace('_', ' ', $this->roleKey()));
    }

    /**
     * Status of one attendance day from its punches, using the shift rules and approved waivers:
     * Missing Punch, Half Day, Late, Early Out or Present. "waived" lists issues removed by approved waivers.
     * "in_progress" is set for today when the employee has checked in and the shift has not ended yet.
     *
     * @return array{status: string, waived: array<int, string>, in_progress?: bool}
     */
    public function dayStatus(string $date, ?object $record, ?object $settings): array
    {
        if (! $record?->check_in && ! $record?->check_out) {
            return ['status' => 'Absent', 'waived' => []];
        }

        // Only an evening punch: the check-in is missing (no late, no early out can be counted)
        if (! $record->check_in) {
            return ['status' => 'Missing Punch', 'waived' => []];
        }

        $in = Carbon::parse($date.' '.$record->check_in);
        $out = $record->check_out ? Carbon::parse($date.' '.$record->check_out) : null;

        $rules = $this->shiftTimesOn($date, $settings);

        if (! $out || ! $out->gt($in)) {
            // Today, before the shift ends, no check-out yet just means the employee is still at work
            if (Carbon::parse($date)->isToday() && now()->lt(Carbon::parse($date.' '.$rules['end']))) {
                $isLate = self::punctuality($this, $date, $record->check_in, null, $settings)['late_minutes'] > 0;
                $isLateWaived = $isLate && DeductionWaiver::isWaived($record, 'late');

                return [
                    'status' => $isLate && ! $isLateWaived ? 'Late' : 'Present',
                    'waived' => $isLateWaived ? ['Late'] : [],
                    'in_progress' => true,
                ];
            }

            return ['status' => 'Missing Punch', 'waived' => []];
        }

        $punctuality = self::punctuality($this, $date, $record->check_in, $record->check_out, $settings);
        $worked = (int) $in->diffInMinutes($out);
        $lateHalfDay = self::isHalfDayByLateArrival($this, $date, $record->check_in, $settings);
        $waived = [];

        // Same order as everywhere: half day, then late, then early out
        foreach ([
            'Half Day' => ['half_day', $worked <= $rules['half_day_max'] || $lateHalfDay],
            'Late' => ['late', $punctuality['late_minutes'] > 0 && ! $lateHalfDay],
            'Early Out' => ['early_out', $punctuality['early_out_minutes'] > 0],
        ] as $status => [$type, $applies]) {
            if (! $applies) {
                continue;
            }
            if (! DeductionWaiver::isWaived($record, $type)) {
                return ['status' => $status, 'waived' => $waived];
            }
            $waived[] = $status;
        }

        return ['status' => 'Present', 'waived' => $waived];
    }

    /**
     * Day-by-day attendance with the same rules as the attendance pages: weekend, approved leave,
     * punches (shift rules, approved waivers, approved corrections), holiday, absent. Future days have an empty status.
     *
     * @return Collection<string, array{date: string, status: string, waived: array<int, string>, in_progress: bool, wfh: ?string, note: ?string, record: ?object}>
     */
    public function attendanceDays(Carbon|string $from, Carbon|string $to, ?object $settings, ?Collection $holidays = null): Collection
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        $holidays ??= DB::table('holidays')->get();
        $today = now()->startOfDay();

        $records = $this->zkteco_uid
            ? DB::table('device_attendance')
                ->where('uid', $this->zkteco_uid)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->get()
                ->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString())
            : collect();

        $leaves = $this->leaveRequests()->approvedAbsence()
            ->whereDate('from_date', '<=', $to->toDateString())
            ->whereDate('to_date', '>=', $from->toDateString())
            ->get();
        $wfhDays = WfhRequest::approvedDayKeys([$this->id], $from, $to);

        $days = collect();
        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
            $date = $cursor->toDateString();
            $record = $records->get($date);
            $day = ['status' => '', 'waived' => [], 'in_progress' => false];

            if ($this->isDirector()) {
                // Directors are not tracked for attendance: never present, late or absent on any page or report
            } elseif ($this->joining_date && $date < Carbon::parse($this->joining_date)->toDateString()) {
                // Before the joining date: not part of the attendance (not absent)
            } elseif (! $this->worksOn($cursor, $settings)) {
                $day['status'] = 'Weekend';
            } elseif ($leaves->contains(fn ($l) => $date >= $l->from_date && $date <= $l->to_date)) {
                $day['status'] = 'On Leave';
            } elseif ($record?->check_in || $record?->check_out) {
                $day = $this->dayStatus($date, $record, $settings) + $day;
            } elseif ($holidays->contains(fn ($h) => $date >= $h->from_date && $date <= ($h->to_date ?? $h->from_date))) {
                $day['status'] = 'Holiday';
            } elseif ($cursor->lte($today) && $date >= self::ATTENDANCE_START_DATE) {
                $day['status'] = 'Absent';
            }

            $isWorkDay = ! in_array($day['status'], ['', 'Weekend', 'Holiday', 'On Leave'], true);

            $days->put($date, $day + [
                'date' => $date,
                'wfh' => $isWorkDay ? WfhRequest::dayLabel($record, isset($wfhDays[$this->id.'_'.$date])) : null,
                'note' => $record->adjustment_note ?? null,
                'record' => $record,
            ]);
        }

        return $days;
    }

    /**
     * One Attendance Breakdown table row for a day from attendanceDays().
     *
     * @param  array{date: string, status: string, waived: array<int, string>, in_progress: bool, wfh: ?string, note: ?string, record: ?object}  $day
     * @return array{employee: string, emp_id: ?string, department: string, date: string, status: string, missing_punch: bool, shift: string, scheduled: string, in: string, out: string, worked: string, late: string, early: string, remarks: string}
     */
    public function breakdownRow(array $day, ?object $settings): array
    {
        $record = $day['record'];
        $times = $this->shiftTimesOn($day['date'], $settings);
        $hasPunches = ($record?->check_in || $record?->check_out) && ! in_array($day['status'], ['Weekend', 'On Leave'], true);
        $punctuality = $hasPunches
            ? self::punctuality($this, $day['date'], $record->check_in, $record->check_out, $settings)
            : ['late_minutes' => 0, 'early_out_minutes' => 0];
        $workedMinutes = $hasPunches && $record->check_in && $record->check_out
            ? (int) Carbon::parse($record->check_in)->diffInMinutes(Carbon::parse($record->check_out), false)
            : 0;

        return [
            'employee' => $this->full_name,
            'emp_id' => $this->employee_id,
            'department' => $this->department->name ?? '-',
            'date' => Carbon::parse($day['date'])->format('D d M'),
            'status' => $day['status'],
            'missing_punch' => $day['status'] === 'Missing Punch',
            'shift' => $this->shift->name ?? 'Standard Shift',
            'scheduled' => Carbon::parse($times['start'])->format('H:i').'-'.Carbon::parse($times['end'])->format('H:i'),
            'in' => match (true) {
                ! $hasPunches => '—',
                ! $record->check_in => 'Missing Punch',
                default => Carbon::parse($record->check_in)->format('h:i A'),
            },
            'out' => match (true) {
                ! $hasPunches => '—',
                $day['in_progress'] => 'In progress',
                ! $record->check_out, $day['status'] === 'Missing Punch' && $record->check_in => 'Missing Punch',
                default => Carbon::parse($record->check_out)->format('h:i A'),
            },
            'worked' => $workedMinutes > 0 ? round($workedMinutes / 60, 1).'h' : '—',
            'late' => $punctuality['late_minutes'] > 0 ? $punctuality['late_minutes'].'m' : '—',
            'early' => $punctuality['early_out_minutes'] > 0 ? $punctuality['early_out_minutes'].'m' : '—',
            'remarks' => self::dayTimeline($day),
        ];
    }

    /**
     * Attendance Breakdown tabs from breakdownRow() rows. "Present" lists every day the employee came in.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, Collection<int, array<string, mixed>>>
     */
    public static function breakdownTabs(Collection $rows): array
    {
        $rows = $rows->values();

        return [
            'all' => $rows,
            'missing' => $rows->where('missing_punch', true)->values(),
            'halfday' => $rows->where('status', 'Half Day')->values(),
            'earlyout' => $rows->where('status', 'Early Out')->values(),
            'absent' => $rows->where('status', 'Absent')->values(),
            'late' => $rows->where('status', 'Late')->values(),
            'present' => $rows->whereIn('status', ['Present', 'Late', 'Early Out', 'Half Day', 'Missing Punch'])->values(),
            'onleave' => $rows->where('status', 'On Leave')->values(),
        ];
    }

    /**
     * Short timeline text for one day from attendanceDays(), e.g. "On Time · WFH" or "Late · Corrected".
     *
     * @param  array{status: string, waived: array<int, string>, in_progress: bool, wfh: ?string, note: ?string, record: ?object}  $day
     */
    public static function dayTimeline(array $day): string
    {
        $parts = [match (true) {
            $day['in_progress'] => 'In Progress',
            $day['status'] === 'Present' => 'On Time',
            $day['status'] === '' => '—',
            default => $day['status'],
        }];

        if ($day['waived']) {
            $parts[] = implode(', ', $day['waived']).' waived';
        }
        if ($day['wfh']) {
            $parts[] = $day['wfh'];
        }
        if ($day['note']) {
            $parts[] = $day['note'];
        }

        return implode(' · ', $parts);
    }

    /**
     * One month of My Attendance (Employee / Line Manager / Manager pages): the calendar rows and the summary counts,
     * all from attendanceDays(), so every page shows a day the same way.
     *
     * @return array{data: array<int, array<string, mixed>>, present: int, absent: int, late: int, early: int, half: int, missing: int, workingDaysCount: int}
     */
    public function monthCalendar(int $year, int $month, ?object $settings): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $holidays = DB::table('holidays')->get();

        $data = $this->attendanceDays($start, $start->copy()->endOfMonth(), $settings, $holidays)
            ->map(fn (array $day) => $this->calendarRow($day, $holidays))
            ->values()
            ->all();

        $rows = collect($data);

        return [
            'data' => $data,
            'present' => $rows->whereIn('status', ['Present', 'Late', 'Early Out', 'Half Day'])->count(),
            'absent' => $rows->where('status', 'Absent')->count(),
            'late' => $rows->where('status', 'Late')->count(),
            'early' => $rows->where('status', 'Early Out')->count(),
            'half' => $rows->where('status', 'Half Day')->count(),
            'missing' => $rows->filter(fn ($d) => $d['in'] === 'Missing Punch' || $d['out'] === 'Missing Punch')->count(),
            'workingDaysCount' => $rows->reject(fn ($d) => in_array($d['status'], ['Weekend', 'Holiday', ''], true))->count(),
        ];
    }

    /**
     * One My Attendance calendar row for a day from attendanceDays(). A missing punch shows as Present with
     * "Missing Punch" in the IN or OUT column (and a correction link on the page).
     *
     * @param  array{date: string, status: string, waived: array<int, string>, in_progress: bool, wfh: ?string, note: ?string, record: ?object}  $day
     * @return array{date: int, full_date: string, adjustment: ?string, day: string, status: string, in: string, out: string, net: string|float, timeline: string, wfh?: string}
     */
    public function calendarRow(array $day, Collection $holidays): array
    {
        $shown = $this->dayPresentation($day, $holidays, 'h:i A');
        $date = Carbon::parse($day['date']);

        $row = [
            'date' => $date->day,
            'full_date' => $day['date'],
            'adjustment' => $day['note'],
            'day' => $date->format('D'),
            'status' => $shown['status'],
            'in' => $shown['in'],
            'out' => $shown['out'],
            'net' => $shown['minutes'] !== null ? round($shown['minutes'] / 60, 1) : '—',
            'timeline' => $shown['timeline'],
        ];

        if ($shown['wfh']) {
            $row['wfh'] = $shown['wfh'];
        }

        return $row;
    }

    /**
     * One Team Attendance table row (Manager / Line Manager / Director pages) for a day from attendanceDays().
     * "tags" drive the page's filter buttons (present, late, early-out, half-day, absent).
     *
     * @param  array{date: string, status: string, waived: array<int, string>, in_progress: bool, wfh: ?string, note: ?string, record: ?object}  $day
     * @return array{name: string, emp_id: ?string, date: string, status: string, tags: array<int, string>, in: string, out: string, net: string, net_minutes: ?int, timeline: string, timeline_class: string, wfh?: string}
     */
    public function teamRow(array $day, Collection $holidays): array
    {
        $shown = $this->dayPresentation($day, $holidays, 'H:i');
        $status = $day['status'];
        $minutes = $shown['minutes'];

        [$tags, $class] = match (true) {
            $status === 'Absent' => [['absent'], 'timeline-absent'],
            $status === 'Missing Punch' => [['present', 'absent'], 'timeline-absent'],
            $status === 'Late' => [['present', 'late'], 'timeline-late'],
            $status === 'Early Out' => [['present', 'early-out'], 'timeline-late'],
            $status === 'Half Day' => [['present', 'half-day'], 'timeline-late'],
            $status === 'Present' => [['present'], 'timeline-on-time'],
            default => [[], ''],
        };

        $row = [
            'name' => $this->full_name,
            'emp_id' => $this->employee_id,
            'date' => Carbon::parse($day['date'])->format('D d M'),
            'status' => $shown['status'],
            'tags' => $tags,
            'in' => $shown['in'],
            'out' => $shown['out'],
            'net' => $minutes !== null ? intdiv($minutes, 60).'h '.($minutes % 60).'m' : '—',
            'net_minutes' => $minutes,
            'timeline' => $status === 'Absent' && ! $shown['wfh'] ? 'No Punch' : $shown['timeline'],
            'timeline_class' => $class,
        ];

        if ($shown['wfh']) {
            $row['wfh'] = $shown['wfh'];
        }

        return $row;
    }

    /**
     * What the attendance pages show for a day: the status label, IN / OUT times (or "Missing Punch"), worked
     * minutes and the timeline text, with the Work From Home label added.
     *
     * @param  array{date: string, status: string, waived: array<int, string>, in_progress: bool, wfh: ?string, note: ?string, record: ?object}  $day
     * @return array{status: string, in: string, out: string, minutes: ?int, timeline: string, wfh: ?string}
     */
    private function dayPresentation(array $day, Collection $holidays, string $timeFormat): array
    {
        $record = $day['record'];
        $status = $day['status'];
        $hasPunches = in_array($status, ['Present', 'Late', 'Early Out', 'Half Day', 'Missing Punch'], true);
        $missingPunch = $status === 'Missing Punch';

        $in = $hasPunches && $record?->check_in ? Carbon::parse($record->check_in)->format($timeFormat) : ($missingPunch ? 'Missing Punch' : '—');
        $out = match (true) {
            ! $hasPunches, $day['in_progress'] => '—',
            // Only one punch, or an out punch not after the in punch: the check-out is missing
            $missingPunch && ($record?->check_in || ! $record?->check_out) => 'Missing Punch',
            ! $record?->check_out => 'Missing Punch',
            default => Carbon::parse($record->check_out)->format($timeFormat),
        };

        $minutes = null;
        if ($hasPunches && ! $missingPunch && ! $day['in_progress'] && $record?->check_in && $record->check_out) {
            $minutes = max(0, (int) Carbon::parse($day['date'].' '.$record->check_in)->diffInMinutes(Carbon::parse($day['date'].' '.$record->check_out), false));
        }

        $holiday = $holidays->first(fn ($h) => $day['date'] >= $h->from_date && $day['date'] <= ($h->to_date ?? $h->from_date));

        $timeline = match (true) {
            $status === 'Holiday' => $holiday->name ?? 'Holiday',
            $day['in_progress'] => 'In Progress',
            $missingPunch => 'Missing Punch',
            $status === 'Present' => 'On Time',
            $status === 'Late', $status === 'Early Out', $status === 'Half Day' => $status,
            default => '—',
        };

        // Came in on a holiday
        if ($hasPunches && $holiday) {
            $timeline .= ' (Holiday)';
        }

        // Approved Work From Home day: portal check-in, or approved but never checked in
        $wfh = $day['wfh'];
        if ($wfh && ! in_array($status, ['', 'Weekend', 'Holiday', 'On Leave'], true)) {
            $timeline = $status === 'Absent' ? $wfh : $timeline.' · '.$wfh;
        } else {
            $wfh = null;
        }

        return [
            // The pages list a missing punch under Present, with "Missing Punch" in the IN / OUT column
            'status' => $missingPunch ? 'Present' : $status,
            'in' => $in,
            'out' => $out,
            'minutes' => $minutes,
            'timeline' => $timeline,
            'wfh' => $wfh,
        ];
    }

    public function wfhRequests()
    {
        return $this->hasMany(WfhRequest::class);
    }

    public function deductionWaivers()
    {
        return $this->hasMany(DeductionWaiver::class);
    }

    /**
     * Attendance cycle (Attendance Policy start/end day) that contains the given date.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function attendanceCycle(Carbon|string $date, ?object $settings): array
    {
        $date = Carbon::parse($date)->startOfDay();
        $startDay = (int) ($settings->cycle_start_day ?? 1);
        $endDay = (int) ($settings->cycle_end_day ?? 31);

        $start = Carbon::create($date->year, $date->month, min($startDay, $date->daysInMonth));
        if ($start->gt($date)) {
            $previous = $date->copy()->startOfMonth()->subMonth();
            $start = Carbon::create($previous->year, $previous->month, min($startDay, $previous->daysInMonth));
        }

        // End day on or after the start day → same month (e.g. 1–31), otherwise the next month (e.g. 26–25)
        $endMonth = $endDay >= $startDay ? $start->copy() : $start->copy()->startOfMonth()->addMonth();
        $end = Carbon::create($endMonth->year, $endMonth->month, min($endDay, $endMonth->daysInMonth));

        return [$start, $end];
    }

    /**
     * First day counted for late / early-out / half-day penalties (Attendance Policy), or null to count every day.
     */
    public static function penaltiesCountFrom(?object $settings): ?string
    {
        $date = $settings->penalty_policy_from ?? null;

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    /**
     * Penalty items (late / early out / half day) and casual-leave due for a date range.
     *
     * Days that are not working days, holidays or covered by approved leave are skipped.
     * Items with an approved waiver are listed but not counted.
     * Days before the Attendance Policy's "Count penalties from" date are not penalty items at all
     * (they still show as Late / Early Out / Half Day on the attendance pages).
     *
     * @return array{items: Collection, counts: array<string, int>, due: array<string, int>, cl_due: int|float, thresholds: array<string, int>}
     */
    public function penaltySummary(Carbon|string $from, Carbon|string $to, ?object $settings = null): array
    {
        $settings ??= DB::table('attendance_settings')->first();
        $from = Carbon::parse($from)->toDateString();
        $to = Carbon::parse($to)->toDateString();

        $countFrom = self::penaltiesCountFrom($settings);
        if ($countFrom && $countFrom > $from) {
            $from = $countFrom;
        }

        $thresholds = [
            'late' => max(1, (int) ($settings->late_arrivals_per_casual_leave ?? 3)),
            'early_out' => max(1, (int) ($settings->early_outs_per_casual_leave ?? 3)),
            'half_day' => max(1, (int) ($settings->half_days_per_casual_leave ?? 2)),
            // 0 = absent days are not deducted
            'absent' => max(0, (int) ($settings->absent_days_per_casual_leave ?? 0)),
        ];

        $items = collect();

        if ($this->zkteco_uid) {
            $holidays = DB::table('holidays')->get();
            $leaves = $this->leaveRequests()->approvedAbsence()
                ->whereDate('from_date', '<=', $to)
                ->whereDate('to_date', '>=', $from)
                ->get();

            $records = DB::table('device_attendance')
                ->where('uid', $this->zkteco_uid)
                ->whereBetween('date', [$from, $to])
                ->whereNotNull('check_in')
                ->orderBy('date')
                ->get();

            foreach ($records as $r) {
                $date = Carbon::parse($r->date)->toDateString();

                $skip = ! $this->worksOn($date, $settings)
                    || $holidays->contains(fn ($h) => $date >= $h->from_date && $date <= ($h->to_date ?? $h->from_date))
                    || $leaves->contains(fn ($l) => $date >= Carbon::parse($l->from_date)->toDateString() && $date <= Carbon::parse($l->to_date)->toDateString());

                if ($skip) {
                    continue;
                }

                $punctuality = self::punctuality($this, $date, $r->check_in, $r->check_out, $settings);
                $rules = $this->shiftTimesOn($date, $settings);

                $worked = 0;
                if ($r->check_out) {
                    $in = Carbon::parse($date.' '.$r->check_in);
                    $out = Carbon::parse($date.' '.$r->check_out);
                    $worked = $out->gt($in) ? (int) $in->diffInMinutes($out) : 0;
                }

                // Arriving later than the policy allows is a half day, not a late
                $lateHalfDay = $worked > 0 && self::isHalfDayByLateArrival($this, $date, $r->check_in, $settings);

                if ($punctuality['late_minutes'] > 0 && ! $lateHalfDay) {
                    $items->push(['date' => $date, 'type' => 'late', 'minutes' => $punctuality['late_minutes']]);
                }

                // A half day is also an early leave — count it once, as a half day
                if ($worked > 0 && ($worked <= $rules['half_day_max'] || $lateHalfDay)) {
                    $items->push(['date' => $date, 'type' => 'half_day', 'minutes' => $worked]);
                } elseif ($punctuality['early_out_minutes'] > 0) {
                    $items->push(['date' => $date, 'type' => 'early_out', 'minutes' => $punctuality['early_out_minutes']]);
                }
            }

            // Absent days (no punch at all, no leave, not a weekend / holiday / WFH day, before today)
            if ($thresholds['absent'] > 0) {
                $today = now()->toDateString();
                foreach ($this->attendanceDays($from, min($to, $today), $settings, $holidays) as $date => $day) {
                    if ($day['status'] === 'Absent' && $date < $today && ! $day['wfh']) {
                        $items->push(['date' => $date, 'type' => 'absent', 'minutes' => 0]);
                    }
                }
            }
        }

        $waivers = $this->deductionWaivers()
            ->whereBetween('date', [$from, $to])
            ->get()
            ->keyBy(fn ($w) => Carbon::parse($w->date)->toDateString().'_'.$w->type);

        $items = $items->map(function (array $item) use ($waivers) {
            $w = $waivers->get($item['date'].'_'.$item['type']);

            return $item + [
                'type_label' => DeductionWaiver::TYPE_LABELS[$item['type']],
                'waiver_id' => $w->id ?? null,
                'waiver_status' => $w->status ?? null,
                'waiver_reason' => $w->reason ?? null,
                'lm_status' => $w->lm_status ?? null,
                'lm_note' => $w->lm_note ?? null,
                // Admin's note once the waiver is decided
                'admin_note' => in_array($w->status ?? null, ['approved', 'rejected'], true) ? $w->manager_note : null,
                'lm_reviewed_at' => $w->lm_reviewed_at ?? null,
                'counted' => ($w->status ?? null) !== 'approved',
            ];
        });

        $counts = [];
        $due = [];
        $clDue = 0;
        foreach ($thresholds as $type => $threshold) {
            $counts[$type] = $items->where('type', $type)->where('counted', true)->count();
            $due[$type] = $threshold > 0 ? intdiv($counts[$type], $threshold) : 0;
            // Casual Leave days due: e.g. 3 late arrivals → 0.5, 2 half-days → 1
            $clDue += $due[$type] * self::PENALTY_LEAVE_DAYS[$type];
        }

        return [
            'items' => $items->sortByDesc('date')->values(),
            'counts' => $counts,
            'due' => $due,
            'cl_due' => $clDue,
            'thresholds' => $thresholds,
        ];
    }

    /**
     * Keep the automatic penalty Casual Leaves of one cycle in line with the penalty count:
     * creates a leave at every threshold (e.g. the 3rd late arrival) and removes it again when
     * waivers bring the count back below the threshold. Only runs when the penalty policy is enabled.
     *
     * @return array{created: int, reversed: int, shortfall: int}
     */
    public function syncPenaltyLeaves(Carbon|string $dateInCycle, ?object $settings = null): array
    {
        $settings ??= DB::table('attendance_settings')->first();
        $result = ['created' => 0, 'reversed' => 0, 'shortfall' => 0];
        $casualType = LeaveType::where('name', 'Casual Leave')->first();

        if (! $settings?->penalty_policy_enabled || ! $casualType) {
            return $result;
        }

        [$cycleStart, $cycleEnd] = self::attendanceCycle($dateInCycle, $settings);

        // Payroll-locked month: its penalty leaves are final
        if (MonthlyLock::firstLockedIn($cycleStart, $cycleEnd)) {
            return $result;
        }

        $summary = $this->penaltySummary($cycleStart, $cycleEnd, $settings);
        $cycleKey = $cycleStart->toDateString();

        // Wanted penalty leaves: key "2026-09-01:late:1" dated on the item that crossed the threshold
        $wanted = collect();
        foreach ($summary['due'] as $type => $count) {
            $counted = $summary['items']->where('type', $type)->where('counted', true)->sortBy('date')->values();
            for ($n = 1; $n <= $count; $n++) {
                $trigger = $counted[$n * $summary['thresholds'][$type] - 1];
                $wanted->put("{$cycleKey}:{$type}:{$n}", [
                    'type' => $type,
                    'date' => $trigger['date'],
                    'dates' => $counted->slice(($n - 1) * $summary['thresholds'][$type], $summary['thresholds'][$type])->pluck('date'),
                ]);
            }
        }

        $existing = $this->leaveRequests()
            ->where('is_penalty', true)
            ->where('penalty_key', 'like', $cycleKey.':%')
            ->get()
            ->keyBy('penalty_key');

        foreach ($existing->diffKeys($wanted) as $leave) {
            $leave->delete();
            $result['reversed']++;

            PortalNotification::notifyEmployee($this, 'penalty', 'Penalty leave returned',
                "{$leave->days} Casual Leave deducted for \"{$leave->reason}\" has been returned to your balance after an approved waiver.",
                'penalty', "penalty:reversed:{$leave->id}");
        }

        // Casual Leave left in the leave year of this cycle
        $used = (float) $this->leaveRequests()
            ->where('leave_type_id', $casualType->id)
            ->where('status', 'approved')
            ->inLeaveYear($cycleStart)
            ->sum('days');
        $balance = $this->leaveAllowance($casualType) - $used;

        foreach ($wanted->diffKeys($existing) as $key => $penalty) {
            $leaveDays = self::PENALTY_LEAVE_DAYS[$penalty['type']];

            if ($balance < $leaveDays) {

                // No Casual Leave left: the penalty becomes a salary-day deduction (shown on the penalty pages)
                $result['shortfall']++;

                continue;
            }

            $label = $penalty['type'] === 'absent' ? 'absent day(s)' : strtolower(DeductionWaiver::TYPE_LABELS[$penalty['type']]).'s';
            $dates = $penalty['dates']->map(fn ($d) => Carbon::parse($d)->format('d M'))->implode(', ');

            $penaltyLeave = $this->leaveRequests()->create([
                'leave_type_id' => $casualType->id,
                'from_date' => $penalty['date'],
                'to_date' => $penalty['date'],
                'days' => $leaveDays,
                'status' => 'approved',
                'reason' => "Penalty: {$summary['thresholds'][$penalty['type']]} {$label} ({$dates})",
                'admin_note' => 'Deducted automatically by the attendance policy. Request a waiver on the Penalty & Waivers page to reverse it.',
                'reviewed_by' => 'System',
                'reviewed_at' => now(),
                'manager_status' => LeaveRequest::MANAGER_NOT_REQUIRED,
                'is_penalty' => true,
                'penalty_key' => $key,
            ]);

            PortalNotification::notifyEmployee($this, 'penalty', 'Casual Leave deducted',
                "{$leaveDays} Casual Leave was deducted: {$penaltyLeave->reason}. If any of these was justified, request a waiver on the Penalty & Waivers page.",
                'penalty', "penalty:created:{$penaltyLeave->id}");

            $balance -= $leaveDays;
            $result['created']++;
        }

        return $result;
    }
}
