<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

/**
 * Attendance correction. Once finally approved it is applied to the day's attendance record
 * (device_attendance), which is then locked against machine sync. Rejecting it later restores
 * the original punches.
 */
class CorrectionRequest extends Model
{
    /**
     * Correction type => label. The first four change the attendance times when approved.
     */
    public const TYPES = [
        'forgot-check-out' => 'Forgot to check out',
        'forgot-check-in' => 'Forgot to check in',
        'official-duty' => 'Official duty (left for office work)',
        'wrong-time' => 'Wrong time recorded',
    ];

    /**
     * Older free-text types (before times were captured); shown but not applied automatically.
     */
    private const LEGACY_TYPES = [
        'missing-punch' => 'Missing punch',
        'late' => 'Late arrival',
        'early-out' => 'Early out',
        'other' => 'Other',
    ];

    protected $fillable = [
        'employee_id', 'date', 'change_type', 'current_record',
        'requested_correction', 'reason', 'status', 'manager_note', 'reviewed_at', 'reviewed_by',
        'requested_check_in', 'requested_check_out', 'applied_at',
        // Line Manager / Manager recommendation (Admin makes the final decision)
        'lm_status', 'lm_note', 'lm_reviewed_by', 'lm_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The Line Manager / Manager who gave the recommendation.
     */
    public function lmReviewer()
    {
        return $this->belongsTo(Employee::class, 'lm_reviewed_by');
    }

    /**
     * Waiting for the Line Manager / Manager's recommendation.
     */
    public function scopeAwaitingManager($query)
    {
        return $query->where('status', 'pending')->where('lm_status', 'pending');
    }

    public function isAwaitingManager(): bool
    {
        return $this->status === 'pending' && $this->lm_status === 'pending';
    }

    /**
     * A Manager's request already decided by their Director: that decision is final, Admin cannot change it.
     * (While it is still pending, Admin may decide it too — whoever decides first.)
     */
    public function isDirectorDecision(): bool
    {
        return (bool) $this->lmReviewer?->isDirector();
    }

    /**
     * A Line Manager's or Manager's own request (see LeaveRequest::isTeamLeadRequest).
     */
    public function isTeamLeadRequest(): bool
    {
        return (bool) $this->employee?->isTeamLead();
    }

    /**
     * "Awaiting Furqan Iqbal (Line Manager)" / "Awaiting Admin" / "Approved" …
     */
    public function stageLabel(): string
    {
        if ($this->status !== 'pending') {
            return ucfirst($this->status);
        }

        if ($this->isAwaitingManager()) {
            $reviewer = $this->employee ? LeaveRequest::reviewingManagerFor($this->employee) : null;

            // No Line Manager / Manager (e.g. older requests of someone reporting to nobody): Admin decides
            return $reviewer ? "Awaiting {$reviewer->full_name} ({$reviewer->roleLabel()})" : 'Awaiting Admin';
        }

        return 'Awaiting Admin';
    }

    /**
     * Validation rules for a new correction request (used by the Employee, Line Manager and Manager forms).
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'date' => 'required|date|before_or_equal:today',
            'change_type' => 'required|in:'.implode(',', array_keys(self::TYPES)),
            'requested_check_in' => 'nullable|required_if:change_type,forgot-check-in|date_format:H:i',
            'requested_check_out' => 'nullable|required_if:change_type,forgot-check-out,official-duty|date_format:H:i',
            'reason' => 'required|string|max:500',
        ];
    }

    /**
     * Create a request for an employee, recording the machine's current punches for that day.
     *
     * @param  array{date: string, change_type: string, requested_check_in?: ?string, requested_check_out?: ?string, reason: string}  $data
     */
    public static function submit(Employee $employee, array $data): self
    {
        // One open request per day, and a rejected correction cannot be requested again for the same day and type
        $existing = self::where('employee_id', $employee->id)
            ->whereDate('date', $data['date'])
            ->where(fn ($q) => $q->whereIn('status', ['pending', 'approved'])
                ->orWhere(fn ($q) => $q->where('status', 'rejected')->where('change_type', $data['change_type'])))
            ->latest('id')
            ->first();

        if ($existing) {
            $day = Carbon::parse($data['date'])->format('d M Y');

            throw new HttpResponseException(redirect()->back()->withInput()->with('error', match ($existing->status) {
                'pending' => "You already have a pending correction request for {$day}. Please wait for it to be reviewed.",
                'approved' => "Your correction request for {$day} has already been approved.",
                default => "Your \"{$existing->typeLabel()}\" correction for {$day} was rejected and cannot be requested again.",
            }));
        }

        $record = $employee->zkteco_uid
            ? DB::table('device_attendance')->where('uid', $employee->zkteco_uid)->where('date', $data['date'])->first()
            : null;

        $time = fn (?string $t) => $t ? Carbon::parse($t)->format('H:i') : '—';
        $in = $data['requested_check_in'] ?? null;
        $out = $data['requested_check_out'] ?? null;

        return self::create([
            'employee_id' => $employee->id,
            'date' => $data['date'],
            'change_type' => $data['change_type'],
            'current_record' => $record ? $time($record->check_in).' - '.$time($record->check_out) : 'No punch',
            'requested_check_in' => $in,
            'requested_check_out' => $out,
            'requested_correction' => trim(($in ? "In {$in}" : '').($in && $out ? ', ' : '').($out ? "Out {$out}" : '')),
            'reason' => $data['reason'],
            'status' => 'pending',
            // Line Manager / Manager recommends first; without one it goes straight to Admin
            'lm_status' => LeaveRequest::reviewingManagerFor($employee) ? 'pending' : 'not_required',
        ]);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->change_type] ?? self::LEGACY_TYPES[$this->change_type] ?? ucfirst(str_replace('-', ' ', (string) $this->change_type));
    }

