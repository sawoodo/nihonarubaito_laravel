@extends('layouts.admin')

@section('title', 'Expiring Jobs')

@section('content')
<div id="page-wrapper" style="min-height: 800px">

    {{-- ── Page Header ── --}}
    <div class="row">
        <div class="col-lg-12">
            <h3 class="page-header" style="margin-bottom: 5px">
                <i class="fa fa-clock-o fa-fw"></i> Expiring Jobs Manager
                <div class="pull-right" style="font-size: 14px; margin-top: 6px">
                    <a href="{{ url('admin/analytics') }}" class="btn btn-sm btn-default">
                        <i class="fa fa-arrow-left"></i> Analytics
                    </a>
                </div>
            </h3>
        </div>
    </div>

    {{-- ── Summary Cards ── --}}
    <div class="row">
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="panel summary-card {{ $filter === '0' ? 'panel-danger' : 'panel-default' }}" data-filter="0" style="cursor: pointer">
                <div class="panel-body text-center" style="padding: 12px 8px">
                    <div style="font-size: 28px; font-weight: 700" id="count-today">{{ $expiringToday }}</div>
                    <div style="font-size: 12px; color: #888">Expiring Today</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="panel summary-card {{ $filter === '1' ? 'panel-warning' : 'panel-default' }}" data-filter="1" style="cursor: pointer">
                <div class="panel-body text-center" style="padding: 12px 8px">
                    <div style="font-size: 28px; font-weight: 700" id="count-tomorrow">{{ $expiringTomorrow }}</div>
                    <div style="font-size: 12px; color: #888">Tomorrow</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="panel summary-card {{ $filter === '3' ? 'panel-info' : 'panel-default' }}" data-filter="3" style="cursor: pointer">
                <div class="panel-body text-center" style="padding: 12px 8px">
                    <div style="font-size: 28px; font-weight: 700" id="count-3days">{{ $expiring3Days }}</div>
                    <div style="font-size: 12px; color: #888">Next 3 Days</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="panel summary-card {{ $filter === '7' ? 'panel-primary' : 'panel-default' }}" data-filter="7" style="cursor: pointer">
                <div class="panel-body text-center" style="padding: 12px 8px">
                    <div style="font-size: 28px; font-weight: 700" id="count-7days">{{ $expiring7Days }}</div>
                    <div style="font-size: 12px; color: #888">Next 7 Days</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="panel summary-card {{ $filter === '14' ? 'panel-success' : 'panel-default' }}" data-filter="14" style="cursor: pointer">
                <div class="panel-body text-center" style="padding: 12px 8px">
                    <div style="font-size: 28px; font-weight: 700" id="count-14days">{{ $expiring14Days }}</div>
                    <div style="font-size: 12px; color: #888">Next 14 Days</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Filter Bar ── --}}
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default" style="margin-bottom: 10px">
                <div class="panel-body" style="padding: 10px 15px">
                    <form class="form-inline" method="GET" action="{{ url('admin/analytics/expiring-jobs') }}" id="filterForm">
                        <div class="form-group" style="margin-right: 8px">
                            <select name="filter" id="filterDays" class="form-control input-sm">
                                <option value="0" {{ $filter === '0' ? 'selected' : '' }}>Today</option>
                                <option value="1" {{ $filter === '1' ? 'selected' : '' }}>Tomorrow</option>
                                <option value="3" {{ $filter === '3' ? 'selected' : '' }}>Next 3 Days</option>
                                <option value="7" {{ $filter === '7' ? 'selected' : '' }}>Next 7 Days</option>
                                <option value="14" {{ $filter === '14' ? 'selected' : '' }}>Next 14 Days</option>
                                <option value="30" {{ $filter === '30' ? 'selected' : '' }}>Next 30 Days</option>
                                <option value="custom" {{ $filter === 'custom' ? 'selected' : '' }}>Custom Range</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-right: 8px; {{ $filter !== 'custom' ? 'display:none' : '' }}" id="customRange">
                            <input type="date" name="from" class="form-control input-sm" value="{{ request('from', now()->format('Y-m-d')) }}">
                            <span style="margin: 0 4px">to</span>
                            <input type="date" name="to" class="form-control input-sm" value="{{ request('to', now()->addDays(7)->format('Y-m-d')) }}">
                        </div>
                        <div class="form-group" style="margin-right: 8px">
                            <select name="prefecture_id" class="form-control input-sm">
                                <option value="">All Prefectures</option>
                                @foreach ($prefectures as $pref)
                                    <option value="{{ $pref->id }}" {{ $prefectureId == $pref->id ? 'selected' : '' }}>{{ $pref->english }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-right: 8px">
                            <select name="category_id" class="form-control input-sm">
                                <option value="">All Categories</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ $categoryId == $cat->id ? 'selected' : '' }}>{{ $cat->english }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-right: 8px">
                            <input type="text" name="search" class="form-control input-sm" placeholder="Search title..." value="{{ $search }}" style="width: 200px">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-filter"></i> Filter</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Bulk Actions ── --}}
    <div class="row">
        <div class="col-lg-12">
            <div style="margin-bottom: 10px">
                <button class="btn btn-sm btn-success bulk-extend" data-days="7" disabled><i class="fa fa-plus"></i> Extend Selected +7 Days</button>
                <button class="btn btn-sm btn-info bulk-extend" data-days="30" disabled><i class="fa fa-plus"></i> Extend Selected +30 Days</button>
                <button class="btn btn-sm btn-danger" id="bulkTrash" disabled><i class="fa fa-trash"></i> Trash Selected</button>
                <span class="text-muted" style="margin-left: 10px" id="selectedCount">0 selected</span>
                <span class="pull-right text-muted">{{ count($jobs) }} jobs shown</span>
            </div>
        </div>
    </div>

    {{-- Toast notification --}}
    <div id="toast" style="display:none; position:fixed; bottom:20px; right:20px; z-index:9999; padding:12px 20px; border-radius:6px; color:#fff; font-size:13px; box-shadow:0 4px 12px rgba(0,0,0,.25); max-width:400px"></div>

    {{-- ── Main Table ── --}}
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-body" style="padding: 0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-condensed" id="expiringTable" style="margin-bottom: 0; font-size: 13px">
                            <thead>
                                <tr style="background: #f5f5f5">
                                    <th style="width: 30px; padding: 8px 6px"><input type="checkbox" id="selectAll"></th>
                                    <th style="width: 70px; padding: 8px 6px; cursor: pointer" class="sortable" data-sort="job_no">Job # <i class="fa fa-sort"></i></th>
                                    <th style="min-width: 250px; padding: 8px 6px; cursor: pointer" class="sortable" data-sort="title">Title <i class="fa fa-sort"></i></th>
                                    <th style="width: 100px; padding: 8px 6px; cursor: pointer" class="sortable" data-sort="prefecture">Prefecture <i class="fa fa-sort"></i></th>
                                    <th style="width: 100px; padding: 8px 6px">Area</th>
                                    <th style="width: 100px; padding: 8px 6px; cursor: pointer" class="sortable" data-sort="category">Category <i class="fa fa-sort"></i></th>
                                    <th style="width: 80px; padding: 8px 6px">Wage</th>
                                    <th style="width: 120px; padding: 8px 6px; cursor: pointer" class="sortable" data-sort="delete_at">Delete At <i class="fa fa-sort"></i></th>
                                    <th style="width: 65px; padding: 8px 6px; cursor: pointer; text-align: center" class="sortable" data-sort="days_left">Days <i class="fa fa-sort"></i></th>
                                    <th style="width: 110px; padding: 8px 6px">Source</th>
                                    <th style="width: 240px; padding: 8px 6px; text-align: center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($jobs as $job)
                                    @php
                                        $daysLeft = max(0, (int) \Carbon\Carbon::parse($job->delete_at)->startOfDay()->diffInDays(now()->startOfDay(), false) * -1);
                                        if (\Carbon\Carbon::parse($job->delete_at)->startOfDay()->lte(now()->startOfDay())) {
                                            $daysLeft = 0;
                                        } else {
                                            $daysLeft = (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($job->delete_at)->startOfDay());
                                        }
                                        $domain = '';
                                        if ($job->apply_link) {
                                            $parsed = parse_url($job->apply_link, PHP_URL_HOST);
                                            $domain = $parsed ? preg_replace('/^www\./', '', $parsed) : '';
                                        }
                                        if ($daysLeft === 0) { $dayColor = '#e74c3c'; $dayBg = '#fde8e8'; }
                                        elseif ($daysLeft <= 2) { $dayColor = '#e67e22'; $dayBg = '#fef3e2'; }
                                        elseif ($daysLeft <= 7) { $dayColor = '#f39c12'; $dayBg = '#fef9e7'; }
                                        else { $dayColor = '#27ae60'; $dayBg = '#eafaf1'; }
                                    @endphp
                                    <tr data-id="{{ $job->id }}" data-job-no="{{ $job->job_no }}">
                                        <td style="padding: 6px"><input type="checkbox" class="row-check" value="{{ $job->id }}"></td>
                                        <td style="padding: 6px">{{ $job->job_no }}</td>
                                        <td style="padding: 6px">
                                            <a href="{{ url('jobs/' . $job->job_no . '/detail') }}" target="_blank" title="{{ $job->title }}" style="color: #333">
                                                {{ \Illuminate\Support\Str::limit($job->title, 50) }}
                                            </a>
                                        </td>
                                        <td style="padding: 6px">{{ $job->prefecture }}</td>
                                        <td style="padding: 6px">{{ $job->area ?: '-' }}</td>
                                        <td style="padding: 6px"><span class="label label-default">{{ $job->category }}</span></td>
                                        <td style="padding: 6px">{{ $job->wage ? \Illuminate\Support\Str::limit($job->wage, 15) : '-' }}</td>
                                        <td style="padding: 6px" class="delete-at-cell" data-date="{{ \Carbon\Carbon::parse($job->delete_at)->format('Y-m-d') }}">
                                            <span class="date-display" style="cursor: pointer; border-bottom: 1px dashed #999" title="Click to edit">
                                                {{ \Carbon\Carbon::parse($job->delete_at)->format('d M Y') }}
                                            </span>
                                            <input type="date" class="date-edit form-control input-sm" value="{{ \Carbon\Carbon::parse($job->delete_at)->format('Y-m-d') }}" style="display: none; width: 140px">
                                        </td>
                                        <td style="padding: 6px; text-align: center">
                                            <span class="days-left-badge" style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-weight: 600; font-size: 12px; color: {{ $dayColor }}; background: {{ $dayBg }}">
                                                {{ $daysLeft }}d
                                            </span>
                                        </td>
                                        <td style="padding: 6px">
                                            @if ($job->apply_link)
                                                <a href="{{ $job->apply_link }}" target="_blank" rel="noopener" class="label label-info" style="font-size: 11px" title="{{ $job->apply_link }}">
                                                    {{ \Illuminate\Support\Str::limit($domain, 18) }} <i class="fa fa-external-link"></i>
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td style="padding: 6px; text-align: center; white-space: nowrap">
                                            <button class="btn btn-xs btn-success extend-btn" data-days="7" title="+7 days">+7d</button>
                                            <button class="btn btn-xs btn-info extend-btn" data-days="30" title="+30 days">+30d</button>
                                            <button class="btn btn-xs btn-warning extend-btn" data-days="60" title="+60 days">+60d</button>
                                            <a href="{{ url('admin/jobs/' . $job->job_no . '/edit') }}" class="btn btn-xs btn-default" title="Edit"><i class="fa fa-pencil"></i></a>
                                            <button class="btn btn-xs btn-danger trash-btn" title="Trash"><i class="fa fa-trash"></i></button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="11" class="text-center text-muted" style="padding: 30px">No expiring jobs found for this filter.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('page-styles')
<style>
    .summary-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.15); transition: box-shadow .2s; }
    .date-edit:focus { box-shadow: 0 0 4px rgba(52,152,219,.5); }
    tr.flash-green { animation: flashGreen .8s ease; }
    @keyframes flashGreen {
        0%   { background-color: #d4edda; }
        100% { background-color: transparent; }
    }
    tr.flash-red { animation: flashRed .8s ease; }
    @keyframes flashRed {
        0%   { background-color: #f8d7da; }
        100% { background-color: transparent; }
    }
    .sortable:hover { background: #e8e8e8; }
    .sortable .fa-sort-asc, .sortable .fa-sort-desc { color: #337ab7; }
</style>
@endpush

@push('page-scripts')
<script>
(function() {
    var baseUrl = ($('#base_url').val() || '').replace(/\/+$/, '');
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    // ── Filter Config ──
    var currentFilter = @json($filter);
    var filterCutoffDate; // max date that fits the current filter
    (function() {
        var today = new Date(); today.setHours(0,0,0,0);
        if (currentFilter === 'custom') {
            var toVal = $('input[name="to"]').val();
            filterCutoffDate = toVal ? new Date(toVal + 'T00:00:00') : null;
        } else {
            var days = parseInt(currentFilter);
            if (isNaN(days)) days = 7;
            var cutoff = new Date(today);
            cutoff.setDate(cutoff.getDate() + days);
            filterCutoffDate = cutoff;
        }
    })();

    function isWithinFilter(dateStr) {
        if (!filterCutoffDate) return true; // custom with no to → keep all
        var d = new Date(dateStr + 'T00:00:00');
        var today = new Date(); today.setHours(0,0,0,0);
        return d >= today && d <= filterCutoffDate;
    }

    function formatDate(dateStr) {
        var d = new Date(dateStr + 'T00:00:00');
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return ('0'+d.getDate()).slice(-2) + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    }

    function calcDaysLeft(dateStr) {
        var d = new Date(dateStr + 'T00:00:00');
        var today = new Date(); today.setHours(0,0,0,0);
        var diff = Math.round((d - today) / 86400000);
        return diff < 0 ? 0 : diff;
    }

    function computeNewDate(oldDateStr, addDays) {
        var d = new Date(oldDateStr + 'T00:00:00');
        d.setDate(d.getDate() + addDays);
        var y = d.getFullYear(), m = ('0'+(d.getMonth()+1)).slice(-2), dd = ('0'+d.getDate()).slice(-2);
        return y + '-' + m + '-' + dd;
    }

    // ── Toast ──
    var toastTimer;
    function showToast(msg, type) {
        var $t = $('#toast');
        clearTimeout(toastTimer);
        $t.stop(true).css({ opacity: 1, display: 'block', background: type === 'success' ? '#27ae60' : type === 'info' ? '#2980b9' : '#e74c3c' }).text(msg);
        toastTimer = setTimeout(function() { $t.fadeOut(400); }, 3000);
    }

    // ── Summary Card Updates ──
    function adjustCount(id, delta) {
        var $el = $(id);
        var v = parseInt($el.text()) + delta;
        if (v < 0) v = 0;
        $el.text(v);
    }

    function updateJobsShownCount() {
        var visible = $('#expiringTable tbody tr:visible').length;
        // Update the "X jobs shown" text
        $('.pull-right.text-muted').last().text(visible + ' jobs shown');
    }

    function removeRowAnimated(row, jobNo, newDateStr) {
        row.addClass('flash-green');
        setTimeout(function() {
            row.fadeOut(400, function() {
                $(this).remove();
                updateBulkButtons();
                updateJobsShownCount();
            });
        }, 600);
        showToast('Job #' + jobNo + ' extended to ' + formatDate(newDateStr) + ' — removed from view', 'info');
    }

    // Decrement all summary cards that no longer include this job
    // (job was within 0..N days, now it's beyond that → decrement cards where cutoff < newDaysLeft)
    function adjustCardsAfterExtend(oldDaysLeft, newDaysLeft) {
        // Each card shows cumulative count for its range
        // If the job was within a card's range but no longer is, decrement that card
        var cardRanges = [
            { id: '#count-today',    max: 0 },
            { id: '#count-tomorrow', max: 1 },
            { id: '#count-3days',    max: 3 },
            { id: '#count-7days',    max: 7 },
            { id: '#count-14days',   max: 14 }
        ];
        for (var i = 0; i < cardRanges.length; i++) {
            var c = cardRanges[i];
            var wasIn = oldDaysLeft <= c.max;
            var nowIn = newDaysLeft <= c.max;
            if (wasIn && !nowIn) adjustCount(c.id, -1);
        }
    }

    function adjustCardsAfterTrash(oldDaysLeft) {
        var cardRanges = [
            { id: '#count-today',    max: 0 },
            { id: '#count-tomorrow', max: 1 },
            { id: '#count-3days',    max: 3 },
            { id: '#count-7days',    max: 7 },
            { id: '#count-14days',   max: 14 }
        ];
        for (var i = 0; i < cardRanges.length; i++) {
            if (oldDaysLeft <= cardRanges[i].max) adjustCount(cardRanges[i].id, -1);
        }
    }

    // ── Summary Card Clicks ──
    $('.summary-card').on('click', function() {
        var f = $(this).data('filter');
        window.location.href = baseUrl + '/admin/analytics/expiring-jobs?filter=' + f;
    });

    // ── Custom Range Toggle ──
    $('#filterDays').on('change', function() {
        if ($(this).val() === 'custom') { $('#customRange').show(); } else { $('#customRange').hide(); }
    });

    // ── Select All ──
    $('#selectAll').on('change', function() {
        $('.row-check').prop('checked', $(this).is(':checked'));
        updateBulkButtons();
    });
    $(document).on('change', '.row-check', function() { updateBulkButtons(); });
    function updateBulkButtons() {
        var count = $('.row-check:checked').length;
        $('.bulk-extend, #bulkTrash').prop('disabled', count === 0);
        $('#selectedCount').text(count + ' selected');
    }

    // ── Inline Date Edit ──
    $(document).on('click', '.date-display', function() {
        var cell = $(this).closest('.delete-at-cell');
        cell.find('.date-display').hide();
        cell.find('.date-edit').show().focus();
    });
    $(document).on('blur', '.date-edit', function() { saveInlineDate($(this)); });
    $(document).on('keydown', '.date-edit', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); saveInlineDate($(this)); }
        if (e.key === 'Escape') { cancelInlineDate($(this)); }
    });

    function saveInlineDate($input) {
        var cell = $input.closest('.delete-at-cell');
        var oldDate = cell.data('date');
        var newDate = $input.val();
        if (!newDate || newDate === oldDate) { cancelInlineDate($input); return; }
        var row = $input.closest('tr');
        var jobId = row.data('id');
        var jobNo = row.data('job-no');
        var oldDaysLeft = calcDaysLeft(oldDate);
        $.ajax({
            url: baseUrl + '/admin/analytics/expiring-jobs/update-date',
            method: 'POST',
            data: { job_id: jobId, delete_at: newDate, _token: csrfToken },
            success: function(res) {
                if (res.success) {
                    var newDaysLeft = calcDaysLeft(res.new_delete_at);
                    adjustCardsAfterExtend(oldDaysLeft, newDaysLeft);
                    removeRowAnimated(row, jobNo, res.new_delete_at);
                } else {
                    alert(res.message || 'Failed to update');
                    cancelInlineDate($input);
                }
            },
            error: function(xhr) {
                alert(xhr.responseJSON ? xhr.responseJSON.message : 'Error updating date');
                cancelInlineDate($input);
            }
        });
    }
    function cancelInlineDate($input) {
        var cell = $input.closest('.delete-at-cell');
        $input.val(cell.data('date')).hide();
        cell.find('.date-display').show();
    }

    // ── Single Row Extend ──
    $(document).on('click', '.extend-btn', function() {
        var btn = $(this);
        var row = btn.closest('tr');
        var jobId = row.data('id');
        var jobNo = row.data('job-no');
        var days = btn.data('days');
        var cell = row.find('.delete-at-cell');
        var oldDate = cell.data('date');
        var oldDaysLeft = calcDaysLeft(oldDate);
        btn.prop('disabled', true);
        $.ajax({
            url: baseUrl + '/admin/analytics/expiring-jobs/bulk-extend',
            method: 'POST',
            data: { job_ids: [jobId], days: days, _token: csrfToken },
            success: function(res) {
                if (res.success) {
                    var newDate = computeNewDate(oldDate, days);
                    var newDaysLeft = calcDaysLeft(newDate);
                    adjustCardsAfterExtend(oldDaysLeft, newDaysLeft);
                    removeRowAnimated(row, jobNo, newDate);
                }
            },
            error: function() { btn.prop('disabled', false); alert('Error extending date'); }
        });
    });

    // ── Single Row Trash ──
    $(document).on('click', '.trash-btn', function() {
        var row = $(this).closest('tr');
        var jobNo = row.data('job-no');
        if (!confirm('Trash job #' + jobNo + '? This will change its status to Trashed.')) return;
        var jobId = row.data('id');
        var oldDaysLeft = calcDaysLeft(row.find('.delete-at-cell').data('date'));
        $.ajax({
            url: baseUrl + '/admin/analytics/expiring-jobs/trash',
            method: 'POST',
            data: { job_id: jobId, _token: csrfToken },
            success: function(res) {
                if (res.success) {
                    adjustCardsAfterTrash(oldDaysLeft);
                    row.addClass('flash-red');
                    setTimeout(function() {
                        row.fadeOut(300, function() {
                            $(this).remove();
                            updateBulkButtons();
                            updateJobsShownCount();
                        });
                    }, 500);
                    showToast('Job #' + jobNo + ' trashed', 'error');
                }
            },
            error: function() { alert('Error trashing job'); }
        });
    });

    // ── Bulk Extend ──
    $('.bulk-extend').on('click', function() {
        var days = $(this).data('days');
        var ids = getSelectedIds();
        if (ids.length === 0) return;
        if (!confirm('Extend ' + ids.length + ' jobs by +' + days + ' days?')) return;
        var btn = $(this);
        btn.prop('disabled', true);
        $.ajax({
            url: baseUrl + '/admin/analytics/expiring-jobs/bulk-extend',
            method: 'POST',
            data: { job_ids: ids, days: days, _token: csrfToken },
            success: function(res) {
                if (res.success) {
                    ids.forEach(function(id) {
                        var row = $('tr[data-id="' + id + '"]');
                        if (row.length === 0) return;
                        var cell = row.find('.delete-at-cell');
                        var oldDate = cell.data('date');
                        var oldDaysLeft = calcDaysLeft(oldDate);
                        var newDate = computeNewDate(oldDate, days);
                        var newDaysLeft = calcDaysLeft(newDate);
                        adjustCardsAfterExtend(oldDaysLeft, newDaysLeft);
                        row.addClass('flash-green');
                        setTimeout((function(r) { return function() {
                            r.fadeOut(400, function() { $(this).remove(); updateJobsShownCount(); });
                        }; })(row), 300);
                    });
                    updateBulkButtons();
                    $('#selectAll').prop('checked', false);
                    showToast(res.updated + ' jobs extended by +' + days + ' days', 'success');
                }
                btn.prop('disabled', false);
            },
            error: function() { btn.prop('disabled', false); alert('Error extending dates'); }
        });
    });

    // ── Bulk Trash ──
    $('#bulkTrash').on('click', function() {
        var ids = getSelectedIds();
        if (ids.length === 0) return;
        if (!confirm('Trash ' + ids.length + ' selected jobs? This cannot be undone easily.')) return;
        var btn = $(this);
        btn.prop('disabled', true);
        var done = 0;
        ids.forEach(function(id) {
            var row = $('tr[data-id="' + id + '"]');
            var oldDaysLeft = row.length ? calcDaysLeft(row.find('.delete-at-cell').data('date')) : 0;
            $.ajax({
                url: baseUrl + '/admin/analytics/expiring-jobs/trash',
                method: 'POST',
                data: { job_id: id, _token: csrfToken },
                success: function() {
                    adjustCardsAfterTrash(oldDaysLeft);
                    row.addClass('flash-red');
                    setTimeout(function() {
                        row.fadeOut(300, function() { $(this).remove(); updateJobsShownCount(); });
                    }, 300);
                    done++;
                    if (done === ids.length) {
                        updateBulkButtons();
                        $('#selectAll').prop('checked', false);
                        showToast(done + ' jobs trashed', 'error');
                        btn.prop('disabled', false);
                    }
                }
            });
        });
    });

    function getSelectedIds() {
        var ids = [];
        $('.row-check:checked').each(function() { ids.push($(this).val()); });
        return ids;
    }

    function updateDaysBadge(row, daysLeft) {
        var badge = row.find('.days-left-badge');
        badge.text(daysLeft + 'd');
        if (daysLeft === 0) { badge.css({ color: '#e74c3c', background: '#fde8e8' }); }
        else if (daysLeft <= 2) { badge.css({ color: '#e67e22', background: '#fef3e2' }); }
        else if (daysLeft <= 7) { badge.css({ color: '#f39c12', background: '#fef9e7' }); }
        else { badge.css({ color: '#27ae60', background: '#eafaf1' }); }
    }

    // ── Client-side Sorting ──
    var sortCol = 'delete_at';
    var sortDir = 'asc';
    var $tbody = $('#expiringTable tbody');

    $('.sortable').on('click', function() {
        var col = $(this).data('sort');
        if (sortCol === col) { sortDir = sortDir === 'asc' ? 'desc' : 'asc'; }
        else { sortCol = col; sortDir = 'asc'; }
        $('.sortable i').attr('class', 'fa fa-sort');
        $(this).find('i').attr('class', 'fa fa-sort-' + sortDir);
        sortTable();
    });

    function sortTable() {
        var rows = $tbody.find('tr').get();
        rows.sort(function(a, b) {
            var $a = $(a), $b = $(b);
            var va, vb;
            switch(sortCol) {
                case 'job_no':    va = parseInt($a.data('job-no')) || 0; vb = parseInt($b.data('job-no')) || 0; break;
                case 'title':     va = $a.find('td:eq(2)').text().toLowerCase(); vb = $b.find('td:eq(2)').text().toLowerCase(); break;
                case 'prefecture':va = $a.find('td:eq(3)').text().toLowerCase(); vb = $b.find('td:eq(3)').text().toLowerCase(); break;
                case 'category':  va = $a.find('td:eq(5)').text().toLowerCase(); vb = $b.find('td:eq(5)').text().toLowerCase(); break;
                case 'delete_at': va = $a.find('.delete-at-cell').data('date') || ''; vb = $b.find('.delete-at-cell').data('date') || ''; break;
                case 'days_left': va = parseInt($a.find('.days-left-badge').text()) || 0; vb = parseInt($b.find('.days-left-badge').text()) || 0; break;
                default: va = ''; vb = '';
            }
            var cmp = va < vb ? -1 : (va > vb ? 1 : 0);
            return sortDir === 'asc' ? cmp : -cmp;
        });
        $.each(rows, function(i, row) { $tbody.append(row); });
    }
})();
</script>
@endpush
