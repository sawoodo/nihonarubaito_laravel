@extends('layouts.admin')

@section('title', 'Link Check')

@section('content')
<div id="page-wrapper" style="min-height: 800px">

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

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

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
                        <p>
                            <strong>{{ number_format($c['rows']) }}</strong> job(s) in the file.
                            <strong class="text-success">{{ number_format($c['apply']) }}</strong> will be extended
                            ({{ number_format($c['open']) }} open, {{ number_format($c['moved']) }} moved —
                            moved jobs also get a new apply link).
                        </p>
                        <ul style="margin-bottom: 0">
                            <li><strong>{{ number_format($c['review']) }}</strong> for staff review (closed / gone / error / stale) — <em>not</em> written</li>
                            <li><strong>{{ number_format($c['collisions']) }}</strong> flagged: two of our jobs resolve to the same final offer — <em>not</em> written</li>
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

        {{-- ── Same final offer ── --}}
        @if ($c['collisions'] > 0)
        <div class="row">
            <div class="col-lg-12">
                <div class="panel panel-danger">
                    <div class="panel-heading">
                        <i class="fa fa-clone"></i> Two of our jobs resolve to the same final offer — review by hand
                    </div>
                    <div class="table-responsive" style="max-height: 320px; overflow: auto">
                        <table class="table table-condensed" style="margin-bottom: 0">
                            <thead><tr><th>Job</th><th>Title</th><th>Also</th><th>Final offer</th></tr></thead>
                            <tbody>
                            @foreach ($preview['collisions'] as $r)
                                <tr>
                                    <td>{{ $r['job_no'] }}</td>
                                    <td>{{ Str::limit($r['title'], 60) }}</td>
                                    <td>{{ implode(', ', $r['others']) }}</td>
                                    <td style="font-size: 11px; word-break: break-all">{{ $r['final'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
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
                            <thead><tr><th>Job</th><th>Result</th><th>Status</th><th>Title</th><th>Link / note</th></tr></thead>
                            <tbody>
                            @foreach ($preview['review'] as $r)
                                <tr>
                                    <td>{{ $r['job_no'] }}</td>
                                    <td><span class="label label-default">{{ $r['class'] }}</span></td>
                                    <td>{{ $r['status'] }}</td>
                                    <td style="max-width: 300px">{{ Str::limit($r['title'], 60) }}</td>
                                    <td style="font-size: 11px; word-break: break-all">
                                        <a href="{{ $r['url'] }}" target="_blank" rel="noopener nofollow">{{ $r['url'] }}</a>
                                        @if (!empty($r['note']))<br><em class="text-muted">{{ $r['note'] }}</em>@endif
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

</div>
@endsection
