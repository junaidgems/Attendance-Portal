<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LeaveType extends Model
{
    protected $fillable = [
        'name',
        'days_per_year',
        'color',
        // Leave policy rules (empty = no limit)
        'max_days_per_month',
        'min_days_per_request',
        'requires_document',
        // Document needed only from this many days per request (empty = always)
        'document_required_from_days',
    ];

    protected function casts(): array
    {
        return [
            'max_days_per_month' => 'float',
            'min_days_per_request' => 'float',
            'requires_document' => 'boolean',
            'document_required_from_days' => 'float',
        ];
    }

    /**
     * Leave types the portal looks up by name (penalty deductions use Casual Leave, the dashboards show
     * these balances), so they can be edited (days, colour) but not renamed or deleted.
     */
    public const SYSTEM_TYPES = ['Annual Leave', 'Sick Leave', 'Casual Leave'];

    public function isSystemType(): bool
    {
        return in_array($this->getOriginal('name') ?? $this->name, self::SYSTEM_TYPES, true);
    }

    /**
     * The policy rules of this leave type as short text, e.g. "Max 2 requests per month · Doctor's slip required".
     */
    public function rulesSummary(): string
    {
        return implode(' · ', array_filter([
            $this->max_days_per_month ? 'Max '.self::formatDays($this->max_days_per_month).' day(s) per month' : null,
            $this->min_days_per_request ? 'Minimum '.self::formatDays($this->min_days_per_request).' day(s) per request' : null,
            $this->requires_document
                ? 'Supporting document (e.g. doctor\'s slip) required'.($this->document_required_from_days > 1 ? ' for '.self::formatDays($this->document_required_from_days).' or more days' : '')
                : null,
        ]));
    }

    /**
     * Whether a request of this many days needs a supporting document, e.g. Sick Leave: 1 day no, 2+ days yes.
     */
    public function needsDocument(float $days): bool
    {
        return $this->requires_document && $days >= ($this->document_required_from_days ?: 0);
    }

    /**
     * Leave year start (month-day) when the Attendance Policy has none: 26 June → 25 June.
     */
    public const DEFAULT_LEAVE_YEAR_START = '06-26';

    /**
     * The leave year that contains the date, e.g. 26 Jun 2026 – 25 Jun 2027. Balances are counted per leave year,
     * so every employee gets the full days_per_year again when a new leave year starts.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function leaveYear(Carbon|string|null $date = null, ?object $settings = null): array
    {
        $settings ??= DB::table('attendance_settings')->first();
        $date = Carbon::parse($date ?? now())->startOfDay();

        [$month, $day] = self::parseYearStart($settings->leave_year_start ?? null);

        $start = Carbon::create($date->year, $month, min($day, Carbon::create($date->year, $month, 1)->daysInMonth));
        if ($start->gt($date)) {
            $start = Carbon::create($date->year - 1, $month, min($day, Carbon::create($date->year - 1, $month, 1)->daysInMonth));
        }

        return [$start, $start->copy()->addYear()->subDay()];
    }

    /**
     * "06-26" → [6, 26]; anything invalid falls back to the default start.
     *
     * @return array{0: int, 1: int}
     */
    public static function parseYearStart(?string $value): array
    {
        if ($value && preg_match('/^(\d{1,2})-(\d{1,2})$/', trim($value), $m) && checkdate((int) $m[1], (int) $m[2], 2024)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return array_map('intval', explode('-', self::DEFAULT_LEAVE_YEAR_START));
    }

    /**
     * 2.0 → "2", 1.5 → "1.5".
     */
    public static function formatDays(float $days): string
    {
        return rtrim(rtrim(number_format($days, 1), '0'), '.');
    }

    public function requests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
