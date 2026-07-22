@extends('layouts.admin')

@section('title', 'Demand vs Supply')

@section('content')
<div id="page-wrapper" style="min-height: 800px">

    {{-- ── Page Header ── --}}
    <div class="row">
        <div class="col-lg-12">
            <h3 class="page-header" style="margin-bottom: 5px">
                <i class="fa fa-balance-scale fa-fw"></i> Demand vs Supply
                <div class="pull-right" style="font-size: 14px; margin-top: 6px">
                    @if ($lastUpload)
                        <span class="text-muted">
                            <i class="fa fa-calendar"></i> {{ $lastUpload->date_from }} to {{ $lastUpload->date_to }}
                            <span style="margin: 0 8px">|</span>
                            <i class="fa fa-clock-o"></i> Uploaded {{ date('d M Y, H:i', strtotime($lastUpload->uploaded_at)) }}
                        </span>
                    @endif
                    <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#uploadModal" style="margin-left: 10px">
                        <i class="fa fa-cloud-upload"></i> Upload GA4 CSV
                    </button>
                    <a href="{{ url('admin/analytics/demand-supply/export') }}" class="btn btn-sm btn-default" style="margin-left: 4px" {{ !$lastUpload ? 'disabled' : '' }}>
                        <i class="fa fa-download"></i> Export CSV
                    </a>
                    <a href="{{ url('admin/analytics') }}" class="btn btn-sm btn-default" style="margin-left: 4px">
                        <i class="fa fa-arrow-left"></i> Analytics
                    </a>
                </div>
            </h3>
        </div>
    </div>

    {{-- ── Summary Cards ── --}}
    <div class="row">
        <div class="col-lg-3 col-md-6">
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
                    <span class="pull-left">Landing pages with traffic</span>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel {{ $gapZeroJobs > 0 ? 'panel-danger' : 'panel-success' }}">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3"><i class="fa fa-exclamation-triangle fa-5x"></i></div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ $gapZeroJobs }}</div>
                            <div>No Jobs (0)</div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <span class="pull-left">{{ number_format($totalNoJobSessions) }} sessions on empty pages</span>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel {{ $gapLowJobs > 0 ? 'panel-yellow' : 'panel-success' }}">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3"><i class="fa fa-warning fa-5x"></i></div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ $gapLowJobs }}</div>
                            <div>Low Supply (&lt;5)</div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <span class="pull-left">May lose visitors</span>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-green">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3"><i class="fa fa-check-circle fa-5x"></i></div>
                        <div class="col-xs-9 text-right">
                            <div class="huge">{{ $gapOk }}</div>
                            <div>OK (5+)</div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <span class="pull-left">Adequate job supply</span>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Filter Tabs ── --}}
    <div class="row">
        <div class="col-lg-12">
            <ul class="nav nav-tabs" id="statusTabs" style="margin-bottom: 0">
                <li class="active"><a href="#" data-filter="all">All Pages <span class="badge">{{ count($demandSupply) }}</span></a></li>
                <li><a href="#" data-filter="No Jobs">No Jobs <span class="badge badge-danger">{{ $gapZeroJobs }}</span></a></li>
                <li><a href="#" data-filter="Low Supply">Low Supply <span class="badge badge-warning">{{ $gapLowJobs }}</span></a></li>
                <li><a href="#" data-filter="OK">OK <span class="badge badge-success">{{ $gapOk }}</span></a></li>
                <li class="divider-vertical" style="border-left: 1px solid #ddd; height: 20px; margin: 10px 4px 0"></li>
                <li><a href="#" data-filter="type:Prefecture/Area">Prefectures</a></li>
                <li><a href="#" data-filter="type:Area">Areas</a></li>
                <li><a href="#" data-filter="type:Station">Stations</a></li>
                <li><a href="#" data-filter="type:Category">Categories</a></li>
                <li><a href="#" data-filter="type:Daily Pay">Hand Cash</a></li>
            </ul>
        </div>
    </div>

    {{-- ── Filters Bar ── --}}
    <div class="row" style="margin-top: 0">
        <div class="col-lg-12">
            <div class="panel panel-default" style="margin-bottom: 10px; border-top: 0; border-top-left-radius: 0; border-top-right-radius: 0">
                <div class="panel-body" style="padding: 10px 15px">
                    <form class="form-inline" id="filterForm">
                        <div class="form-group" style="margin-right: 10px">
                            <input type="text" id="searchPath" class="form-control input-sm" placeholder="Search path, location, or prefecture..." style="width: 280px">
                        </div>
                        <div class="form-group" style="margin-right: 10px">
                            <select id="filterType" class="form-control input-sm">
                                <option value="">All Types</option>
                                <option value="Prefecture/Area">Prefecture</option>
                                <option value="Area">Area</option>
                                <option value="Station">Station</option>
                                <option value="Category">Category</option>
                                <option value="Daily Pay">Daily Pay</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-right: 10px">
                            <select id="filterStatus" class="form-control input-sm">
                                <option value="">All Statuses</option>
                                <option value="No Jobs">No Jobs</option>
                                <option value="Low Supply">Low Supply</option>
                                <option value="OK">OK</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-right: 10px">
                            <label for="minSessions" style="margin-right: 4px; font-weight: normal">Min Sessions:</label>
                            <input type="number" id="minSessions" class="form-control input-sm" value="0" min="0" style="width: 80px">
                        </div>
                        <div class="form-group" style="margin-right: 10px">
                            <label for="sortBy" style="margin-right: 4px; font-weight: normal">Sort:</label>
                            <select id="sortBy" class="form-control input-sm">
                                <option value="default">Default (Urgency)</option>
                                <option value="sessions">Sessions (High→Low)</option>
                                <option value="gap_score">Gap Score (High→Low)</option>
                                <option value="active_jobs_asc">Jobs (Low→High)</option>
                                <option value="active_jobs_desc">Jobs (High→Low)</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-right: 10px">
                            <label for="perPage" style="margin-right: 4px; font-weight: normal">Show:</label>
                            <select id="perPage" class="form-control input-sm">
                                <option value="25">25</option>
                                <option value="50" selected>50</option>
                                <option value="100">100</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                        <button type="button" id="resetFilters" class="btn btn-sm btn-default">
                            <i class="fa fa-refresh"></i> Reset
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Results Info ── --}}
    <div class="row">
        <div class="col-lg-12">
            <div style="margin-bottom: 8px; font-size: 13px; color: #888">
                Showing <strong id="showingCount">0</strong> of <strong id="totalFiltered">0</strong> pages
                <span id="paginationInfo" style="margin-left: 8px"></span>
            </div>
        </div>
    </div>

    {{-- ── Main Table ── --}}
    <div class="row">
        <div class="col-lg-12">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped table-condensed" id="demandTable">
                    <thead style="position: sticky; top: 0; z-index: 10; background: #f5f5f5">
                        <tr>
                            <th style="width: 30px">#</th>
                            <th style="cursor: pointer" data-sort="page_path">Page Path <i class="fa fa-sort text-muted"></i></th>
                            <th style="width: 100px; cursor: pointer" data-sort="page_type">Type <i class="fa fa-sort text-muted"></i></th>
                            <th style="cursor: pointer" data-sort="location">Location <i class="fa fa-sort text-muted"></i></th>
                            <th style="width: 80px; cursor: pointer; text-align: center" data-sort="sessions">Sessions <i class="fa fa-sort text-muted"></i></th>
                            <th style="width: 80px; cursor: pointer; text-align: center" data-sort="pageviews">Views <i class="fa fa-sort text-muted"></i></th>
                            <th style="width: 80px; cursor: pointer; text-align: center" data-sort="active_jobs">Jobs <i class="fa fa-sort text-muted"></i></th>
                            <th style="width: 70px; cursor: pointer; text-align: center" data-sort="gap_score">Gap <i class="fa fa-sort text-muted"></i></th>
                            <th style="width: 80px; text-align: center">Status</th>
                            <th style="width: 180px; text-align: center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="demandTableBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Pagination ── --}}
    <div class="row">
        <div class="col-lg-12 text-center">
            <ul class="pagination pagination-sm" id="pagination"></ul>
        </div>
    </div>

    {{-- ── Upload Modal ── --}}
    <div class="modal fade" id="uploadModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-cloud-upload"></i> Upload GA4 Landing Page CSV</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-7">
                            <form method="POST" action="{{ url('admin/analytics/upload-ga4') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="form-group">
                                    <label for="csv_file">CSV File</label>
                                    <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                                </div>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label for="ga4_from">Date From</label>
                                            <input type="date" id="ga4_from" name="ga4_from" class="form-control input-sm"
                                                value="{{ $lastUpload ? $lastUpload->date_from : now()->subDays(29)->format('Y-m-d') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label for="ga4_to">Date To</label>
                                            <input type="date" id="ga4_to" name="ga4_to" class="form-control input-sm"
                                                value="{{ $lastUpload ? $lastUpload->date_to : now()->format('Y-m-d') }}" required>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fa fa-cloud-upload"></i> Upload &amp; Analyze
                                </button>
                            </form>
                        </div>
                        <div class="col-md-5">
                            <div class="panel panel-info" style="margin-bottom: 0">
                                <div class="panel-heading"><i class="fa fa-question-circle"></i> How to Export from GA4</div>
                                <div class="panel-body" style="font-size: 12px">
                                    <ol style="padding-left: 16px; margin-bottom: 0">
                                        <li>Go to <strong>GA4 &rarr; Reports &rarr; Pages and screens</strong></li>
                                        <li>Change primary dimension to <strong>"Landing page"</strong></li>
                                        <li>Set the date range (top right)</li>
                                        <li>Show <strong>all rows</strong> (bottom, set to max)</li>
                                        <li>Click <strong>share</strong> icon &rarr; <strong>"Download file"</strong></li>
                                        <li>Choose <strong>CSV</strong></li>
                                        <li>Upload here with matching dates</li>
                                    </ol>
                                </div>
                            </div>

                            @if ($uploadHistory->isNotEmpty())
                            <div style="margin-top: 12px">
                                <strong style="font-size: 12px"><i class="fa fa-history"></i> Upload History</strong>
                                <table class="table table-condensed" style="font-size: 11px; margin-top: 4px; margin-bottom: 0">
                                    @foreach ($uploadHistory as $h)
                                    <tr>
                                        <td>{{ $h->date_from }} &mdash; {{ $h->date_to }}</td>
                                        <td>{{ number_format($h->row_count) }} rows</td>
                                        <td class="text-muted">{{ date('M d H:i', strtotime($h->uploaded_at)) }}</td>
                                    </tr>
                                    @endforeach
                                </table>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('page-styles')
