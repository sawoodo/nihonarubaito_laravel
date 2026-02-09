@extends('layouts.frontend')

@section('content')
<div class="section wb no-top-padding">
    <div class="container">

        <div class="row">
            <div class="col-md-6 col-md-offset-3 text-center" style="border-radius: 20px; box-shadow: 0 2px 20px rgba(0,0,0,0.1);">
                <h1 style="color: #e74c3c; font-size: 2em; margin-bottom: 20px;">
                    @if ($user_lang_name == 'english')
                        Sorry, This Job Has Expired
                    @elseif ($user_lang_name == 'japanese')
                        &#30003;&#12375;&#35379;&#12372;&#12374;&#12356;&#12414;&#12379;&#12435;&#12290;&#12371;&#12398;&#27714;&#20154;&#12399;&#32066;&#20102;&#12375;&#12414;&#12375;&#12383;
                    @elseif ($user_lang_name == 'chinese')
                        &#25265;&#27465;&#65292;&#27492;&#32844;&#20301;&#24050;&#36807;&#26399;
                    @elseif ($user_lang_name == 'nepali')
                        &#2350;&#2366;&#2347; &#2327;&#2352;&#2381;&#2344;&#2369;&#2361;&#2379;&#2360;&#2381;, &#2351;&#2379; &#2325;&#2366;&#2350; &#2360;&#2350;&#2366;&#2346;&#2381;&#2340; &#2349;&#2319;&#2325;&#2379; &#2331;
                    @endif
                </h1>

                @if (!empty($job))
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: left;">
                        <strong style="color: #666;">
                            @if ($user_lang_name == 'english')
                                Expired Job:
                            @elseif ($user_lang_name == 'japanese')
                                &#32066;&#20102;&#12375;&#12383;&#27714;&#20154;&#65306;
                            @elseif ($user_lang_name == 'chinese')
                                &#24050;&#36807;&#26399;&#30340;&#32844;&#20301;&#65306;
                            @elseif ($user_lang_name == 'nepali')
                                &#2360;&#2350;&#2366;&#2346;&#2381;&#2340; &#2349;&#2319;&#2325;&#2379; &#2325;&#2366;&#2350;:
                            @endif
                        </strong>
                        <div style="color: #999; margin-top: 5px;">{{ $job->title }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <h3 class="text-center page-header">Related Jobs</h3>

                @if ($related_jobs && count($related_jobs) > 0)
                    <div class="all-jobs job-listing clearfix">
                        @foreach ($related_jobs as $relatedJob)
                            @include('jobs.partials.related-job-card', ['job' => $relatedJob])
                        @endforeach
                    </div>
                    @if (count($related_jobs) === 10)
                        @php $prefecture = strtolower($related_jobs->last()->prefecture_name) @endphp
                        <div class="text-center" style="margin-top: 20px;">
                            <a href="{{ url("part-time-jobs-in-{$prefecture}") }}" class="btn btn-primary btn-lg">
                                {{ $content->btn_load_more_label }}
                            </a>
                        </div>
                    @endif
                @else
                    <div class="alert alert-danger text-center">
                        No related jobs Found. For more jobs, please
                        <a class="btn btn-primary" href="https://nihonarubaito.com/">Click here</a>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
