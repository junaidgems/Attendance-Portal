<?php

namespace App\Http\Controllers\LineManager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employee\PenaltyControllers;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PenaltyController extends Controller
{
    private function currentManager()
    {
        return Employee::where('user_id', Auth::id())->firstOrFail();
    }

    public function index(Request $request)
    {
        $manager = $this->currentManager();
        $team = Employee::where('reports_to_id', $manager->id)
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get();

        $selectedId = $request->input('employee', 'self');
        $target = $selectedId === 'self' ? null : $team->firstWhere('id', (int) $selectedId);

        if (! $target) {
            $target = $manager;
            $selectedId = 'self';
        }

        $target->load(['shift', 'gracePolicy']);
        $settings = DB::table('attendance_settings')->first();

        return view('attendence.pages.line-manager.penalty-waivers', PenaltyControllers::penaltyViewData($target, $settings, (int) $request->input('cycle', 0)) + [
            'team' => $team,
            'selectedId' => $selectedId,
            'isSelf' => $selectedId === 'self',
            'targetName' => $target->full_name,
        ]);
    }

    /**
     * The Line Manager's own waiver request — goes straight to Admin.
     */
    public function storeWaiver(Request $request)
    {
        $request->validate([
            'date' => 'required|date|before_or_equal:today',
            'type' => 'required|in:late,early_out,half_day,absent',
            'reason' => 'required|string|max:500',
        ]);

        PenaltyControllers::submitWaiver($this->currentManager(), $request->date, $request->type, $request->reason);

        return redirect()->back()->with('success', 'Waiver request submitted!');
    }
}