<style>
    .panel-yellow { border-color: #f0ad4e; }
    .panel-yellow > .panel-heading { border-color: #f0ad4e; background-color: #f0ad4e; color: white; }
    .panel-green { border-color: #5cb85c; }
    .panel-green > .panel-heading { border-color: #5cb85c; background-color: #5cb85c; color: white; }
    .huge { font-size: 30px; }

    #demandTable thead th { white-space: nowrap; font-size: 12px; }
    #demandTable tbody td { font-size: 12px; vertical-align: middle; }
    #demandTable tbody tr.row-danger { background-color: #f2dede; }
    #demandTable tbody tr.row-warning { background-color: #fcf8e3; }
    #demandTable tbody tr:hover { background-color: #d9edf7 !important; }

    .btn-xs-action { padding: 2px 5px; font-size: 11px; margin: 1px; }

    #statusTabs > li > a { padding: 8px 12px; font-size: 13px; }
    #statusTabs > li > a .badge { font-size: 10px; margin-left: 3px; }
    .badge-danger { background-color: #d9534f; }
    .badge-warning { background-color: #f0ad4e; }
    .badge-success { background-color: #5cb85c; }

    .sort-active { color: #337ab7 !important; }
</style>
@endpush

@push('page-scripts')
<script>
(function() {
    // ── Data from server ──
    var allData = @json($demandSupply);
    var prefectureJp = @json($prefectureJp);
    var prefectureEn = @json($prefectureEn);
    var areaJp = @json($areaJp);
    var baseUrl = ($('#base_url').val() || '').replace(/\/+$/, '');

    // Build reverse lookup: prefecture name (lowercase) → prefecture id
    var prefIdByName = {};
    $.each(prefectureEn, function(id, name) {
        prefIdByName[name.toLowerCase()] = parseInt(id);
    });

    var filteredData = allData.slice();
    var currentPage = 1;
    var currentSort = null;
    var currentSortDir = 'asc';
    var activeTab = 'all';

    // ── Rendering ──
    function render() {
        applyFilters();
        applySort();

        var perPage = $('#perPage').val();
        var total = filteredData.length;
        var start, end, pageData;

        if (perPage === 'all') {
            start = 0;
            end = total;
            pageData = filteredData;
        } else {
            perPage = parseInt(perPage);
            var totalPages = Math.ceil(total / perPage);
            if (currentPage > totalPages) currentPage = totalPages || 1;
            start = (currentPage - 1) * perPage;
            end = Math.min(start + perPage, total);
            pageData = filteredData.slice(start, end);
        }

        // Render table rows
        var tbody = $('#demandTableBody');
        tbody.empty();

        if (pageData.length === 0) {
            tbody.append('<tr><td colspan="10" class="text-center text-muted" style="padding: 30px">No data. Upload a GA4 CSV to see demand vs supply analysis.</td></tr>');
        } else {
            for (var i = 0; i < pageData.length; i++) {
                var row = pageData[i];
                var idx = start + i + 1;
                var rowClass = row.status === 'No Jobs' ? 'row-danger' : (row.status === 'Low Supply' ? 'row-warning' : '');
                var jobsHtml, gapHtml;

                if (row.active_jobs === 0) {
                    jobsHtml = '<span class="text-danger"><strong>0</strong></span>';
                } else if (row.active_jobs < 5) {
                    jobsHtml = '<span class="text-warning"><strong>' + row.active_jobs + '</strong></span>';
                } else {
                    jobsHtml = row.active_jobs;
                }

                if (row.gap_score !== null) {
                    gapHtml = row.gap_score;
                } else {
                    gapHtml = '<span class="text-danger">&infin;</span>';
                }

                var actions = buildActions(row);

                tbody.append(
                    '<tr class="' + rowClass + '">' +
                        '<td>' + idx + '</td>' +
                        '<td><a href="' + baseUrl + row.page_path + '" target="_blank" style="font-size: 11px">' + escHtml(row.page_path) + '</a></td>' +
                        '<td><span class="label label-default">' + escHtml(row.page_type) + '</span></td>' +
                        '<td>' + escHtml(row.location) + '</td>' +
                        '<td class="text-center">' + numberFormat(row.sessions) + '</td>' +
                        '<td class="text-center">' + numberFormat(row.pageviews) + '</td>' +
                        '<td class="text-center">' + jobsHtml + '</td>' +
                        '<td class="text-center">' + gapHtml + '</td>' +
                        '<td class="text-center"><span class="label label-' + row.status_class + '">' + row.status + '</span></td>' +
                        '<td class="text-center">' + actions + '</td>' +
                    '</tr>'
                );
            }
        }

        // Update counts
        $('#showingCount').text(pageData.length);
        $('#totalFiltered').text(total);

        // Render pagination
        renderPagination(total, perPage === 'all' ? total : parseInt($('#perPage').val()));
    }

    function buildActions(row) {
        var btns = '';

        // View page
        btns += '<a href="' + baseUrl + row.page_path + '" target="_blank" class="btn btn-default btn-xs-action" title="View Page"><i class="fa fa-external-link"></i></a>';

        // Create Job - pre-fill location
        var createParams = [];
        if (row.prefecture_id) createParams.push('prefecture_id=' + row.prefecture_id);
        if (row.area_id) createParams.push('area_id=' + row.area_id);
        if (row.category_ids && row.category_ids.length) createParams.push('category_id=' + row.category_ids[0]);
        var createUrl = baseUrl + '/admin/jobs/create' + (createParams.length ? '?' + createParams.join('&') : '');
        btns += ' <a href="' + createUrl + '" target="_blank" class="btn btn-success btn-xs-action" title="Create Job"><i class="fa fa-plus"></i> Job</a>';

        // Create from XML - pre-fill location
        var xmlUrl = baseUrl + '/admin/jobs/create-from-xml' + (createParams.length ? '?' + createParams.join('&') : '');
        btns += ' <a href="' + xmlUrl + '" target="_blank" class="btn btn-info btn-xs-action" title="Create from XML"><i class="fa fa-code"></i> XML</a>';

        // Search Shigotoin - use Japanese name
        var searchKeyword = '';
        if (row.area_id && areaJp[row.area_id]) {
            searchKeyword = areaJp[row.area_id];
        } else if (row.prefecture_id && prefectureJp[row.prefecture_id]) {
            searchKeyword = prefectureJp[row.prefecture_id];
        } else if (row.station) {
            searchKeyword = row.station;
        }
        if (searchKeyword) {
            btns += ' <a href="https://shigotoin.com/search?keyword=' + encodeURIComponent(searchKeyword) + '" target="_blank" class="btn btn-warning btn-xs-action" title="Search Shigotoin"><i class="fa fa-search"></i></a>';
        }

        return btns;
    }

    // ── Filtering ──
    function applyFilters() {
        var search = $('#searchPath').val().toLowerCase().trim();
        var filterType = $('#filterType').val();
        var filterStatus = $('#filterStatus').val();
        var minSessions = parseInt($('#minSessions').val()) || 0;

        filteredData = allData.filter(function(row) {
            // Tab filter
            if (activeTab !== 'all') {
                if (activeTab.indexOf('type:') === 0) {
                    if (row.page_type !== activeTab.substring(5)) return false;
                } else {
                    if (row.status !== activeTab) return false;
                }
            }
            // Search — match page_path, location, or prefecture name (e.g., "tokyo" finds all Tokyo areas/stations)
            if (search) {
                var matchPath = row.page_path.toLowerCase().indexOf(search) !== -1;
                var matchLoc = row.location.toLowerCase().indexOf(search) !== -1;
                var matchPref = false;
                if (row.prefecture_id && prefectureEn[row.prefecture_id]) {
                    matchPref = prefectureEn[row.prefecture_id].toLowerCase().indexOf(search) !== -1;
                }
                if (!matchPath && !matchLoc && !matchPref) return false;
            }
            // Type dropdown
            if (filterType && row.page_type !== filterType) return false;
            // Status dropdown
            if (filterStatus && row.status !== filterStatus) return false;
            // Min sessions
            if (row.sessions < minSessions) return false;
            return true;
        });
    }

    // ── Sorting ──
    function applySort() {
        var sortBy = $('#sortBy').val();

        if (sortBy === 'default' && !currentSort) {
            // Server-side default order (urgency)
            return;
        }

        var key = currentSort || sortBy;
        var dir = currentSort ? currentSortDir : 'desc';

        if (key === 'default') return;

        // Named sort presets
        if (key === 'sessions') { key = 'sessions'; dir = 'desc'; }
        if (key === 'gap_score') { key = 'gap_score'; dir = 'desc'; }
        if (key === 'active_jobs_asc') { key = 'active_jobs'; dir = 'asc'; }
        if (key === 'active_jobs_desc') { key = 'active_jobs'; dir = 'desc'; }

        filteredData.sort(function(a, b) {
            var va = a[key], vb = b[key];
            // Handle nulls (gap_score can be null)
            if (va === null) va = dir === 'desc' ? -Infinity : Infinity;
            if (vb === null) vb = dir === 'desc' ? -Infinity : Infinity;
            // String comparison
            if (typeof va === 'string') {
                va = va.toLowerCase();
                vb = (vb || '').toLowerCase();
            }
            if (va < vb) return dir === 'asc' ? -1 : 1;
            if (va > vb) return dir === 'asc' ? 1 : -1;
            return 0;
        });
    }

    // ── Pagination ──
    function renderPagination(total, perPage) {
        var $pag = $('#pagination');
        $pag.empty();

        if (perPage >= total || perPage === 0) {
            $('#paginationInfo').text('');
            return;
        }

        var totalPages = Math.ceil(total / perPage);
        $('#paginationInfo').text('Page ' + currentPage + ' of ' + totalPages);

        // First / Prev
        $pag.append('<li' + (currentPage === 1 ? ' class="disabled"' : '') + '><a href="#" data-page="1">&laquo;</a></li>');
        $pag.append('<li' + (currentPage === 1 ? ' class="disabled"' : '') + '><a href="#" data-page="' + (currentPage - 1) + '">&lsaquo;</a></li>');

        // Page numbers (show 7 around current)
        var startPage = Math.max(1, currentPage - 3);
        var endPage = Math.min(totalPages, currentPage + 3);
        for (var p = startPage; p <= endPage; p++) {
            $pag.append('<li' + (p === currentPage ? ' class="active"' : '') + '><a href="#" data-page="' + p + '">' + p + '</a></li>');
        }

        // Next / Last
        $pag.append('<li' + (currentPage === totalPages ? ' class="disabled"' : '') + '><a href="#" data-page="' + (currentPage + 1) + '">&rsaquo;</a></li>');
        $pag.append('<li' + (currentPage === totalPages ? ' class="disabled"' : '') + '><a href="#" data-page="' + totalPages + '">&raquo;</a></li>');
    }

    // ── Event Handlers ──

    // Tab clicks
    $('#statusTabs a').on('click', function(e) {
        e.preventDefault();
        $('#statusTabs li').removeClass('active');
        $(this).parent().addClass('active');
        activeTab = $(this).data('filter');
        // Sync dropdowns
        if (activeTab.indexOf('type:') === 0) {
            $('#filterType').val(activeTab.substring(5));
            $('#filterStatus').val('');
        } else if (activeTab === 'all') {
            $('#filterType').val('');
            $('#filterStatus').val('');
        } else {
            $('#filterStatus').val(activeTab);
            $('#filterType').val('');
        }
        currentPage = 1;
        currentSort = null;
        render();
    });

    // Filter changes
    $('#searchPath, #filterType, #filterStatus, #minSessions, #sortBy, #perPage').on('change input', function() {
        currentPage = 1;
        currentSort = null;
        render();
    });

    // Reset filters
    $('#resetFilters').on('click', function() {
        $('#searchPath').val('');
        $('#filterType').val('');
        $('#filterStatus').val('');
        $('#minSessions').val(0);
        $('#sortBy').val('default');
        $('#perPage').val('50');
        activeTab = 'all';
        currentPage = 1;
        currentSort = null;
        $('#statusTabs li').removeClass('active').first().addClass('active');
        render();
    });

    // Sortable column headers
    $('#demandTable thead th[data-sort]').on('click', function() {
        var key = $(this).data('sort');
        if (currentSort === key) {
            currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort = key;
            currentSortDir = (key === 'sessions' || key === 'pageviews' || key === 'gap_score') ? 'desc' : 'asc';
        }
        // Reset the sort dropdown to avoid conflicts
        $('#sortBy').val('default');
        render();

        // Update sort indicators
        $('#demandTable thead th i.fa').removeClass('fa-sort-asc fa-sort-desc sort-active').addClass('fa-sort text-muted');
        $(this).find('i.fa').removeClass('fa-sort text-muted').addClass(currentSortDir === 'asc' ? 'fa-sort-asc sort-active' : 'fa-sort-desc sort-active');
    });

    // Pagination clicks
    $(document).on('click', '#pagination a', function(e) {
        e.preventDefault();
        var page = parseInt($(this).data('page'));
        var perPage = parseInt($('#perPage').val());
        var totalPages = Math.ceil(filteredData.length / perPage);
        if (page >= 1 && page <= totalPages) {
            currentPage = page;
            render();
            // Scroll to top of table
            $('html, body').animate({ scrollTop: $('#demandTable').offset().top - 60 }, 200);
        }
    });

    // ── Helpers ──
    function escHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function numberFormat(n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // ── Init ──
    render();
})();
</script>
@endpush
