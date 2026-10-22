@extends('layouts.admin')

@section('title', 'Link Check')

@section('content')
{{-- layouts.admin already opens #page-wrapper (margin-left:250px at >=768px).
     Opening a second one here nests the rule and indents the page by 500px. --}}

    {{-- ── Page Header ── --}}
    <div class="row">
        <div class="col-lg-12">
            <h3 class="page-header" style="margin-bottom: 5px">
                <i class="fa fa-link fa-fw"></i> Link Check — extend jobs still live
            </h3>
            <p class="text-muted" style="margin-bottom: 15px">
                Upload the results CSV from the Mac-side scan. Jobs confirmed still live are
                extended by 7 days; moved jobs also get their apply link updated.
                Closed, gone and errored jobs are listed only — they are never expired here.
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
        </div>
    </div>

    {{-- ── Upload ── --}}
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading"><i class="fa fa-upload"></i> Upload results CSV</div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('admin.analytics.link-check.preview') }}"
                          enctype="multipart/form-data" class="form-inline">
                        @csrf
                        <div class="form-group" style="margin-right: 10px">
                            <input type="file" name="csv_file" accept=".csv,text/csv" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-eye"></i> Preview changes
                        </button>
                        <span class="text-muted" style="margin-left: 12px">
                            Nothing is saved until you confirm on the next screen.
                        </span>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if ($preview)
        @php $c = $preview['counts']; @endphp

        {{-- ── Summary ── --}}
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-info">
                    <div class="panel-heading"><i class="fa fa-list"></i> Preview</div>
                    <div class="panel-body">
                        <p class="text-muted" style="margin-bottom:10px">
                            <i class="fa fa-file-text-o"></i>
                            <strong>{{ $preview['file_name'] }}</strong>
                            — {{ number_format($c['rows']) }} job(s)
                            @if (!empty($preview['uploaded_at'])) · uploaded {{ $preview['uploaded_at'] }} @endif
                        </p>
                        <p>
                            <strong>{{ number_format($c['rows']) }}</strong> job(s) in the file.
                            <strong class="text-success">{{ number_format($c['apply']) }}</strong> will be extended
                            ({{ number_format($c['open']) }} open, {{ number_format($c['moved']) }} moved —
                            moved jobs also get a new apply link).
                        </p>
                        <ul style="margin-bottom: 0">
                            <li><strong>{{ number_format($c['review']) }}</strong> for staff review (closed / gone / error / wrong target / stale) — <em>not</em> written
                                @if ($c['wrongTarget'] > 0)
                                    <br><span class="text-danger"><i class="fa fa-exclamation-triangle"></i>
                                    includes <strong>{{ $c['wrongTarget'] }}</strong> whose redirect lands on a different employer — never relinked</span>
                                @endif
                            </li>
                            <li><strong>{{ number_format($c['groups']) }}</strong> same-final-offer group(s) covering {{ number_format($c['groupJobs']) }} job(s) — <em>not</em> written
                                @if ($c['groupsDiff'] > 0)
                                    <br><span class="text-danger">{{ $c['groupsDiff'] }} of them are <strong>different jobs</strong>, not duplicates</span>
                                @endif
                            </li>
                            <li><strong>{{ number_format($c['notPublished']) }}</strong> no longer Published — skipped</li>
                            <li><strong>{{ number_format($c['notFound']) }}</strong> not found in the database — skipped</li>
                        </ul>
                    </div>
                    @if ($c['apply'] > 0)
                    <div class="panel-footer">
                        <form method="POST" action="{{ route('admin.analytics.link-check.apply') }}"
                              onsubmit="return confirm('Extend {{ $c['apply'] }} job(s) by 7 days?');">
                            @csrf
                            <input type="hidden" name="token" value="{{ $preview['token'] }}">
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-check"></i> Apply — extend {{ number_format($c['apply']) }} job(s)
                            </button>
                            <span class="text-muted" style="margin-left: 10px">
                                Each write is guarded on the job still being Published with the same apply link.
                            </span>
                        </form>
                        <form method="POST" action="{{ route('admin.analytics.link-check.cancel') }}"
                              style="display:inline-block; margin-left:8px"
                              onsubmit="return confirm('Discard this upload? Nothing will be changed.');">
                            @csrf
                            <input type="hidden" name="token" value="{{ $preview['token'] }}">
                            <button type="submit" class="btn btn-default"><i class="fa fa-times"></i> Cancel</button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Will be changed ── --}}
        @if ($c['apply'] > 0)
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-success">
                    <div class="panel-heading"><i class="fa fa-calendar-plus-o"></i> Will be extended</div>
                    <div class="table-responsive" style="max-height: 460px; overflow: auto">
                        <table class="table table-condensed table-striped" style="margin-bottom: 0">
                            <thead>
                                <tr>
                                    <th>Job</th><th>Result</th><th>Title</th>
                                    <th>delete_at</th><th>Apply link</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($preview['apply'] as $r)
                                <tr>
                                    <td>{{ $r['job_no'] }}</td>
                                    <td>
                                        <span class="label label-{{ $r['class'] === 'MOVED' ? 'warning' : 'success' }}">
                                            {{ $r['class'] }}
                                        </span>
                                    </td>
                                    <td style="max-width: 320px">{{ Str::limit($r['title'], 70) }}</td>
                                    <td style="white-space: nowrap">
                                        {{ $r['old_delete_at'] ? date('d M Y', strtotime($r['old_delete_at'])) : '—' }}
                                        &rarr;
                                        <strong>{{ date('d M Y', strtotime($r['new_delete_at'])) }}</strong>
                                    </td>
                                    <td style="font-size: 11px; word-break: break-all">
                                        @if ($r['new_link'] !== $r['current_link'])
                                            <span class="text-muted">{{ $r['current_link'] }}</span><br>
                                            &rarr; <strong>{{ $r['new_link'] }}</strong>
                                        @else
                                            <span class="text-muted">unchanged</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ── Same final offer: one card per group ── --}}
        @if ($c['groups'] > 0)
        <div class="row">
            <div class="col-lg-12">
                <h4 style="margin-bottom:10px">
                    <i class="fa fa-clone"></i> Same final offer — {{ $c['groups'] }} group(s), {{ $c['groupJobs'] }} job(s)
                    <small class="text-muted">none of these are written automatically</small>
                </h4>
            </div>
        </div>
        @foreach ($preview['groups'] as $g)
        <div class="row">
            <div class="col-lg-12">
                <div class="panel {{ $g['verdict'] === 'same' ? 'panel-warning' : 'panel-danger' }}">
                    <div class="panel-heading">
                        <strong>{{ $g['verdict_text'] }}</strong>
                        <span class="text-muted" style="margin-left:8px">{{ $g['evidence'] }}</span>
                        <a href="{{ $g['final'] }}" target="_blank" rel="noopener nofollow" class="pull-right">
                            {{ $g['final'] }} <i class="fa fa-external-link"></i>
                        </a>
                    </div>
                    <div class="panel-body" style="padding-bottom:6px">
                        <p style="margin-bottom:8px">
                            <span class="text-muted">shigotoin page:</span> {{ $g['page_title'] ?: '(title not captured)' }}
                        </p>
                        @if ($g['verdict'] !== 'same')
                            <div class="alert alert-danger" style="padding:8px 12px; margin-bottom:10px">
                                These are <strong>not duplicates</strong> — do not trash either.
                                @if ($g['owner'])
                                    The page belongs to <strong>job {{ $g['owner'] }}</strong>;
                                    the other job(s) have the wrong link stored and need fixing by hand via <em>Open job</em>.
                                @else
                                    None of our jobs matches the destination page, so every link here is suspect.
                                @endif
                            </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-condensed" style="margin-bottom:0">
                                <thead><tr>
                                    <th>Job</th><th>Age</th><th>Company</th><th>Title</th>
                                    <th>Address</th><th>Created</th><th>delete_at</th><th>Owns page?</th><th></th>
                                </tr></thead>
                                <tbody>
                                @foreach ($g['jobs'] as $j)
                                    <tr @if ($j['matches_page']) style="background:#f6fff6" @endif>
                                        <td>{{ $j['job_no'] }}</td>
                                        <td>
                                            @if (!empty($j['is_oldest']))
                                                <span class="label label-primary">OLDER</span>
                                            @else
                                                <span class="label label-default">newer</span>
                                            @endif
                                        </td>
                                        <td>{{ Str::limit($j['company'], 26) }}</td>
                                        <td>{{ Str::limit($j['title'], 46) }}</td>
                                        <td style="font-size:11px">{{ Str::limit($j['address'], 32) }}</td>
                                        <td style="white-space:nowrap">{{ $j['created'] ? date('d M Y', strtotime($j['created'])) : '—' }}</td>
                                        <td style="white-space:nowrap">{{ $j['delete_at'] ? date('d M Y', strtotime($j['delete_at'])) : '—' }}</td>
                                        <td>
                                            @if ($j['matches_page'])
                                                <span class="label label-success">YES</span>
                                            @else
                                                <span class="text-muted">no</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ url('admin/jobs/'.$j['job_no'].'/edit') }}" target="_blank"
                                               class="btn btn-xs btn-default">Open job</a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="panel-footer">
                        @if ($g['verdict'] === 'same')
                            @php $n = count($g['jobs']); @endphp
                            <form method="POST" action="{{ route('admin.analytics.link-check.group-action') }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="final_url" value="{{ $g['final'] }}">
                                <input type="hidden" name="job_nos" value="{{ $g['job_nos'] }}">
                                <button type="submit" name="action" value="keep_older" class="btn btn-sm btn-primary"
                                        onclick="return confirm('Keep the OLDEST job and trash the other {{ $n - 1 }}?');">
                                    Keep older (trash {{ $n - 1 }})
                                </button>
                                <button type="submit" name="action" value="keep_newer" class="btn btn-sm btn-default"
                                        onclick="return confirm('Keep the NEWEST job and trash the other {{ $n - 1 }}?');">
                                    Keep newer (trash {{ $n - 1 }})
                                </button>
                                <button type="submit" name="action" value="not_duplicate" class="btn btn-sm btn-link"
                                        onclick="return confirm('Mark as NOT a duplicate? This group will not be shown again.');">
                                    Not a duplicate
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.analytics.link-check.group-action') }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="final_url" value="{{ $g['final'] }}">
                                <input type="hidden" name="job_nos" value="{{ $g['job_nos'] }}">
                                <button type="submit" name="action" value="not_duplicate" class="btn btn-sm btn-default"
                                        onclick="return confirm('Mark as NOT a duplicate? This group will not be shown again.');">
                                    Not a duplicate — stop showing this
                                </button>
                            </form>
                            <span class="text-muted" style="margin-left:10px">
                                Keep/trash is deliberately unavailable: these are different jobs.
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
        @endif

        {{-- ── Staff review ── --}}
        @if ($c['review'] > 0)
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-warning">
                    <div class="panel-heading">
                        <i class="fa fa-exclamation-triangle"></i> For staff review — nothing written for these
                    </div>
                    <div class="table-responsive" style="max-height: 400px; overflow: auto">
                        <table class="table table-condensed table-striped" style="margin-bottom: 0">
                            <thead><tr><th>Job</th><th>Result</th><th>Title</th><th>Link / note</th><th>Actions</th></tr></thead>
                            <tbody>
                            @foreach ($preview['review'] as $r)
                                <tr @if ($r['class'] === 'WRONG_TARGET') class="danger" @endif>
                                    <td>{{ $r['job_no'] }}</td>
                                    <td>
                                        <span class="label label-{{ $r['class'] === 'WRONG_TARGET' ? 'danger' : 'default' }}">{{ $r['class'] }}</span>
                                        <br><small class="text-muted">status {{ $r['status'] }}</small>
                                    </td>
                                    <td style="max-width: 260px">
                                        {{ Str::limit($r['title'], 58) }}
                                        <br><small class="text-muted">{{ Str::limit($r['company'] ?? '', 30) }}</small>
                                    </td>
                                    <td style="font-size: 11px; word-break: break-all">
                                        <a href="{{ $r['url'] }}" target="_blank" rel="noopener nofollow">{{ $r['url'] }}</a>
                                        @if ($r['class'] === 'WRONG_TARGET' && !empty($r['final_url']))
                                            <br><span class="text-danger">redirects to {{ $r['final_url'] }}</span>
                                            @if (!empty($r['page_title']))
                                                <br><em class="text-muted">{{ Str::limit($r['page_title'], 90) }}</em>
                                            @endif
                                        @endif
                                        @if (!empty($r['note']))<br><em class="text-muted">{{ $r['note'] }}</em>@endif
                                    </td>
                                    <td style="white-space:nowrap">
                                        @if ($r['status'] === 3)
                                            <form method="POST" action="{{ route('admin.analytics.link-check.row-action') }}" style="display:inline">
                                                @csrf
                                                <input type="hidden" name="job_no" value="{{ $r['job_no'] }}">
                                                <input type="hidden" name="current_link" value="{{ $r['current_link'] }}">
                                                <button type="submit" name="action" value="expire" class="btn btn-xs btn-danger"
                                                        onclick="return confirm('Expire job {{ $r['job_no'] }}? It stops being listed.');">Expire</button>
                                                <button type="submit" name="action" value="extend" class="btn btn-xs btn-success"
                                                        onclick="return confirm('Give job {{ $r['job_no'] }} another 7 days?');">Extend 7d</button>
                                            </form>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                        <a href="{{ url('admin/jobs/'.$r['job_no'].'/edit') }}" target="_blank" class="btn btn-xs btn-default">Open job</a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif
    @endif

    {{-- ── Recent runs ── --}}
    @if (!empty($recent))
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading"><i class="fa fa-history"></i> Recent link-check runs</div>
                <div class="table-responsive">
                    <table class="table table-condensed" style="margin-bottom: 0">
                        <thead><tr><th>Batch</th><th>By</th><th>Jobs</th><th>When</th></tr></thead>
                        <tbody>
                        @foreach ($recent as $b)
                            <tr>
                                <td>{{ $b->batch }}</td>
                                <td>{{ $b->actor }}</td>
                                <td>{{ $b->n }}</td>
                                <td>{{ $b->at ? date('d M Y, H:i', strtotime($b->at)) : '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

@endsection
