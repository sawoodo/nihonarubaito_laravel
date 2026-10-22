@php use App\Support\Tz; @endphp
@extends('layouts.admin')

@section('title', 'Link Check — batch')

@section('content')

    <div class="row">
        <div class="col-lg-12">
            <h3 class="page-header" style="margin-bottom: 5px">
                <i class="fa fa-history fa-fw"></i> Batch {{ $batch }}
                <a href="{{ route('admin.analytics.link-check') }}" class="btn btn-sm btn-default pull-right" style="margin-top:4px">
                    <i class="fa fa-arrow-left"></i> Link Check
                </a>
            </h3>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-info">
                <div class="panel-heading"><i class="fa fa-list"></i> What this run changed</div>
                <div class="panel-body">
                    <p style="margin-bottom:6px">
                        <span class="label label-{{ $summary['source'] === 'bulk-expire' ? 'danger' : 'default' }}">{{ $summary['source'] }}</span>
                        by <strong>{{ $summary['actor'] }}</strong>
                        @if ($summary['at']) on {{ Tz::pkt($summary['at']) }} @endif
                    </p>
                    <ul style="margin-bottom:0">
                        <li><strong>{{ number_format($summary['rows']) }}</strong> job(s) changed</li>
                        @if ($summary['extended'] > 0)
                            <li><strong>{{ number_format($summary['extended']) }}</strong> extended (status unchanged)</li>
                        @endif
                        @if ($summary['expired'] > 0)
                            <li><strong>{{ number_format($summary['expired']) }}</strong> expired (status 4)</li>
                        @endif
                        @if ($summary['trashed'] > 0)
                            <li><strong>{{ number_format($summary['trashed']) }}</strong> trashed (status 5)</li>
                        @endif
                        @if ($summary['relinks'] > 0)
                            <li><strong>{{ number_format($summary['relinks']) }}</strong> apply link(s) rewritten</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @if ($summary['relinks'] > 0)
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-warning">
                <div class="panel-heading"><i class="fa fa-link"></i> Apply links rewritten ({{ $links->count() }})</div>
                <div class="table-responsive" style="max-height: 360px; overflow: auto">
                    <table class="table table-condensed table-striped" style="margin-bottom:0">
                        <thead><tr><th>Job</th><th>Old link</th><th>New link</th><th>HTTP</th><th>Hops</th></tr></thead>
                        <tbody>
                        @foreach ($links as $l)
                            <tr>
                                <td><a href="{{ url('admin/jobs/'.$l->job_no.'/edit') }}" target="_blank">{{ $l->job_no }}</a></td>
                                <td style="font-size:11px; word-break:break-all">{{ $l->old_apply_link }}</td>
                                <td style="font-size:11px; word-break:break-all"><strong>{{ $l->new_apply_link }}</strong></td>
                                <td>{{ $l->http_code }}</td>
                                <td>{{ $l->num_redirects }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading"><i class="fa fa-exchange"></i> Every change in this batch ({{ $status->count() }})</div>
                <div class="table-responsive" style="max-height: 560px; overflow: auto">
                    <table class="table table-condensed table-striped" style="margin-bottom:0">
                        <thead><tr>
                            <th>Job</th><th>Title</th><th>Status</th><th>delete_at</th>
                            <th>Now</th><th>Reason</th><th>Scan checked (PKT)</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($status as $h)
                            <tr>
                                <td><a href="{{ url('admin/jobs/'.$h->job_no.'/edit') }}" target="_blank">{{ $h->job_no }}</a></td>
                                <td style="max-width:280px">{{ Str::limit($h->title ?? '(job not found)', 58) }}</td>
                                <td style="white-space:nowrap">
                                    @if ($h->old_status === $h->new_status)
                                        <span class="text-muted">{{ $h->old_status }} (unchanged)</span>
                                    @else
                                        {{ $h->old_status }} &rarr; <strong>{{ $h->new_status }}</strong>
                                    @endif
                                </td>
                                <td style="white-space:nowrap">
                                    @if ($h->old_delete_at && $h->new_delete_at && $h->old_delete_at !== $h->new_delete_at)
                                        {{ date('d M', strtotime($h->old_delete_at)) }} &rarr;
                                        <strong>{{ date('d M Y', strtotime($h->new_delete_at)) }}</strong>
                                    @elseif ($h->new_delete_at)
                                        <span class="text-muted">{{ date('d M Y', strtotime($h->new_delete_at)) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $h->job_status_id ?? '—' }}</td>
                                <td style="font-size:11px">{{ Str::limit($h->reason, 60) }}</td>
                                <td style="font-size:11px; white-space:nowrap">
                                    {{ Tz::pkt($h->source_checked_at, 'd M, H:i') }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

@endsection
