<?php

namespace App\Http\Controllers\Director\Concerns;

use App\Models\CorrectionRequest;
use App\Models\DeductionWaiver;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\WfhRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Requests of the Managers who report to the logged-in Director (the Director decides them finally).
 */
trait ManagerRequests
{
    protected function director(): ?Employee
    {
        return Auth::user()->employee;
    }

    /**
     * IDs of the Managers who report to the logged-in Director.
     *
     * @return Collection<int, int>
     */
    protected function managerIds(): Collection
    {
        $director = $this->director();

        if (! $director) {
            return collect();
        }

        return Employee::where('reports_to_id', $director->id)
            ->whereHas('role', fn ($q) => $q->where('name', 'manager'))
            ->pluck('id');
    }

    /**
     * Pending requests per type, each as a row for the approvals list.
     *
     * @return array{corrections: Collection, leaves: Collection, waivers: Collection, wfh: Collection}
     */
    protected function pendingManagerRequests(): array
    {
        $managerIds = $this->managerIds();

        $corrections = CorrectionRequest::with('employee')
            ->whereIn('employee_id', $managerIds)
            ->awaitingManager()
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($c) => $this->requestRow($c, 'correction', Carbon::parse($c->date)->format('d M Y'), $c->typeLabel(), [
                'extra' => 'Current: '.($c->current_record ?: '—').' → Requested: '.($c->requested_correction ?: '—'),
            ]));

        $leaves = LeaveRequest::with(['employee', 'leaveType'])
            ->whereIn('employee_id', $managerIds)
            ->awaitingManager()
            ->orderBy('from_date', 'desc')
            ->get()
            ->map(fn ($l) => $this->requestRow($l, 'leave', $this->dateRange($l->from_date, $l->to_date),
                ($l->leaveType->name ?? 'Leave').' · '.($l->is_half_day
                    ? 'Half day ('.($l->half_day_session === 'second_half' ? 'second half' : 'first half').')'
                    : $l->days.' '.($l->days == 1 ? 'day' : 'days')), [
                        'document_url' => $l->document_path ? route('leave.document', $l->id) : null,
                    ]));

        $waivers = DeductionWaiver::with('employee')
            ->whereIn('employee_id', $managerIds)
            ->where('status', 'pending')
            ->where('lm_status', 'pending')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($w) => $this->requestRow($w, 'waiver', Carbon::parse($w->date)->format('d M Y'), DeductionWaiver::deductionLabel($w->type)));

        $wfh = WfhRequest::with('employee')
            ->whereIn('employee_id', $managerIds)
            ->awaitingManager()
            ->orderBy('from_date')
            ->get()
            ->map(fn ($w) => $this->requestRow($w, 'wfh', $w->dateLabel(), 'Work From Home · '.$w->days.' '.($w->days == 1 ? 'day' : 'days')));

        return compact('corrections', 'leaves', 'waivers', 'wfh');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function requestRow(object $request, string $kind, string $date, string $detail, array $extra = []): array
    {
        $employee = $request->employee;

        return $extra + [
            'id' => $request->id,
            'kind' => $kind,
            'employee' => $employee?->full_name ?? 'Unknown Employee',
            'avatar' => $employee ? strtoupper(substr($employee->first_name, 0, 1).substr($employee->last_name, 0, 1)) : 'NA',
            'date' => $date,
            'detail' => $detail,
            'extra' => null,
            'reason' => $request->reason,
        ];
    }

    private function dateRange(mixed $from, mixed $to): string
    {
        $from = Carbon::parse($from);
        $to = Carbon::parse($to);

        return $from->isSameDay($to)
            ? $from->format('d M Y')
            : $from->format('d M').' – '.$to->format('d M Y');
    }
}
