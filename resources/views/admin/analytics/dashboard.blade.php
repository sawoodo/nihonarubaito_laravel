@extends('layouts.admin')

@section('title', 'Analytics Dashboard')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header" style="margin-bottom: 10px">Analytics Dashboard</h3>
        </div>
    </div>

    {{-- Date Range Picker Bar --}}
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default" style="margin-bottom: 15px">
                <div class="panel-body" style="padding: 10px 15px">
                    <form id="dateForm" method="GET" action="{{ url('admin/analytics') }}" class="form-inline">
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
                            <button type="button" class="btn btn-xs btn-default preset-btn" data-preset="today">Today</button>
                            <button type="button" class="btn btn-xs btn-default preset-btn" data-preset="yesterday">Yesterday</button>
                            <button type="button" class="btn btn-xs btn-default preset-btn" data-preset="7d">7 Days</button>
                            <button type="button" class="btn btn-xs btn-default preset-btn" data-preset="30d">30 Days</button>
                            <button type="button" class="btn btn-xs btn-default preset-btn" data-preset="90d">90 Days</button>
                            <button type="button" class="btn btn-xs btn-default preset-btn" data-preset="this-month">This Month</button>
                            <button type="button" class="btn btn-xs btn-default preset-btn" data-preset="last-month">Last Month</button>
                            <button type="button" class="btn btn-xs btn-default preset-btn" data-preset="this-year">This Year</button>
                        </div>
                    </form>
                    <div class="tw-mt-2 tw-text-sm text-muted">
                        <i class="fa fa-calendar"></i>
                        {{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }} — {{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}
                        <span class="tw-ml-2">({{ $rangeDays }} {{ $rangeDays === 1 ? 'day' : 'days' }})</span>
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
                        <div class="col-xs-3"><i class="fa fa-briefcase fa-5x"></i></div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ number_format($activeJobs) }}</div>
                            <div>Active Jobs</div>
                        </div>
                    </div>
                </div>
                <a href="{{ url('admin/jobs/published') }}">
                    <div class="panel-footer">
                        <span class="pull-left">View Details</span>
                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-green">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3"><i class="fa fa-plus-circle fa-5x"></i></div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ number_format($newJobs) }}</div>
                            <div>New Jobs</div>
                            @include('admin.analytics.partials.comparison', ['current' => $newJobs, 'previous' => $prevNewJobs])
                        </div>
                    </div>
                </div>
                <a href="{{ url('admin/jobs') }}">
                    <div class="panel-footer">
                        <span class="pull-left">View Details</span>
                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-yellow">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3"><i class="fa fa-check-circle fa-5x"></i></div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ number_format($conversions) }}</div>
                            <div>Conversions</div>
                            @include('admin.analytics.partials.comparison', ['current' => $conversions, 'previous' => $prevConversions])
                        </div>
                    </div>
                </div>
                <a href="{{ url('admin/application-logs') }}">
                    <div class="panel-footer">
                        <span class="pull-left">View Details</span>
                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-red">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3"><i class="fa fa-user-plus fa-5x"></i></div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ number_format($newSubscribers) }}</div>
                            <div>New Subscribers</div>
                            @include('admin.analytics.partials.comparison', ['current' => $newSubscribers, 'previous' => $prevNewSubscribers])
                        </div>
                    </div>
                </div>
                <a href="{{ url('admin/subscribers') }}">
                    <div class="panel-footer">
                        <span class="pull-left">View Details</span>
                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    {{-- Row: Daily Trend Chart + Category Pie --}}
    <div class="row">
        <div class="col-lg-8">
            <div class="panel panel-default">
                <div class="panel-heading"><i class="fa fa-line-chart fa-fw"></i> Daily New vs Expired Jobs</div>
                <div class="panel-body">
                    <canvas id="dailyChart" height="110"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel panel-default">
                <div class="panel-heading"><i class="fa fa-pie-chart fa-fw"></i> Active Jobs by Category</div>
                <div class="panel-body">
                    <canvas id="categoryChart" height="170"></canvas>
                    <div class="tw-mt-3">
                        <table class="table table-condensed" style="margin-bottom:0">
                            @foreach ($categoryStats as $cat)
                            <tr>
                                <td>{{ $cat->category }}</td>
                                <td class="text-right">
                                    <strong>{{ $cat->active }}</strong>
                                    <span class="text-muted">/ +{{ $cat->new_in_range }} new</span>
                                </td>
                            </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row: Top Jobs by Conversions --}}
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading"><i class="fa fa-trophy fa-fw"></i> Top Performing Jobs (by Conversions)</div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Job No</th>
                                    <th>Title</th>
                                    <th>Prefecture</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th class="text-center">Conversions</th>
                                    <th>First Apply</th>
                                    <th>Last Apply</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topJobs as $i => $job)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <a href="{{ url("admin/jobs/{$job->job_no}/view") }}" class="btn btn-xs tw-btn-purple" target="_blank">
                                            {{ $job->job_no }}
                                        </a>
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($job->title, 40) }}</td>
                                    <td>{{ $job->prefecture }}</td>
                                    <td>{{ $job->category }}</td>
                                    <td>
                                        @php $s = $statusLabels[$job->job_status_id] ?? ['Unknown', 'default'] @endphp
                                        <span class="label label-{{ $s[1] }}">{{ $s[0] }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="tw-px-3 tw-py-1 tw-rounded-full tw-text-white {{ $job->conversions >= 30 ? 'tw-bg-red-500' : ($job->conversions >= 10 ? 'tw-bg-amber-400' : 'tw-bg-emerald-500') }}">
                                            {{ $job->conversions }}
                                        </span>
                                    </td>
                                    <td>{{ $job->first_apply ? date('d M Y', strtotime($job->first_apply)) : '' }}</td>
                                    <td>{{ $job->last_apply ? date('d M Y', strtotime($job->last_apply)) : '' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="9" class="text-center text-muted">No conversion data for this period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row: Prefecture Bar Chart + Table --}}
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading"><i class="fa fa-bar-chart fa-fw"></i> Jobs by Prefecture — Top 20</div>
                <div class="panel-body">
                    <canvas id="prefectureChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading"><i class="fa fa-map-marker fa-fw"></i> All Prefectures — Full Breakdown</div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>Prefecture</th>
                                    <th class="text-center">Active</th>
                                    <th class="text-center">New</th>
                                    <th class="text-center">Expired / Trashed</th>
                                    <th class="text-center">Conversions</th>
                                    <th class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($prefectureStats as $pref)
                                <tr class="{{ $pref->active < 10 && $pref->active > 0 ? 'warning' : '' }}">
                                    <td>{{ $pref->prefecture }}</td>
                                    <td class="text-center">
                                        @if ($pref->active < 10 && $pref->total > 0)
                                            <span class="text-danger"><strong>{{ $pref->active }}</strong></span>
                                        @else
                                            {{ $pref->active }}
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $pref->new_in_range }}</td>
                                    <td class="text-center">{{ $pref->expired_recent }}</td>
                                    <td class="text-center">{{ $prefApplyCounts[$pref->prefecture_id] ?? 0 }}</td>
                                    <td class="text-center">{{ number_format($pref->total) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════ --}}
    {{-- GA4 Landing Page Analysis --}}
    {{-- ════════════════════════════════════════════════════ --}}
    <div class="row">
        <div class="col-lg-12">
            <h3 class="page-header" style="margin-bottom: 10px; margin-top: 10px">
                <i class="fa fa-google fa-fw"></i> GA4 Landing Page Analysis
            </h3>
        </div>
    </div>

    {{-- Upload Form --}}
    <div class="row">
        <div class="col-lg-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-upload fa-fw"></i> Upload GA4 CSV Export
                    @if ($lastUpload)
                        <span class="pull-right text-muted" style="font-size: 12px">
                            Last upload: {{ date('d M Y, H:i', strtotime($lastUpload->uploaded_at)) }}
                            ({{ $lastUpload->date_from }} to {{ $lastUpload->date_to }})
                        </span>
                    @endif
                </div>
                <div class="panel-body">
                    <form method="POST" action="{{ url('admin/analytics/upload-ga4') }}" enctype="multipart/form-data" class="form-inline">
                        @csrf
                        <div class="form-group" style="margin-right: 10px">
                            <label for="csv_file" style="margin-right: 4px">CSV File</label>
                            <input type="file" id="csv_file" name="csv_file" class="form-control input-sm" accept=".csv,.txt" required>
                        </div>
                        <div class="form-group" style="margin-right: 8px">
                            <label for="ga4_from" style="margin-right: 4px">From</label>
                            <input type="date" id="ga4_from" name="ga4_from" class="form-control input-sm" value="{{ $lastUpload ? $lastUpload->date_from : now()->subDays(29)->format('Y-m-d') }}" required style="width: 150px">
                        </div>
                        <div class="form-group" style="margin-right: 8px">
                            <label for="ga4_to" style="margin-right: 4px">To</label>
                            <input type="date" id="ga4_to" name="ga4_to" class="form-control input-sm" value="{{ $lastUpload ? $lastUpload->date_to : now()->format('Y-m-d') }}" required style="width: 150px">
                        </div>
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="fa fa-cloud-upload"></i> Upload &amp; Analyze
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel panel-info">
                <div class="panel-heading"><i class="fa fa-question-circle fa-fw"></i> How to Export from GA4</div>
                <div class="panel-body" style="font-size: 13px">
                    <ol style="padding-left: 18px; margin-bottom: 0">
                        <li>Go to <strong>GA4 &rarr; Reports &rarr; Pages and screens</strong></li>
                        <li>Change the primary dimension to <strong>"Landing page"</strong></li>
                        <li>Set the date range (top right) to the period you want</li>
                        <li>Show <strong>all rows</strong> (bottom of table, set to max)</li>
                        <li>Click the <strong>share</strong> icon (top right) &rarr; <strong>"Download file"</strong></li>
                        <li>Choose <strong>CSV</strong></li>
                        <li>Upload the downloaded file here with matching dates</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- Supply Gap Alerts --}}
    @if ($lastUpload)
    <div class="row">
        <div class="col-lg-4 col-md-6">
            <div class="panel {{ $gapZeroJobs > 0 ? 'panel-danger' : 'panel-success' }}">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3"><i class="fa fa-exclamation-triangle fa-5x"></i></div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ $gapZeroJobs }}</div>
                            <div>Pages with 0 Jobs</div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <span class="pull-left">Traffic landing on empty pages</span>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="panel {{ $gapLowJobs > 0 ? 'panel-yellow' : 'panel-success' }}">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3"><i class="fa fa-warning fa-5x"></i></div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ $gapLowJobs }}</div>
                            <div>Pages with &lt;5 Jobs</div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <span class="pull-left">Low supply, may lose visitors</span>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3"><i class="fa fa-database fa-5x"></i></div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ count($demandSupply) }}</div>
                            <div>Pages Analyzed</div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <span class="pull-left">{{ $lastUpload->date_from }} to {{ $lastUpload->date_to }}</span>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Demand vs Supply Table --}}
    <div class="row tw-mb-5">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-balance-scale fa-fw"></i> Demand vs Supply
                    <span class="text-muted" style="font-size: 12px; margin-left: 10px">
                        Sorted by urgency: No Jobs &rarr; Low Supply &rarr; OK | Higher sessions = more missed opportunities
                    </span>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-striped table-condensed" id="demandSupplyTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Page Path</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th class="text-center">Sessions</th>
                                    <th class="text-center">Pageviews</th>
                                    <th class="text-center">Active Jobs</th>
                                    <th class="text-center">Gap Score</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($demandSupply as $i => $row)
                                <tr class="{{ $row['status'] === 'No Jobs' ? 'danger' : ($row['status'] === 'Low Supply' ? 'warning' : '') }}">
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <a href="{{ url($row['page_path']) }}" target="_blank" style="font-size: 12px">
                                            {{ $row['page_path'] }}
                                        </a>
                                    </td>
                                    <td><span class="label label-default">{{ $row['page_type'] }}</span></td>
                                    <td>{{ $row['location'] }}</td>
                                    <td class="text-center">{{ number_format($row['sessions']) }}</td>
                                    <td class="text-center">{{ number_format($row['pageviews']) }}</td>
                                    <td class="text-center">
                                        @if ($row['active_jobs'] === 0)
                                            <span class="text-danger"><strong>0</strong></span>
                                        @elseif ($row['active_jobs'] < 5)
                                            <span class="text-warning"><strong>{{ $row['active_jobs'] }}</strong></span>
                                        @else
                                            {{ $row['active_jobs'] }}
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($row['gap_score'] !== null)
                                            {{ $row['gap_score'] }}
                                        @else
                                            <span class="text-danger">&infin;</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="label label-{{ $row['status_class'] }}">{{ $row['status'] }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="9" class="text-center text-muted">No data. Upload a GA4 CSV to see demand vs supply analysis.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection

@push('page-scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function() {
    // ── Date Preset Buttons ──
    var presets = {
        'today': function() {
            var d = new Date(); return [fmt(d), fmt(d)];
        },
        'yesterday': function() {
            var d = new Date(); d.setDate(d.getDate() - 1); return [fmt(d), fmt(d)];
        },
        '7d': function() {
            var d = new Date(), f = new Date(); f.setDate(f.getDate() - 6); return [fmt(f), fmt(d)];
        },
        '30d': function() {
            var d = new Date(), f = new Date(); f.setDate(f.getDate() - 29); return [fmt(f), fmt(d)];
        },
        '90d': function() {
            var d = new Date(), f = new Date(); f.setDate(f.getDate() - 89); return [fmt(f), fmt(d)];
        },
        'this-month': function() {
            var d = new Date();
            return [fmt(new Date(d.getFullYear(), d.getMonth(), 1)), fmt(d)];
        },
        'last-month': function() {
            var d = new Date();
            var first = new Date(d.getFullYear(), d.getMonth() - 1, 1);
            var last = new Date(d.getFullYear(), d.getMonth(), 0);
            return [fmt(first), fmt(last)];
        },
        'this-year': function() {
            var d = new Date();
            return [fmt(new Date(d.getFullYear(), 0, 1)), fmt(d)];
        }
    };

    function fmt(d) {
        var y = d.getFullYear();
        var m = ('0' + (d.getMonth() + 1)).slice(-2);
        var day = ('0' + d.getDate()).slice(-2);
        return y + '-' + m + '-' + day;
    }

    $('.preset-btn').on('click', function() {
        var range = presets[$(this).data('preset')]();
        $('#from').val(range[0]);
        $('#to').val(range[1]);
        $('#dateForm').submit();
    });

    // ── Charts ──
    var blue = 'rgba(54, 162, 235, 0.8)';
    var blueBorder = 'rgba(54, 162, 235, 1)';
    var red = 'rgba(255, 99, 132, 0.8)';
    var redBorder = 'rgba(255, 99, 132, 1)';

    new Chart(document.getElementById('dailyChart'), {
        type: 'line',
        data: {
            labels: @json($days),
            datasets: [
                {
                    label: 'New Jobs',
                    data: @json($newCounts),
                    borderColor: blueBorder,
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    fill: true, tension: 0.3, pointRadius: {{ $rangeDays > 60 ? 0 : 2 }}
                },
                {
                    label: 'Expired / Trashed',
                    data: @json($expiredCounts),
                    borderColor: redBorder,
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    fill: true, tension: 0.3, pointRadius: {{ $rangeDays > 60 ? 0 : 2 }}
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { position: 'top' } }
        }
    });

    var catLabels = @json($categoryStats->pluck('category'));
    var catData = @json($categoryStats->pluck('active'));
    var catColors = ['#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22'];

    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: catLabels,
            datasets: [{ data: catData, backgroundColor: catColors.slice(0, catLabels.length) }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom', labels: { padding: 15 } } }
        }
    });

    var prefData = @json($prefectureStats->take(20));
    new Chart(document.getElementById('prefectureChart'), {
        type: 'bar',
        data: {
            labels: prefData.map(function(p) { return p.prefecture; }),
            datasets: [
                {
                    label: 'Active',
                    data: prefData.map(function(p) { return p.active; }),
                    backgroundColor: blue, borderColor: blueBorder, borderWidth: 1
                },
                {
                    label: 'Expired/Trashed',
                    data: prefData.map(function(p) { return p.expired_recent; }),
                    backgroundColor: red, borderColor: redBorder, borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { position: 'top' } }
        }
    });
})();
</script>
@endpush

@push('page-styles')
<style>
    .huge { font-size: 30px; }
    .panel-green { border-color: #5cb85c; }
    .panel-green > .panel-heading { border-color: #5cb85c; background-color: #5cb85c; color: white; }
    .panel-green > a { color: #5cb85c; }
    .panel-green > a:hover { color: #3d8b3d; }
    .panel-yellow { border-color: #f0ad4e; }
    .panel-yellow > .panel-heading { border-color: #f0ad4e; background-color: #f0ad4e; color: white; }
    .panel-yellow > a { color: #f0ad4e; }
    .panel-yellow > a:hover { color: #c77c11; }
    .panel-red { border-color: #d9534f; }
    .panel-red > .panel-heading { border-color: #d9534f; background-color: #d9534f; color: white; }
    .panel-red > a { color: #d9534f; }
    .panel-red > a:hover { color: #a02622; }
    .comparison { font-size: 12px; opacity: 0.9; margin-top: 2px; }
    .comparison .fa { margin-right: 2px; }
</style>
@endpush
