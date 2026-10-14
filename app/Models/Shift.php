<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'saturday_start_time',
        'saturday_end_time',
        'is_active',
    ];

    /**
     * Shift timings for a date: Saturday uses the Saturday timings when set, other days the normal timings.
     *
     * @return array{start: string, end: string}
     */
    public function timesOn(Carbon|string $date): array
    {
        $isSaturday = Carbon::parse($date)->isSaturday();

        if ($isSaturday && $this->saturday_start_time && $this->saturday_end_time) {
            return ['start' => $this->saturday_start_time, 'end' => $this->saturday_end_time];
        }

        return ['start' => $this->start_time, 'end' => $this->end_time];
    }

    public function hasSaturdayTimings(): bool
    {
        return $this->saturday_start_time && $this->saturday_end_time;
    }
}
