{{--
  The import duplicate card. Shared by the single XML import and manual create.

  Expects session('job_matches'):
    certain[]       matches that block creation
    review[]        matches shown for information only
    new_link        the apply link being imported
    delete_in_days  the review window the form asked for
    return_to       'xml' | 'manual'

  A 'certain' match gets the "Update job N with this link" button. A 'review'
  match never does: offer 118120821 is stored on two unrelated employers
  because a wrong link was pasted onto one of them, so one click would relink
  the wrong job. Review says "check it" and leaves the decision to a person.
--}}
@php
    $m = session('job_matches');
    $certain = $m['certain'] ?? [];
    $review = $m['review'] ?? [];
@endphp

@if ($certain)
    <div class="panel panel-danger tw-mb-4">
        <div class="panel-heading">
            <strong>
                <i class="fa fa-clone"></i>
                @if (count($certain) === 1)
                    This looks like job #{{ $certain[0]['job_no'] }}
                @else
                    This looks like {{ count($certain) }} jobs we already have
                @endif
            </strong>
        </div>
        <div class="panel-body">
            <p class="tw-mb-3">
                Do not create a second copy. If it is the same vacancy, give the existing
                job the new apply link — its page keeps its history and its ranking.
            </p>

            @foreach ($certain as $match)
                <div class="well well-sm tw-mb-3">
                    <h4 class="tw-mt-0">
                        <a href="{{ url('admin/jobs/' . $match['job_no'] . '/view') }}" target="_blank">
                            Job #{{ $match['job_no'] }}
                        </a>
                        <small>
                            {{ $match['status_label'] }}@if (! is_null($match['retired_days_ago']))
                                {{ $match['retired_days_ago'] }} day{{ $match['retired_days_ago'] == 1 ? '' : 's' }} ago@endif
                            &middot; matched on {{ $match['reason'] }}
                        </small>
                    </h4>

                    <table class="table table-condensed tw-mb-3">
                        <tr><th style="width:150px">Title</th><td>{{ $match['title'] }}</td></tr>
                        <tr><th>Company</th><td>{{ $match['company_name'] }}</td></tr>
                        <tr><th>Address</th><td>{{ $match['address'] }}</td></tr>
                        <tr><th>Station</th><td>{!! nl2br(e(strip_tags($match['station']))) !!}</td></tr>
                        <tr><th>Wage</th><td>{{ $match['wage'] }}</td></tr>
                        <tr><th>Review date</th><td>{{ $match['delete_at'] ?: '—' }}</td></tr>
                        <tr>
                            <th>Its apply link</th>
                            <td class="tw-break-all"><small>{{ $match['apply_link'] }}</small></td>
                        </tr>
                        <tr>
                            <th>The new link</th>
                            <td class="tw-break-all"><small>{{ $m['new_link'] }}</small></td>
                        </tr>
                    </table>

                    <form action="{{ url('admin/jobs/match/relink') }}" method="POST" class="tw-inline">
                        @csrf
                        <input type="hidden" name="job_no" value="{{ $match['job_no'] }}">
                        <input type="hidden" name="new_link" value="{{ $m['new_link'] }}">
                        <input type="hidden" name="delete_in_days" value="{{ $m['delete_in_days'] }}">
                        <input type="hidden" name="return_to" value="{{ $m['return_to'] }}">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-link"></i>
                            Update job #{{ $match['job_no'] }} with this link
                            @if (! is_null($match['retired_days_ago'])) (and republish it) @endif
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if ($review)
    <div class="alert alert-info tw-mb-4">
        <strong><i class="fa fa-search"></i> Worth a look before you create this</strong>
        <p class="tw-mb-2">
            These are not certain matches, so nothing is blocked — but check them:
            if one of them is this vacancy, update that job instead.
        </p>
        <ul class="tw-mb-0">
            @foreach ($review as $match)
                <li>
                    <a href="{{ url('admin/jobs/' . $match['job_no'] . '/view') }}" target="_blank">
                        #{{ $match['job_no'] }}
                    </a>
                    — {{ \Illuminate\Support\Str::limit($match['title'], 70) }}
                    — {{ $match['status_label'] }}@if (! is_null($match['retired_days_ago'])), retired {{ $match['retired_days_ago'] }}d ago@endif
                    — <em>{{ $match['reason'] }}</em>
                </li>
            @endforeach
        </ul>
    </div>
@endif
