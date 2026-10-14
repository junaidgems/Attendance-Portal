@extends('attendence.layouts.app')

@section('title', 'Audit Log')
@section('page-title', 'Audit Log')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/audit-log.css') }}">
@endpush

@section('content')

<div class="row">
    <div class="col-12">
        <div class="glass-card">
            <div class="section-header">
                <div class="section-title"><i class="bi bi-journal-text"></i> Audit Log</div>
            </div>

            <!-- Filter Bar -->
            <form method="GET" action="{{ route('admin.audit.log') }}" id="auditFilterForm">
                <div class="filter-bar">
                    <div class="form-group" style="flex:2;">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Action, person or detail" value="{{ $search }}">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-control">
                            <option value="all" @selected($category === 'all')>All activity</option>
                            @foreach ($categories as $value => $label)
                                <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn-primary-glass"><i class="bi bi-funnel"></i> Filter</button>
                        <a href="{{ route('admin.audit.log') }}" class="btn-outline-glass" id="auditClear"><i class="bi bi-arrow-counterclockwise"></i> Clear</a>
                        <a href="{{ route('admin.audit.log.export', request()->only('search', 'category')) }}" class="btn-outline-glass" id="auditExport"><i class="bi bi-download"></i> Export CSV</a>
                    </div>
                </div>
            </form>

            <!-- Table + pagination (replaced via AJAX) -->
            <div id="auditLogResults">
                @include('attendence.partials.audit-log-results')
            </div>

        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    (function () {
        const results = document.getElementById('auditLogResults');
        const form = document.getElementById('auditFilterForm');
        const exportLink = document.getElementById('auditExport');
        const exportBase = @json(route('admin.audit.log.export'));

        // Load a page of results without reloading the whole page
        async function load(url, push = true) {
            results.classList.add('is-loading');
            try {
                const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                if (!response.ok) {
                    throw new Error(response.status);
                }
                results.innerHTML = await response.text();

                const query = new URL(url, window.location.origin).searchParams;
                form.querySelector('[name=search]').value = query.get('search') || '';
                form.querySelector('[name=category]').value = query.get('category') || 'all';
                query.delete('page');
                exportLink.href = exportBase + (query.toString() ? '?' + query.toString() : '');

                if (push) {
                    history.pushState({ auditUrl: url }, '', url);
                }
                results.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (e) {
                window.location.href = url; // fall back to a normal page load
            } finally {
                results.classList.remove('is-loading');
            }
        }

        // Pagination links
        results.addEventListener('click', function (e) {
            const link = e.target.closest('.pagination a');
            if (link) {
                e.preventDefault();
                load(link.href);
            }
        });

        // Filter
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const params = new URLSearchParams(new FormData(form));
            load(form.action + '?' + params.toString());
        });

        // Clear
        document.getElementById('auditClear').addEventListener('click', function (e) {
            e.preventDefault();
            form.reset();
            form.querySelector('[name=search]').value = '';
            form.querySelector('[name=category]').value = 'all';
            load(this.href);
        });

        // Browser back / forward
        window.addEventListener('popstate', function () {
            load(window.location.href, false);
        });
    })();
</script>
@endpush
