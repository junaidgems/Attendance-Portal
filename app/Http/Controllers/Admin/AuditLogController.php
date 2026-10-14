<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Category key => label for the filter.
     */
    private const CATEGORIES = [
        'signin' => 'Sign In',
        'sync' => 'Biometric Sync',
        'attendance' => 'Attendance',
        'leave' => 'Leave',
        'penalty' => 'Penalty',
        'cycle' => 'Attendance Policy',
        'holiday' => 'Holiday',
        'lock' => 'Period Lock',
    ];

    private const PER_PAGE = 25;

    public function index(Request $request)
    {
        $search = $request->input('search');
        $category = $request->input('category', 'all');

        $logs = $this->filtered($request)->paginate(self::PER_PAGE)->withQueryString();

        // AJAX (filter / page change): only the table + pagination
        if ($request->ajax()) {
            return view('attendence.partials.audit-log-results', compact('logs'));
        }

        return view('attendence.pages.admin.audit-log', [
            'logs' => $logs,
            'search' => $search,
            'category' => $category,
            'categories' => self::CATEGORIES,
        ]);
    }

    /**
     * CSV of the currently filtered log (same search / category as the page).
     */
    public function export(Request $request)
    {
        $logs = $this->filtered($request)->get();

        $filename = 'audit-log-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Timestamp', 'Actor', 'Category', 'Action', 'Detail']);
            foreach ($logs as $log) {
                fputcsv($file, [$log->created_at, $log->actor_name, self::CATEGORIES[$log->category] ?? $log->category, $log->action, $log->detail]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function filtered(Request $request): Builder
    {
        $search = trim((string) $request->input('search'));
        $category = $request->input('category', 'all');

        return AuditLog::query()
            ->when($category !== 'all', fn ($q) => $q->where('category', $category))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('actor_name', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('detail', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
