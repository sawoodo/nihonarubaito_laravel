@extends('layouts.admin')

@section('title', 'Duplicate Jobs Review')

@section('content')
<div id="page-wrapper" style="min-height: 800px">

    {{-- Page Header --}}
    <div class="row">
        <div class="col-lg-12">
            <h3 class="page-header" style="margin-bottom: 5px">
                <i class="fa fa-clone fa-fw"></i> Duplicate Jobs Review
                <div class="pull-right" style="font-size: 14px; margin-top: 6px">
                    <a href="{{ url('admin/analytics') }}" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> Analytics</a>
                </div>
            </h3>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="panel {{ $levelFilter === 'high' ? 'panel-danger' : 'panel-default' }}" style="cursor:pointer" onclick="window.location='?level=high'">
                <div class="panel-body text-center" style="padding:12px 8px">
                    <div style="font-size:28px;font-weight:700" class="text-danger">{{ $highCount }}</div>
                    <div style="font-size:12px;color:#888">High Confidence</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel {{ $levelFilter === 'medium' ? 'panel-warning' : 'panel-default' }}" style="cursor:pointer" onclick="window.location='?level=medium'">
                <div class="panel-body text-center" style="padding:12px 8px">
                    <div style="font-size:28px;font-weight:700" class="text-warning">{{ $mediumCount }}</div>
                    <div style="font-size:12px;color:#888">Medium Confidence</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel {{ $levelFilter === 'low' ? 'panel-info' : 'panel-default' }}" style="cursor:pointer" onclick="window.location='?level=low'">
                <div class="panel-body text-center" style="padding:12px 8px">
                    <div style="font-size:28px;font-weight:700" class="text-info">{{ $lowCount }}</div>
                    <div style="font-size:12px;color:#888">Low Confidence</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel {{ $levelFilter === 'all' ? 'panel-primary' : 'panel-default' }}" style="cursor:pointer" onclick="window.location='?level=all'">
                <div class="panel-body text-center" style="padding:12px 8px">
                    <div style="font-size:28px;font-weight:700">{{ $highCount + $mediumCount + $lowCount }}</div>
                    <div style="font-size:12px;color:#888">Total Groups</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs" style="margin-bottom:15px">
        <li class="active"><a href="#active-tab" data-toggle="tab">Active Duplicates ({{ count($groups) }})</a></li>
        <li><a href="#dismissed-tab" data-toggle="tab">Dismissed ({{ count($dismissedGroups) }})</a></li>
    </ul>

    <div class="tab-content">

    {{-- Active Tab --}}
    <div class="tab-pane active" id="active-tab">

    {{-- Filter Bar --}}
    <form method="GET" class="form-inline" style="margin-bottom:15px; background:#f5f5f5; padding:12px; border-radius:4px">
        <div class="form-group" style="margin-right:10px">
            <label style="margin-right:4px">Confidence:</label>
            <select name="level" class="form-control input-sm" onchange="this.form.submit()">
                <option value="all" {{ $levelFilter === 'all' ? 'selected' : '' }}>All</option>
                <option value="high" {{ $levelFilter === 'high' ? 'selected' : '' }}>High</option>
                <option value="medium" {{ $levelFilter === 'medium' ? 'selected' : '' }}>Medium</option>
                <option value="low" {{ $levelFilter === 'low' ? 'selected' : '' }}>Low</option>
            </select>
        </div>
        <div class="form-group" style="margin-right:10px">
            <label style="margin-right:4px">Prefecture:</label>
            <select name="prefecture_id" class="form-control input-sm" onchange="this.form.submit()">
                <option value="0">All</option>
                @foreach ($prefectureList as $pid => $pname)
                    <option value="{{ $pid }}" {{ $prefectureFilter == $pid ? 'selected' : '' }}>{{ $pname }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin-right:10px">
            <input type="text" name="search" class="form-control input-sm" placeholder="Search title, company, job#..." value="{{ $search }}" style="width:220px">
        </div>
        <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-search"></i></button>
        @if ($levelFilter !== 'all' || $prefectureFilter || $search)
            <a href="{{ url('admin/analytics/duplicates') }}" class="btn btn-sm btn-default" style="margin-left:5px">Clear</a>
        @endif
    </form>

    {{-- Bulk Actions --}}
    <div style="margin-bottom:10px">
        <button class="btn btn-sm btn-default" id="btn-select-all-groups"><i class="fa fa-check-square-o"></i> Select All Groups</button>
        <button class="btn btn-sm btn-warning" id="btn-bulk-dismiss" disabled><i class="fa fa-eye-slash"></i> Dismiss Selected</button>
        <span style="margin-left:10px; color:#888; font-size:13px">{{ count($groups) }} groups shown</span>
    </div>

    @if (empty($groups))
        <div class="alert alert-success"><i class="fa fa-check"></i> No duplicate groups found. All clean!</div>
    @endif

    {{-- Duplicate Groups --}}
    @foreach ($groups as $gi => $group)
        @php
            $levelColors = ['high' => '#e74c3c', 'medium' => '#f39c12', 'low' => '#f1c40f'];
            $levelBg = ['high' => '#fdf2f2', 'medium' => '#fef9e7', 'low' => '#fef9e7'];
            $levelLabels = ['high' => 'HIGH', 'medium' => 'MEDIUM', 'low' => 'LOW'];
            $statusLabels = [1 => 'Draft', 2 => 'Pending', 3 => 'Published', 4 => 'Expired', 5 => 'Trashed'];
            $statusColors = [1 => '#95a5a6', 2 => '#f39c12', 3 => '#27ae60', 4 => '#e74c3c', 5 => '#7f8c8d'];
        @endphp
        <div class="panel panel-default group-panel" data-hash="{{ $group['hash'] }}" data-level="{{ $group['level'] }}" style="border-left: 4px solid {{ $levelColors[$group['level']] }}">
            <div class="panel-heading" style="background: {{ $levelBg[$group['level']] }}; padding:10px 15px">
                <div class="row">
                    <div class="col-md-8">
                        <input type="checkbox" class="group-checkbox" data-hash="{{ $group['hash'] }}" style="margin-right:8px">
                        <strong>Group {{ $gi + 1 }}:</strong>
                        {{ \Illuminate\Support\Str::limit($group['label'], 70) }}
                        <span class="label" style="background:{{ $levelColors[$group['level']] }};margin-left:8px">{{ $levelLabels[$group['level']] }}</span>
                        <span style="color:#888;margin-left:8px">({{ count($group['jobs']) }} jobs)</span>
                    </div>
                    <div class="col-md-4 text-right">
                        <small class="text-muted">{{ $group['company'] }}</small>
                    </div>
                </div>
            </div>
            <div class="panel-body" style="padding:0">
                <table class="table table-condensed table-hover" style="margin:0;font-size:13px">
                    <thead>
                        <tr style="background:#fafafa">
                            <th style="width:50px">Keep</th>
                            <th style="width:80px">Job #</th>
                            <th>Title</th>
                            <th style="width:110px">Prefecture</th>
                            <th style="width:100px">Area</th>
                            <th style="width:70px">Status</th>
                            <th style="width:70px">Wage</th>
                            <th style="width:90px">Created</th>
                            <th style="width:100px">Source</th>
                            <th style="width:100px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['jobs'] as $ji => $job)
                            <tr data-job-id="{{ $job->id }}">
                                <td><input type="radio" name="keep_{{ $group['hash'] }}" value="{{ $job->id }}" {{ $ji === 0 ? 'checked' : '' }}></td>
                                <td><a href="{{ url('admin/jobs/' . $job->job_no . '/view') }}" target="_blank">#{{ $job->job_no }}</a></td>
                                <td title="{{ $job->title }}">{{ \Illuminate\Support\Str::limit($job->title, 50) }}</td>
                                <td>{{ $prefectures[$job->prefecture_id] ?? '-' }}</td>
                                <td>{{ $areas[$job->area_id] ?? '-' }}</td>
                                <td><span class="label" style="background:{{ $statusColors[$job->job_status_id] ?? '#999' }}">{{ $statusLabels[$job->job_status_id] ?? '?' }}</span></td>
                                <td>{{ $job->wage ? \Illuminate\Support\Str::limit($job->wage, 10) : '-' }}</td>
                                <td>{{ $job->date ? date('M d, Y', strtotime($job->date)) : '-' }}</td>
                                <td>
                                    @if ($job->apply_link)
                                        @php $domain = parse_url($job->apply_link, PHP_URL_HOST); @endphp
                                        <a href="{{ $job->apply_link }}" target="_blank" rel="noopener" class="label label-info" style="font-size:11px">{{ $domain ? str_replace('www.', '', $domain) : 'Link' }}</a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ url('admin/jobs/' . $job->job_no . '/view') }}" target="_blank" class="btn btn-xs btn-default" title="View"><i class="fa fa-eye"></i></a>
                                    <a href="{{ url('admin/jobs/' . $job->job_no . '/edit') }}" target="_blank" class="btn btn-xs btn-primary" title="Edit"><i class="fa fa-pencil"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Comparison: highlight differences --}}
                @if (count($group['jobs']) >= 2)
                    <div style="padding:10px 15px; background:#f9f9f9; border-top:1px solid #eee">
                        <a href="javascript:void(0)" class="btn-toggle-compare" style="font-size:12px; color:#337ab7"><i class="fa fa-columns"></i> Show Comparison</a>
                        <div class="comparison-table" style="display:none; margin-top:10px; overflow-x:auto">
                            @php
                                $fields = ['title' => 'Title', 'company_name' => 'Company', 'wage' => 'Wage', 'station' => 'Station', 'apply_link' => 'Apply Link'];
                                $jobList = $group['jobs'];
                            @endphp
                            <table class="table table-bordered table-condensed" style="font-size:12px;margin:0;background:#fff">
                                <thead>
                                    <tr>
                                        <th style="width:100px">Field</th>
                                        @foreach ($jobList as $j)
                                            <th>Job #{{ $j->job_no }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($fields as $field => $label)
                                        @php
                                            $values = array_map(fn($j) => $j->$field ?? '', (array) $jobList);
                                            if ($jobList instanceof \Illuminate\Support\Collection) {
                                                $values = $jobList->pluck($field)->toArray();
                                            } else {
                                                $values = array_map(fn($j) => $j->$field ?? '', $jobList);
                                            }
                                            $allSame = count(array_unique($values)) <= 1;
                                        @endphp
                                        <tr>
                                            <td><strong>{{ $label }}</strong></td>
                                            @foreach ($jobList as $j)
                                                <td style="{{ !$allSame ? 'background:#fff3cd' : '' }}">{{ \Illuminate\Support\Str::limit($j->$field ?? '-', 60) }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td><strong>Prefecture</strong></td>
                                        @php $prefValues = []; @endphp
                                        @foreach ($jobList as $j)
                                            @php $prefValues[] = $prefectures[$j->prefecture_id] ?? '-'; @endphp
                                            <td style="{{ count(array_unique($prefValues)) > 1 ? 'background:#fff3cd' : '' }}">{{ $prefectures[$j->prefecture_id] ?? '-' }}</td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <td><strong>Area</strong></td>
                                        @php $areaValues = []; @endphp
                                        @foreach ($jobList as $j)
                                            @php $areaValues[] = $areas[$j->area_id] ?? '-'; @endphp
                                            <td style="{{ count(array_unique($areaValues)) > 1 ? 'background:#fff3cd' : '' }}">{{ $areas[$j->area_id] ?? '-' }}</td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <td><strong>Created</strong></td>
                                        @foreach ($jobList as $j)
                                            <td>{{ $j->date ? date('M d, Y', strtotime($j->date)) : '-' }}</td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <td><strong>Expires</strong></td>
                                        @foreach ($jobList as $j)
                                            <td>{{ $j->delete_at ? date('M d, Y', strtotime($j->delete_at)) : '-' }}</td>
                                        @endforeach
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
            <div class="panel-footer" style="padding:10px 15px">
                <button class="btn btn-sm btn-success btn-keep-trash" data-hash="{{ $group['hash'] }}"><i class="fa fa-check"></i> Keep Selected &amp; Trash Others</button>
                <button class="btn btn-sm btn-default btn-dismiss-group" data-hash="{{ $group['hash'] }}"><i class="fa fa-eye-slash"></i> Dismiss Group</button>
            </div>
        </div>
    @endforeach

    </div>{{-- /active-tab --}}

    {{-- Dismissed Tab --}}
    <div class="tab-pane" id="dismissed-tab">
        @if ($dismissedGroups->isEmpty())
            <div class="alert alert-info">No dismissed groups yet.</div>
        @else
            <table class="table table-condensed table-striped" style="font-size:13px">
                <thead>
                    <tr><th>Group Hash</th><th>Dismissed At</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @foreach ($dismissedGroups as $dg)
                        <tr data-hash="{{ $dg->group_hash }}">
                            <td><code>{{ substr($dg->group_hash, 0, 16) }}...</code></td>
                            <td>{{ $dg->dismissed_at }}</td>
                            <td><button class="btn btn-xs btn-warning btn-undismiss" data-hash="{{ $dg->group_hash }}"><i class="fa fa-undo"></i> Un-dismiss</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    </div>{{-- /tab-content --}}

</div>

{{-- Toast --}}
<div id="toast" style="display:none;position:fixed;bottom:30px;right:30px;z-index:9999;padding:12px 24px;border-radius:6px;color:#fff;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.2);min-width:250px"></div>

@endsection

@push('page-scripts')
<script>
$(function(){
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    function showToast(msg, type) {
        var bg = type === 'success' ? '#27ae60' : type === 'warning' ? '#f39c12' : '#e74c3c';
        $('#toast').css('background', bg).text(msg).fadeIn(300);
        setTimeout(function(){ $('#toast').fadeOut(500); }, 3000);
    }

    // Toggle comparison
    $(document).on('click', '.btn-toggle-compare', function(){
        var $tbl = $(this).closest('div').find('.comparison-table');
        $tbl.slideToggle(200);
        var $icon = $(this).find('i');
        if ($tbl.is(':visible')) {
            $(this).html('<i class="fa fa-columns"></i> Hide Comparison');
        } else {
            $(this).html('<i class="fa fa-columns"></i> Show Comparison');
        }
    });

    // Keep & Trash
    $(document).on('click', '.btn-keep-trash', function(){
        var $panel = $(this).closest('.group-panel');
        var hash = $(this).data('hash');
        var keepId = $panel.find('input[name="keep_' + hash + '"]:checked').val();
        if (!keepId) { showToast('Please select a job to keep.', 'error'); return; }

        var trashIds = [];
        $panel.find('input[name="keep_' + hash + '"]').each(function(){
            if ($(this).val() != keepId) trashIds.push($(this).val());
        });

        if (!confirm('Keep #' + $panel.find('input[value="' + keepId + '"]').closest('tr').find('a').first().text() + ' and trash ' + trashIds.length + ' other(s)?')) return;

        $.ajax({
            url: '{{ url("admin/analytics/duplicates/keep-and-trash") }}',
            method: 'POST',
            data: { _token: csrfToken, keep_id: keepId, trash_ids: trashIds },
            success: function(res) {
                if (res.success) {
                    $panel.css('background', '#d4edda').fadeOut(600, function(){ $(this).remove(); });
                    showToast('Kept selected, trashed ' + res.trashed + ' job(s).', 'success');
                } else {
                    showToast(res.message || 'Error', 'error');
                }
            },
            error: function() { showToast('Request failed.', 'error'); }
        });
    });

    // Dismiss group
    $(document).on('click', '.btn-dismiss-group', function(){
        var $panel = $(this).closest('.group-panel');
        var hash = $(this).data('hash');

        $.ajax({
            url: '{{ url("admin/analytics/duplicates/dismiss") }}',
            method: 'POST',
            data: { _token: csrfToken, hash: hash },
            success: function(res) {
                if (res.success) {
                    $panel.css('background', '#fef9e7').fadeOut(600, function(){ $(this).remove(); });
                    showToast('Group dismissed.', 'success');
                } else {
                    showToast(res.message || 'Error', 'error');
                }
            },
            error: function() { showToast('Request failed.', 'error'); }
        });
    });

    // Select all groups
    $('#btn-select-all-groups').click(function(){
        var all = $('.group-checkbox');
        var checked = all.filter(':checked').length === all.length;
        all.prop('checked', !checked);
        $('#btn-bulk-dismiss').prop('disabled', all.filter(':checked').length === 0);
    });

    $(document).on('change', '.group-checkbox', function(){
        $('#btn-bulk-dismiss').prop('disabled', $('.group-checkbox:checked').length === 0);
    });

    // Bulk dismiss
    $('#btn-bulk-dismiss').click(function(){
        var hashes = [];
        $('.group-checkbox:checked').each(function(){ hashes.push($(this).data('hash')); });
        if (!hashes.length) return;
        if (!confirm('Dismiss ' + hashes.length + ' group(s)?')) return;

        $.ajax({
            url: '{{ url("admin/analytics/duplicates/bulk-dismiss") }}',
            method: 'POST',
            data: { _token: csrfToken, hashes: hashes },
            success: function(res) {
                if (res.success) {
                    hashes.forEach(function(h){
                        $('.group-panel[data-hash="' + h + '"]').fadeOut(400, function(){ $(this).remove(); });
                    });
                    showToast('Dismissed ' + res.dismissed + ' group(s).', 'success');
                } else {
                    showToast(res.message || 'Error', 'error');
                }
            },
            error: function() { showToast('Request failed.', 'error'); }
        });
    });

    // Un-dismiss
    $(document).on('click', '.btn-undismiss', function(){
        var hash = $(this).data('hash');
        var $row = $(this).closest('tr');

        $.ajax({
            url: '{{ url("admin/analytics/duplicates/undismiss") }}',
            method: 'POST',
            data: { _token: csrfToken, hash: hash },
            success: function(res) {
                if (res.success) {
                    $row.fadeOut(400, function(){ $(this).remove(); });
                    showToast('Group un-dismissed. It will reappear on next page load.', 'success');
                } else {
                    showToast(res.message || 'Error', 'error');
                }
            },
            error: function() { showToast('Request failed.', 'error'); }
        });
    });
});
</script>
@endpush