    /**
     * "In 09:30 → Out 17:30" style summary of the requested change.
     */
    public function requestedLabel(): string
    {
        return $this->requested_correction ?: '—';
    }

    /**
     * Apply an approved correction to the attendance record and lock it against machine sync.
     * Returns false when the correction has no times to apply (older free-text requests).
     */
    public function applyToAttendance(string $approvedBy): bool
    {
        $employee = $this->employee?->loadMissing(['shift', 'gracePolicy']);

        if (! $employee?->zkteco_uid || (! $this->requested_check_in && ! $this->requested_check_out && $this->change_type !== 'official-duty')) {
            return false;
        }

        $date = Carbon::parse($this->date)->toDateString();
        $settings = DB::table('attendance_settings')->first();
        $record = DB::table('device_attendance')->where('uid', $employee->zkteco_uid)->where('date', $date)->first();

        // Keep the machine's original punches only the first time the day is adjusted
        $originalIn = $record?->adjustment ? $record->original_check_in : $record?->check_in;
        $originalOut = $record?->adjustment ? $record->original_check_out : $record?->check_out;

        $checkIn = $this->requested_check_in ?: $record?->check_in;
        $checkOut = $this->requested_check_out ?: $record?->check_out;

        if ($this->change_type === 'official-duty') {
            // Left for office work: the day counts until at least the end of the shift
            $shiftEnd = $employee->shiftTimesOn($date, $settings)['end'];
            $checkOut = $checkOut && $checkOut > $shiftEnd ? $checkOut : $shiftEnd;
        }

        if (! $checkIn) {
            return false;
        }

        $checkIn = Carbon::parse($checkIn)->format('H:i:s');
        $checkOut = $checkOut ? Carbon::parse($checkOut)->format('H:i:s') : null;
        $punctuality = Employee::punctuality($employee, $date, $checkIn, $checkOut, $settings);

        DB::table('device_attendance')->updateOrInsert(
            ['uid' => $employee->zkteco_uid, 'date' => $date],
            [
                'name' => $record->name ?? $employee->full_name,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'status' => $punctuality['late_minutes'] > 0 ? 'Late' : 'On Time',
                'late_minutes' => $punctuality['late_minutes'],
                'early_out_minutes' => $punctuality['early_out_minutes'],
                'adjustment' => $this->change_type === 'official-duty' ? 'official_duty' : 'correction',
                'adjustment_note' => $this->typeLabel()." (request #{$this->id})",
                'adjusted_by' => $approvedBy,
                'adjusted_at' => now(),
                'original_check_in' => $originalIn,
                'original_check_out' => $originalOut,
                'created_at' => $record->created_at ?? now(),
                'updated_at' => now(),
            ]
        );

        $this->update(['applied_at' => now()]);

        return true;
    }

    /**
     * Undo an applied correction (e.g. Admin changed it to rejected): restore the machine's punches.
     */
    public function revertAttendance(): void
    {
        if (! $this->applied_at || ! $this->employee?->zkteco_uid) {
            return;
        }

        $date = Carbon::parse($this->date)->toDateString();
        $record = DB::table('device_attendance')->where('uid', $this->employee->zkteco_uid)->where('date', $date)->first();

        if ($record && $record->adjustment && str_contains((string) $record->adjustment_note, "#{$this->id})")) {
            $employee = $this->employee->loadMissing(['shift', 'gracePolicy']);
            $settings = DB::table('attendance_settings')->first();
            $punctuality = Employee::punctuality($employee, $date, $record->original_check_in, $record->original_check_out, $settings);

            DB::table('device_attendance')->where('id', $record->id)->update([
                'check_in' => $record->original_check_in,
                'check_out' => $record->original_check_out,
                'status' => $punctuality['late_minutes'] > 0 ? 'Late' : 'On Time',
                'late_minutes' => $punctuality['late_minutes'],
                'early_out_minutes' => $punctuality['early_out_minutes'],
                'adjustment' => null,
                'adjustment_note' => null,
                'adjusted_by' => null,
                'adjusted_at' => null,
                'original_check_in' => null,
                'original_check_out' => null,
                'updated_at' => now(),
            ]);
        }

        $this->update(['applied_at' => null]);
    }

    /**
     * Apply or undo the correction after a final status change, then refresh penalty leaves for that cycle.
     */
    public function syncAttendanceWithStatus(string $reviewer): void
    {
        $this->status === 'approved' ? $this->applyToAttendance($reviewer) : $this->revertAttendance();

        $this->employee?->loadMissing(['shift', 'gracePolicy'])->syncPenaltyLeaves($this->date);
    }
}
