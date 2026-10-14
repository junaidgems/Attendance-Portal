{{-- Audit log table + pagination. Rendered on page load and returned alone for AJAX filter / page changes. --}}
<div class="table-wrap" style="margin-top:16px;">
    <table class="audit-table">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Actor</th>
                <th>Action</th>
                <th>Detail</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="timestamp">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i') }}</td>
                    <td><span class="actor-name">{{ $log->actor_name }}</span></td>
                    <td><span class="action-badge {{ $log->category }}">{{ $log->action }}</span></td>
                    <td>{{ $log->detail }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">
                        <div style="text-align:center; padding:30px 20px; color:var(--text-muted);">
                            <i class="bi bi-inbox" style="font-size:36px; opacity:0.3; display:block; margin-bottom:8px;"></i>
                            <p>No audit records found.</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($logs->total() > 0)
    <div class="audit-pagination">
        <span class="audit-pagination-info">
            Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ $logs->total() }}
        </span>
        @if ($logs->hasPages())
            {{-- Single Bootstrap pagination row (the default Tailwind template renders a second, unstyled copy) --}}
            {{ $logs->onEachSide(1)->links('pagination::bootstrap-4') }}
        @endif
    </div>
@endif
