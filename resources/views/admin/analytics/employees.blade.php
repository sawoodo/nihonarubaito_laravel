@extends('layouts.admin')

@section('title', 'Employee Performance')

@section('content')
<div id="page-wrapper" style="min-height: 800px">

    {{-- Page Header --}}
    <div class="row">
        <div class="col-lg-12">
            <h3 class="page-header" style="margin-bottom: 10px">
                <i class="fa fa-users fa-fw"></i> Employee Performance
                <a href="{{ url('admin/analytics') }}" class="btn btn-sm btn-default pull-right" style="margin-top: 4px">
                    <i class="fa fa-arrow-left"></i> Analytics
                </a>
            </h3>
        </div>
    </div>

    {{-- Date Range Bar --}}
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default" style="margin-bottom: 15px">
                <div class="panel-body" style="padding: 10px 15px">
                    <form id="dateForm" method="GET" action="{{ url('admin/analytics/employees') }}" class="form-inline">
                        <div class="form-group" style="margin-right: 8px">
                            <label for="from" style="margin-right: 4px">From</label>
                            <input type="date" id="from" name="from" class="form-control input-sm" value="{{ $fromDate }}" style="width: 150px">
                        </div>
                        <div class="form-group" style="margin-right: 8px">
                            <label for="to" style="margin-right: 4px">To</label>
                            <input type="date" id="to" name="to" class="form-control input-sm" value="{{ $toDate }}" style="width: 150px">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary" style="margin-right: 15px">
                            <i class="fa fa-search"></i> Apply
                        </button>
                        <span class="text-muted" style="margin-right: 10px">|</span>
                        <div class="btn-group" style="margin-right: 4px">
                            <button type="button" class="btn btn-xs btn-default preset-btn" data-preset="7d">7 Days</button>
                            <button type="button" class="btn btn-xs btn-default preset-btn" data-preset="30d">30 Days</button>
                            <button type="button" class="btn btn-xs btn-default preset-btn" data-preset="90d">90 Days</button>
                            <button type="button" class="btn btn-xs btn-default preset-btn" data-preset="this-month">This Month</button>
                            <button type="button" class="btn btn-xs btn-default preset-btn" data-preset="last-month">Last Month</button>
                        </div>
                    </form>
                    <div style="margin-top: 6px; font-size: 12px" class="text-muted">
                        <i class="fa fa-calendar"></i>
                        {{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }} &mdash; {{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}
                        <span style="margin-left: 8px">({{ $rangeDays }} {{ $rangeDays === 1 ? 'day' : 'days' }})</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3"><i class="fa fa-plus-circle fa-4x"></i></div>
                        <div class="col-xs-9 text-right">
                            <div style="font-size: 28px; font-weight: bold">{{ number_format($totalCreated) }}</div>
                            <div>Jobs Created</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-green">
                <div class="panel-heading" style="background-color: #27ae60; border-color: #27ae60; color: #fff">
                    <div class="row">
                        <div class="col-xs-3"><i class="fa fa-line-chart fa-4x"></i></div>
                        <div class="col-xs-9 text-right">
                            <div style="font-size: 28px; font-weight: bold">{{ $totalAvgPerDay }}</div>
                            <div>Avg Jobs/Day</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3"><i class="fa fa-check-circle fa-4x"></i></div>
                        <div class="col-xs-9 text-right">
                            <div style="font-size: 28px; font-weight: bold">{{ number_format($totalActive) }}</div>
                            <div>Active Jobs</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3"><i class="fa fa-trophy fa-4x"></i></div>
                        <div class="col-xs-9 text-right">
                            @if ($bestOverall)
                                <div style="font-size: 28px; font-weight: bold">{{ $bestOverall->count }}</div>
                                <div>Best Day ({{ \Carbon\Carbon::parse($bestOverall->day)->format('M d') }})</div>
                            @else
                                <div style="font-size: 28px; font-weight: bold">-</div>
                                <div>Best Day</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Employee Cards --}}
    <div class="row">
        @foreach ($employees as $emp)
        @if ($emp['created'] > 0 || $emp['active'] > 0)
        <div class="col-lg-4 col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading" style="padding: 10px 15px">
                    <h4 style="margin: 0; font-size: 16px">
                        <i class="fa fa-user fa-fw text-muted"></i>
                        {{ $emp['user']->name ?: $emp['user']->email }}
                        @if ($emp['change_percent'] !== null)
                            @if ($emp['change_percent'] > 0)
                                <span class="label label-success pull-right" style="font-size: 11px"><i class="fa fa-arrow-up"></i> {{ $emp['change_percent'] }}%</span>
                            @elseif ($emp['change_percent'] < 0)
                                <span class="label label-danger pull-right" style="font-size: 11px"><i class="fa fa-arrow-down"></i> {{ abs($emp['change_percent']) }}%</span>
                            @else
                                <span class="label label-default pull-right" style="font-size: 11px">0%</span>
                            @endif
                        @endif
                    </h4>
                    <small class="text-muted">{{ $emp['user']->email }}</small>
                </div>
                <div class="panel-body" style="padding: 12px 15px">
                    <div class="row">
                        <div class="col-xs-6">
                            <div style="margin-bottom: 8px">
                                <span class="text-muted" style="font-size: 11px">Created (period)</span><br>
                                <strong style="font-size: 20px">{{ number_format($emp['created']) }}</strong>
                                <small class="text-muted">(was {{ number_format($emp['prev_created']) }})</small>
                            </div>
                            <div style="margin-bottom: 8px">
                                <span class="text-muted" style="font-size: 11px">Active Now</span><br>
                                <strong style="font-size: 16px">{{ number_format($emp['active']) }}</strong>
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div style="margin-bottom: 8px">
                                <span class="text-muted" style="font-size: 11px">Avg/Day</span><br>
                                <strong style="font-size: 20px">{{ $emp['avg_per_day'] }}</strong>
                            </div>
                            <div style="margin-bottom: 8px">
                                <span class="text-muted" style="font-size: 11px">Streak</span><br>
                                <strong style="font-size: 16px">{{ $emp['streak'] }} days</strong>
                            </div>
                        </div>
                    </div>

                    @if ($emp['best_day_date'])
                    <div style="margin-bottom: 8px; padding: 4px 0; border-top: 1px solid #eee">
                        <span class="text-muted" style="font-size: 11px"><i class="fa fa-trophy text-warning"></i> Best Day:</span>
                        <strong>{{ $emp['best_day_count'] }} jobs</strong>
                        <span class="text-muted">({{ \Carbon\Carbon::parse($emp['best_day_date'])->format('M d, Y') }})</span>
                    </div>
                    @endif

                    {{-- Category Breakdown --}}
                    @if (!empty($emp['categories']))
                    <div style="margin-bottom: 6px; padding-top: 4px; border-top: 1px solid #eee">
                        <span class="text-muted" style="font-size: 11px">Categories</span>
                        <div style="margin-top: 2px">
                            @foreach ($emp['categories'] as $cat)
                                <span class="label label-info" style="font-size: 10px; margin-right: 3px; display: inline-block; margin-bottom: 2px">{{ $cat['name'] }}: {{ $cat['count'] }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Prefecture Breakdown --}}
                    @if (!empty($emp['prefectures']))
                    <div style="margin-bottom: 6px; padding-top: 4px; border-top: 1px solid #eee">
                        <span class="text-muted" style="font-size: 11px">Top Prefectures</span>
                        <div style="margin-top: 2px">
                            @foreach ($emp['prefectures'] as $pref)
                                <span class="label label-default" style="font-size: 10px; margin-right: 3px; display: inline-block; margin-bottom: 2px">{{ $pref['name'] }}: {{ $pref['count'] }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Quality Metrics --}}
                    @if ($emp['quality'] && $emp['quality']->total > 0)
                    <div style="padding-top: 4px; border-top: 1px solid #eee">
                        <span class="text-muted" style="font-size: 11px">Quality</span>
                        <div style="margin-top: 2px; font-size: 12px">
                            @php
                                $noImg = $emp['quality']->no_image;
                                $noSta = $emp['quality']->no_station;
                                $noWage = $emp['quality']->no_wage;
                                $tot = $emp['quality']->total;
                            @endphp
                            @if ($noImg > 0)
                                <span class="{{ $noImg / $tot > 0.1 ? 'text-danger' : 'text-warning' }}"><i class="fa fa-image"></i> No image: {{ $noImg }}</span>&nbsp;
                            @endif
                            @if ($noSta > 0)
                                <span class="{{ $noSta / $tot > 0.1 ? 'text-danger' : 'text-warning' }}"><i class="fa fa-map-marker"></i> No station: {{ $noSta }}</span>&nbsp;
                            @endif
                            @if ($noWage > 0)
                                <span class="{{ $noWage / $tot > 0.1 ? 'text-danger' : 'text-warning' }}"><i class="fa fa-money"></i> No wage: {{ $noWage }}</span>&nbsp;
                            @endif
                            @if ($noImg == 0 && $noSta == 0 && $noWage == 0)
                                <span class="text-success"><i class="fa fa-check"></i> All fields complete</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
        @endforeach
    </div>

    {{-- Daily Activity Chart --}}
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-bar-chart fa-fw"></i> Daily Job Creation
                    <div class="pull-right">
                        <div class="btn-group btn-group-xs">
                            <button class="btn btn-default active chart-type-btn" data-type="stacked">Stacked</button>
                            <button class="btn btn-default chart-type-btn" data-type="grouped">Grouped</button>
                            <button class="btn btn-default chart-type-btn" data-type="line">Line</button>
                        </div>
                    </div>
                </div>
                <div class="panel-body">
                    <div style="height: 350px; position: relative">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Detailed Table --}}
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-table fa-fw"></i> Detailed Daily Breakdown
                    <div class="pull-right form-inline" style="margin-top: -2px">
                        <select id="filterEmployee" class="form-control input-sm" style="width: 180px">
                            <option value="">All Employees</option>
                            @foreach ($employees as $emp)
                                @if ($emp['created'] > 0)
                                    <option value="{{ $emp['user']->id }}">{{ $emp['user']->name ?: $emp['user']->email }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="panel-body" style="padding: 0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-condensed" id="detailTable" style="margin-bottom: 0">
                            <thead>
                                <tr>
                                    <th style="cursor:pointer" data-sort="date">Date <i class="fa fa-sort"></i></th>
                                    <th style="cursor:pointer" data-sort="employee">Employee <i class="fa fa-sort"></i></th>
                                    <th style="cursor:pointer; text-align:right" data-sort="created">Jobs Created <i class="fa fa-sort"></i></th>
                                    <th>Categories</th>
                                    <th>Top Prefectures</th>
                                    <th style="text-align:right">Published</th>
                                    <th style="text-align:right">Draft</th>
                                </tr>
                            </thead>
                            <tbody id="detailBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Weekly Summary Heatmap --}}
    @foreach ($weeklySummary as $ws)
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-calendar fa-fw"></i> Weekly Heatmap: {{ $ws['user']->name ?: $ws['user']->email }}
                </div>
                <div class="panel-body" style="padding: 0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-condensed" style="margin-bottom: 0">
                            <thead>
                                <tr>
                                    <th style="width: 100px">Week</th>
                                    <th style="text-align:center">Mon</th>
                                    <th style="text-align:center">Tue</th>
                                    <th style="text-align:center">Wed</th>
                                    <th style="text-align:center">Thu</th>
                                    <th style="text-align:center">Fri</th>
                                    <th style="text-align:center">Sat</th>
                                    <th style="text-align:center">Sun</th>
                                    <th style="text-align:center; font-weight:bold">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ws['weeks'] as $week)
                                <tr>
                                    <td><small>{{ $week['label'] }}</small></td>
                                    @foreach ($week['days'] as $dayCount)
                                        @php
                                            if ($dayCount === 0) $bg = '#fff';
                                            elseif ($dayCount < 5) $bg = '#c6efce';
                                            elseif ($dayCount < 20) $bg = '#6bcb77';
                                            elseif ($dayCount < 50) $bg = '#27ae60';
                                            else $bg = '#1a7a42';
                                            $textColor = $dayCount >= 20 ? '#fff' : '#333';
                                        @endphp
                                        <td style="text-align:center; background-color: {{ $bg }}; color: {{ $textColor }}; font-weight: {{ $dayCount > 0 ? 'bold' : 'normal' }}">
                                            {{ $dayCount > 0 ? $dayCount : '' }}
                                        </td>
                                    @endforeach
                                    <td style="text-align:center; font-weight:bold">{{ $week['total'] }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach

</div>
@endsection

@push('page-scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    // ── Date Preset Buttons ──
    var presets = {
        '7d':         function() { return [daysAgo(6), today()]; },
        '30d':        function() { return [daysAgo(29), today()]; },
        '90d':        function() { return [daysAgo(89), today()]; },
        'this-month': function() { var d = new Date(); return [fmt(new Date(d.getFullYear(), d.getMonth(), 1)), today()]; },
        'last-month': function() { var d = new Date(); return [fmt(new Date(d.getFullYear(), d.getMonth()-1, 1)), fmt(new Date(d.getFullYear(), d.getMonth(), 0))]; }
    };
    function today() { return fmt(new Date()); }
    function daysAgo(n) { var d = new Date(); d.setDate(d.getDate() - n); return fmt(d); }
    function fmt(d) { return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); }

    $('.preset-btn').on('click', function() {
        var range = presets[$(this).data('preset')]();
        $('#from').val(range[0]);
        $('#to').val(range[1]);
        $('#dateForm').submit();
    });

    // ── Daily Chart ──
    var chartLabels = @json($chartLabels);
    var chartDatasets = @json($chartDatasets);
    var ctx = document.getElementById('dailyChart').getContext('2d');

    var barDatasets = chartDatasets.map(function(ds) {
        return {
            label: ds.label,
            data: ds.data,
            backgroundColor: ds.backgroundColor + 'cc',
            borderColor: ds.borderColor,
            borderWidth: 1
        };
    });

    var dailyChart = new Chart(ctx, {
        type: 'bar',
        data: { labels: chartLabels, datasets: barDatasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true }
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Chart type toggle
    $('.chart-type-btn').on('click', function() {
        $('.chart-type-btn').removeClass('active');
        $(this).addClass('active');
        var type = $(this).data('type');

        dailyChart.destroy();

        if (type === 'line') {
            var lineDs = chartDatasets.map(function(ds) {
                return {
                    label: ds.label,
                    data: ds.data,
                    borderColor: ds.borderColor,
                    backgroundColor: ds.backgroundColor + '33',
                    fill: false,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 2
                };
            });
            dailyChart = new Chart(ctx, {
                type: 'line',
                data: { labels: chartLabels, datasets: lineDs },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } },
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        } else {
            var stacked = type === 'stacked';
            var barDs = chartDatasets.map(function(ds) {
                return {
                    label: ds.label,
                    data: ds.data,
                    backgroundColor: ds.backgroundColor + 'cc',
                    borderColor: ds.borderColor,
                    borderWidth: 1
                };
            });
            dailyChart = new Chart(ctx, {
                type: 'bar',
                data: { labels: chartLabels, datasets: barDs },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { stacked: stacked },
                        y: { stacked: stacked, beginAtZero: true }
                    },
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    });

    // ── Detailed Table ──
    var detailData = @json($detailTableData);

    var currentSort = 'date';
    var currentSortDir = 'desc';

    function renderDetailTable() {
        var filterUserId = $('#filterEmployee').val();
        var filtered = detailData.filter(function(row) {
            if (filterUserId && row.user_id != filterUserId) return false;
            return true;
        });

        // Sort
        filtered.sort(function(a, b) {
            var valA = a[currentSort], valB = b[currentSort];
            if (currentSort === 'created') {
                valA = parseInt(valA); valB = parseInt(valB);
            }
            if (valA < valB) return currentSortDir === 'asc' ? -1 : 1;
            if (valA > valB) return currentSortDir === 'asc' ? 1 : -1;
            return 0;
        });

        var html = '';
        filtered.forEach(function(row) {
            html += '<tr>';
            html += '<td>' + row.date_display + '</td>';
            html += '<td>' + row.employee + '</td>';
            html += '<td style="text-align:right"><strong>' + row.created + '</strong></td>';
            html += '<td>-</td>';
            html += '<td>-</td>';
            html += '<td style="text-align:right">-</td>';
            html += '<td style="text-align:right">-</td>';
            html += '</tr>';
        });

        if (filtered.length === 0) {
            html = '<tr><td colspan="7" class="text-center text-muted" style="padding: 20px">No data for selected period</td></tr>';
        }

        $('#detailBody').html(html);
    }

    // Sort headers
    $('#detailTable thead th[data-sort]').on('click', function() {
        var col = $(this).data('sort');
        if (currentSort === col) {
            currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort = col;
            currentSortDir = col === 'date' ? 'desc' : 'desc';
        }
        // Update sort icons
        $('#detailTable thead th i').attr('class', 'fa fa-sort');
        $(this).find('i').attr('class', 'fa fa-sort-' + (currentSortDir === 'asc' ? 'asc' : 'desc'));
        renderDetailTable();
    });

    $('#filterEmployee').on('change', function() { renderDetailTable(); });

    renderDetailTable();

})();
</script>
@endpush
