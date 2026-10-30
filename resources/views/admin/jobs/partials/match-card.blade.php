{{--
  The duplicate card. Shared by the single XML import, manual create and the
  publish check on the edit page.

  Expects session('job_matches'):
    certain[]       matches that block creation or publishing
    review[]        matches shown for information only
    new_link        the apply link being imported, or the pending job's own
    delete_in_days  the review window the form asked for (not set when publishing)
    return_to       'xml' | 'manual' | 'publish'
    publish_job_no  the pending job being published ('publish' only)

  At an import no job exists yet, so the exits are relink, create anyway or
  Discard. At publish the job already exists, so Discard does not apply: the
  relink button also trashes it, and "Publish anyway" lives in
  match-publish-anyway, included after this one.

  NOTE: always leave a space before a closing Blade directive. Blade's pattern
  requires a non-word character before the "@", so a directive written straight
  after a letter (as in the word "ago" with no space) stays literal text, the
  block it should close is left open, and the view only breaks when it is
  actually rendered -- a parse error no linter on the Blade source will catch.

  A 'certain' match gets the "Update job N with this link" button. A 'review'
  match never does: offer 118120821 is stored on two unrelated employers
  because a wrong link was pasted onto one of them, so one click would relink
  the wrong job. Review says "check it" and leaves the decision to a person.
--}}
@php
    $m = session('job_matches');
    $certain = $m['certain'] ?? [];
    $review = $m['review'] ?? [];
    // Only an admin or manager may relink; JobMatchController refuses anyone
    // else, so do not show a button that will bounce them.
    $mayRelink = in_array(
        (int) (session('user')->role_id ?? 0),
        [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_MANAGER],
        true
    );
    $publishing = ($m['return_to'] ?? '') === 'publish';
    // A pending job with no usable link has nothing to give job N, and
    // relink() would refuse it: do not offer the button.
    $hasLink = \App\Services\JobMatcher::normLink($m['new_link'] ?? '') !== '';
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
                @if ($publishing)
                    Do not publish a second copy. If it is the same vacancy, give the existing
                    job this job's apply link and trash this one — its page keeps its history
                    and its ranking.
                @else
                    Do not create a second copy. If it is the same vacancy, give the existing
                    job the new apply link — its page keeps its history and its ranking.
                @endif
            </p>

            @foreach ($certain as $match)
                <div class="well well-sm tw-mb-3">
                    <h4 class="tw-mt-0">
                        <a href="{{ url('admin/jobs/' . $match['job_no'] . '/view') }}" target="_blank">
                            Job #{{ $match['job_no'] }}
                        </a>
                        <small>
                            {{ $match['status_label'] }}@if (! is_null($match['retired_days_ago']))
                                {{ $match['retired_days_ago'] }} day{{ $match['retired_days_ago'] == 1 ? '' : 's' }} ago @endif
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

                    @if ($mayRelink && $hasLink && $publishing)
                    <form action="{{ url('admin/jobs/match/relink-and-trash') }}" method="POST" class="tw-inline">
                        @csrf
                        <input type="hidden" name="job_no" value="{{ $match['job_no'] }}">
                        <input type="hidden" name="duplicate_job_no" value="{{ $m['publish_job_no'] }}">
                        <input type="hidden" name="expect_status" value="{{ $match['job_status_id'] }}">
                        <input type="hidden" name="expect_link" value="{{ $match['apply_link'] }}">
                        <input type="hidden" name="expect_duplicate_link" value="{{ $m['new_link'] }}">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-link"></i>
                            Update job #{{ $match['job_no'] }} with this link and trash this one
                            @if (! is_null($match['retired_days_ago'])) (and republish it) @endif
                        </button>
                    </form>
                    @elseif ($publishing && ! $hasLink)
                        <p class="text-muted tw-mb-0">
                            <em>This job has no apply link to give job #{{ $match['job_no'] }}. Add one, or trash this job from the list.</em>
                        </p>
                    @elseif ($mayRelink && ! $publishing)
                    <form action="{{ url('admin/jobs/match/relink') }}" method="POST" class="tw-inline">
                        @csrf
                        <input type="hidden" name="job_no" value="{{ $match['job_no'] }}">
                        <input type="hidden" name="new_link" value="{{ $m['new_link'] }}">
                        <input type="hidden" name="delete_in_days" value="{{ $m['delete_in_days'] }}">
                        <input type="hidden" name="expect_status" value="{{ $match['job_status_id'] }}">
                        <input type="hidden" name="expect_link" value="{{ $match['apply_link'] }}">
                        <input type="hidden" name="return_to" value="{{ $m['return_to'] }}">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-link"></i>
                            Update job #{{ $match['job_no'] }} with this link
                            @if (! is_null($match['retired_days_ago'])) (and republish it) @endif
                        </button>
                    </form>
                    @else
                        <p class="text-muted tw-mb-0">
                            <em>Ask an admin or manager to give job #{{ $match['job_no'] }} this link.</em>
                        </p>
                    @endif
                </div>
            @endforeach

            @unless ($publishing)
                {{-- For every role: it changes nothing, it only records the decision. --}}
                <form action="{{ url('admin/jobs/match/discard') }}" method="POST">
                    @csrf
                    <input type="hidden" name="matched_job_no" value="{{ $certain[0]['job_no'] }}">
                    <input type="hidden" name="form" value="{{ $m['return_to'] }}">
                    <input type="hidden" name="apply_link" value="{{ $m['new_link'] }}">
                    <button type="submit" class="btn btn-default">
                        <i class="fa fa-times"></i>
                        Discard — don't save this job
                    </button>
                </form>
            @endunless
        </div>
    </div>
@endif

@if ($review)
    <div class="alert alert-info tw-mb-4">
        <strong><i class="fa fa-search"></i> Worth a look before you {{ $publishing ? 'publish' : 'create' }} this</strong>
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
                    — {{ $match['status_label'] }}@if (! is_null($match['retired_days_ago'])), retired {{ $match['retired_days_ago'] }}d ago @endif
                    — <em>{{ $match['reason'] }}</em>
                </li>
            @endforeach
        </ul>
    </div>
@endif
