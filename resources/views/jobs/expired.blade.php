@extends('layouts.frontend')

@section('status_headers')
    @if (!empty($noindex))
        <meta name="robots" content="noindex, follow">
    @endif
@endsection

@section('content')
<div class="section wb no-top-padding">
    <div class="container">

        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div style="background: #fff3cd; padding: 25px 30px; border-radius: 10px; border-left: 4px solid #ffc107; margin: 30px 0;">
                    <h1 style="color: #856404; font-size: 1.4em; margin: 0 0 10px 0;">
                        @if ($user_lang_name == 'english')
                            This Job Has Expired
                        @elseif ($user_lang_name == 'japanese')
                            &#12371;&#12398;&#27714;&#20154;&#12399;&#32066;&#20102;&#12375;&#12414;&#12375;&#12383;
                        @elseif ($user_lang_name == 'chinese')
                            &#27492;&#32844;&#20301;&#24050;&#36807;&#26399;
                        @elseif ($user_lang_name == 'nepali')
                            &#2351;&#2379; &#2325;&#2366;&#2350; &#2360;&#2350;&#2366;&#2346;&#2381;&#2340; &#2349;&#2319;&#2325;&#2379; &#2331;
                        @endif
                    </h1>

                    @if (!empty($job))
                        <p style="color: #856404; margin: 0 0 10px 0; line-height: 1.6;">
                            The position <strong>"{{ $job->title }}"</strong> is no longer accepting applications.
                        </p>
                    @endif

                    @if (!empty($prefecture_slug))
                        <p style="color: #856404; margin: 0;">
                            Browse all <a href="/part-time-jobs-in-{{ $prefecture_slug }}" style="color: #856404; font-weight: bold; text-decoration: underline;">
                                part-time jobs in {{ $prefecture_name }}
                            </a>
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                @if ($related_jobs && count($related_jobs) > 0)
                    <h2 class="text-center page-header" style="font-size: 1.3em;">Similar Jobs You Might Like</h2>

                    <div class="all-jobs job-listing clearfix">
                        @foreach ($related_jobs as $relatedJob)
                            @include('jobs.partials.related-job-card', ['job' => $relatedJob])
                        @endforeach
                    </div>

                    @if (!empty($prefecture_slug))
                        <div class="text-center" style="margin-top: 20px;">
                            <a href="/part-time-jobs-in-{{ $prefecture_slug }}" class="btn btn-primary btn-lg">
                                More Jobs in {{ $prefecture_name }}
                            </a>
                        </div>
                    @endif
                @else
                    <div class="text-center" style="margin-top: 20px;">
                        <a href="/" class="btn btn-primary btn-lg">Browse All Jobs</a>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
