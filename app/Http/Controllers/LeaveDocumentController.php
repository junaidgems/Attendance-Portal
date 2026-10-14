<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveDocumentController extends Controller
{
    /**
     * Open the supporting document of a leave request (e.g. a doctor's slip).
     * Only the employee, their Line Manager / Manager and Admin may see it.
     */
    public function show(LeaveRequest $leaveRequest): StreamedResponse
    {
        $user = Auth::user();
        $viewer = $user->employee;
        $applicant = $leaveRequest->employee;

        $canView = $user->hasRole('administrator')
            || ($viewer && $applicant && (
                $viewer->id === $applicant->id
                || $viewer->id === $leaveRequest->manager_id
                || $viewer->id === $applicant->reports_to_id
            ));

        abort_unless($canView, 403);
        abort_unless($leaveRequest->document_path && Storage::disk('local')->exists($leaveRequest->document_path), 404, 'Document not found.');

        // Inline, so a PDF or image opens in the browser tab
        return Storage::disk('local')->response($leaveRequest->document_path);
    }
}
