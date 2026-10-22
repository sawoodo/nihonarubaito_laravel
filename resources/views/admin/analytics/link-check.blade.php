@php use App\Support\Tz; @endphp
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
                            @if (!empty($preview['uploaded_at'])) · uploaded {{ Tz::pkt($preview['uploaded_at']) }} @endif
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
                            @if ($c['alreadyApplied'] > 0)
                                <li><strong>{{ number_format($c['alreadyApplied']) }}</strong> already extended from this scan result (or a newer one) — skipped, so re-uploading the same file changes nothing</li>
                            @endif
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
            @include('admin.analytics.partials.group-card', ['g' => $g, 'showDone' => false])
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
                                @include('admin.analytics.partials.review-row', ['r' => $r, 'showDone' => false])
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif
        {{-- ── Already applied ── --}}
        @if ($c['alreadyApplied'] > 0)
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <i class="fa fa-check-circle-o"></i>
                        Already extended from this scan — {{ number_format($c['alreadyApplied']) }} job(s), nothing to do
                    </div>
                    <div class="table-responsive" style="max-height: 300px; overflow: auto">
                        <table class="table table-condensed table-striped" style="margin-bottom: 0">
                            <thead><tr><th>Job</th><th>Result</th><th>Title</th><th>Scan checked (PKT)</th><th>Already applied (PKT)</th></tr></thead>
                            <tbody>
                            @foreach ($preview['alreadyApplied'] as $r)
                                <tr>
                                    <td>{{ $r['job_no'] }}</td>
                                    <td><span class="label label-default">{{ $r['class'] }}</span></td>
                                    <td>{{ Str::limit($r['title'], 60) }}</td>
                                    <td style="white-space:nowrap">{{ Tz::pkt($r['checked_at'], 'd M, H:i') }}</td>
                                    <td style="white-space:nowrap">{{ Tz::pkt($r['applied_at'], 'd M, H:i') }}</td>
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

    {{-- ── Open items: work saved from earlier uploads ── --}}
    @if ($open && ($open['counts']['groups'] > 0 || $open['counts']['review'] > 0))
        @php $o = $open['counts']; @endphp
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <i class="fa fa-inbox"></i> Open items — saved from earlier uploads
                    </div>
                    <div class="panel-body">
                        <strong>{{ number_format($o['review']) }}</strong> job(s) for review
                        and <strong>{{ number_format($o['groups']) }}</strong> duplicate group(s)
                        covering {{ number_format($o['groupJobs']) }} job(s).
                        @if ($o['groupsDiff'] > 0)
                            <span class="text-danger">{{ $o['groupsDiff'] }} of the groups are <strong>different jobs</strong>, not duplicates.</span>
                        @endif
                        <br><span class="text-muted">Items disappear automatically once the job is retired, relinked or the group stops being a duplicate.</span>
                    </div>
                </div>
            </div>
        </div>

        @if ($o['review'] > 0)
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-warning">
                    <div class="panel-heading"><i class="fa fa-exclamation-triangle"></i> For review — {{ $o['review'] }} job(s)</div>
                    <div class="table-responsive" style="max-height: 420px; overflow: auto">
                        <table class="table table-condensed table-striped" style="margin-bottom: 0">
                            <thead><tr><th>Job</th><th>Result</th><th>Title</th><th>Link / note</th><th>Actions</th></tr></thead>
                            <tbody>
                            @foreach ($open['review'] as $r)
                                @include('admin.analytics.partials.review-row', ['r' => $r, 'showDone' => true])
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if ($o['groups'] > 0)
        <div class="row">
            <div class="col-lg-12">
                <h4 style="margin-bottom:10px">
                    <i class="fa fa-clone"></i> Same final offer — {{ $o['groups'] }} group(s), {{ $o['groupJobs'] }} job(s)
                    @if ($o['capped'])
                        <small class="text-muted">showing the first {{ $o['shown'] }}</small>
                        <a href="{{ route('admin.analytics.link-check', ['all' => 1]) }}" class="btn btn-xs btn-default">Show all {{ $o['groups'] }}</a>
                    @endif
                </h4>
            </div>
        </div>
        @foreach ($open['groups'] as $g)
            @include('admin.analytics.partials.group-card', ['g' => $g, 'showDone' => true])
        @endforeach
        @if ($o['capped'])
        <div class="row"><div class="col-lg-12" style="margin-bottom:15px">
            <a href="{{ route('admin.analytics.link-check', ['all' => 1]) }}" class="btn btn-default">
                Show all {{ $o['groups'] }} groups
            </a>
        </div></div>
        @endif
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
                        <thead><tr><th>Batch</th><th>Type</th><th>By</th><th>Jobs</th><th>When (PKT)</th></tr></thead>
                        <tbody>
                        @foreach ($recent as $b)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.analytics.link-check.batch', ['batch' => $b->batch]) }}">
                                        {{ $b->batch }}
                                    </a>
                                </td>
                                <td><span class="label label-{{ ($b->source ?? '') === 'bulk-expire' ? 'danger' : 'default' }}">{{ $b->source ?? 'link-check' }}</span></td>
                                <td>{{ $b->actor }}</td>
                                <td>{{ $b->n }}</td>
                                <td style="white-space:nowrap">{{ Tz::pkt($b->at, 'd M Y, H:i', '') }}</td>
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
