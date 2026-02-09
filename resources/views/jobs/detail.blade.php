@extends('layouts.frontend')

@push('page-scripts')
    <script src="{{ mix('js/frontend-app-pages/home/detail.js') }}" type="text/javascript"></script>
@endpush

@section('content')
<input type="hidden" id="btn-apply-label" value="{{ $content->btn_apply_label }}" />
<input type="hidden" id="job-no" value="{{ $job->job_no }}" />

<div class="section wb no-top-padding">
    <div class="container">
        {!! $breadcrumb !!}

        <div class="row">
            <div id="content" class="col-md-10 col-md-offset-1 col-sm-12">
                <div class="cart-body">

                    <div class="col-lg-12 col-md-12">
                        <div class="panel panel-info contact_form">
                            <div class="panel-heading text-center">
                                {{-- If job status is "published" or "quota full" --}}
                                @if ($job && in_array((int) $job->job_status_id, [3, 6]))
                                    <div class="row">

                                        <div class="col-md-4">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="img-responsive">
                                                        @php
                                                            $img = $job->images_img_id
                                                                ? "{$job->images_img_name}{$job->images_img_ext}"
                                                                : "{$job->img_name}{$job->img_ext}";
                                                        @endphp
                                                        @if ($img)
                                                            <img src="{{ url("frontend/images/jobs/{$img}") }}" loading="lazy" alt="{{ $job->title }}" width="100" height="180">
                                                        @else
                                                            <img src="{{ url("frontend/svgs/interview.svg") }}" alt="{{ $job->title }}" width="100" height="180">
                                                        @endif
                                                    </div>

                                                    @if (app()->environment('production'))
                                                        <div id="amzn-assoc-ad-a4bb43c1-c2bc-4046-bfaa-94f06edd459f"></div>
                                                        <script async src="//z-na.amazon-adsystem.com/widgets/onejs?MarketPlace=US&adInstanceId=a4bb43c1-c2bc-4046-bfaa-94f06edd459f"></script>
                                                        <small>{{ $job->img_link }}</small>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <br>

                                                <div class="form-group">

                                                    @if ((int) $job->apply_link === 123 || $job->send_email)

                                                        @include('jobs.partials.no-status-check-apply-button')

                                                    @else

                                                        @php $language = $user_lang_name @endphp

                                                        <div class="col-md-12 col-sm-12 col-xs-12 tw-mb-5 ">
                                                            @if ($language == "english")
                                                                @php $alert = 'Please apply only once on a job application.\n Please use the 11-digit telephone number.\n Please be sure to respond to emails and phone calls from the company you are applying to (not Shigoto in). Do not forget to check your spam folder. \n Please check your email after applying for the job and please go for interview . \n If you got the job, please call the other company you applied for and cancel the interview.' @endphp
                                                            @else
                                                                @php $alert = 'Chỉ ứng tuyển 1 lần cho 1 công việc.\n Hãy nhập số điện thoại có đủ 11 số.\n Khi đã ứng tuyển, nhất định hãy đi phỏng vấn . \n Khi đã được tuyển, không gọi điện đến các công ty đã ứng tuyển hẹn phỏng vấn nữa.' @endphp
                                                            @endif

                                                            @if ((int) $job->job_status_id === 6)
                                                                <div class="tw-mb-5 tw-py-3 tw-bg-amber-300 tw-text-3xl tw-text-red-600 tw-rounded-xl tw-shadow-md">
                                                                    Quota Full
                                                                </div>
                                                            @endif
                                                        </div>

                                                    @endif

                                                    <div class="fb-share-button" >
                                                    </div>
                                                </div>

                                            </div>
                                        </div>



                                        <div class="col-md-8 text-left">
                                            <h1 class="h3">{{ $job->title }}</h1>

                                            <hr>

                                            <div class="row">
                                                <div class="col-md-4 text-left">{{ $content->job_description_label }}:</div>
                                                <div class="col-md-8 text-left">
                                                    <small>{!! $job->description !!}</small>
                                                </div>
                                            </div>

                                            <div class="row tw-mt-8">
                                                <div class="col-md-4 text-left">{{ $content->wage_label }}:</div>
                                                <div class="col-md-8 text-left">
                                                    @if (!$job->wage_detail)
                                                        &yen;
                                                    @endif

                                                    <small>{{ "{$job->wage} ({$job->wage_type_name})" }}</small>

                                                    @if ($job->wage_detail)
                                                        <small>{{ $job->wage_detail }}</small>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="row tw-mt-8">
                                                <div class="col-md-4 text-left">{{ $content->prefecture_label }}:</div>
                                                <div class="col-md-8 text-left">
                                                    <small>{{ $job->area_name }}</small> <small>{{ $job->prefecture_name }}</small>
                                                </div>
                                            </div>

                                            <div class="row tw-mt-8">
                                                <div class="col-md-4 text-left">{{ $content->station_label }}:</div>
                                                <div class="col-md-8 text-left">
                                                    <small>{{ $job->station }}</small>
                                                </div>
                                            </div>

                                            <div class="row tw-mt-8">
                                                <div class="col-md-4 text-left">{{ $content->address_label }}:</div>
                                                <div class="col-md-8 text-left">
                                                    <small>{{ $job->address }}</small>
                                                </div>
                                            </div>

                                            <div class="row tw-mt-8">
                                                <div class="col-md-4 text-left">
                                                    {{ $content->japanese_level_label }}:
                                                </div>

                                                <div class="col-md-6 text-left">
                                                    @php $language = $user_lang_name @endphp

                                                    <small>
                                                        @if ($job->japanese_level == 5 && $language == "vietnamese")
                                                            Giao ti&#7871;p c&#417; b&#7843;n, c&#243; th&#7875; gi&#7899;i thi&#7879;u b&#7843;n th&#226;n (T&#432;&#417;ng &#273;&#432;&#417;ng N5)
                                                        @elseif ($job->japanese_level == 5 && $language == "english")
                                                            Basic Conversational Japanese
                                                        @elseif ($job->japanese_level == 4 && $language == "vietnamese")
                                                            Giao ti&#7871;p c&#417; b&#7843;n, c&#243; th&#7875; gi&#7899;i thi&#7879;u b&#7843;n th&#226;n (T&#432;&#417;ng &#273;&#432;&#417;ng N4)
                                                        @elseif ($job->japanese_level == 4 && $language == "english")
                                                            Basic Conversational Japanese
                                                        @elseif ($job->japanese_level == 3 && $language == "vietnamese")
                                                            H&#7897;i tho&#7841;i th&#244;ng th&#432;&#7901;ng (T&#432;&#417;ng &#273;&#432;&#417;ng N3)
                                                        @elseif ($job->japanese_level == 3 && $language == "english")
                                                            Daily Conversational Japanese
                                                        @elseif ($job->japanese_level == 2 && $language == "vietnamese")
                                                            Ti&#7871;ng Nh&#7853;t th&#432;&#417;ng m&#7841;i (t&#432;&#417;ng &#273;&#432;&#417;ng N2)
                                                        @elseif ($job->japanese_level == 2 && $language == "english")
                                                            Business Conversational Japanese
                                                        @endif
                                                    </small>
                                                </div>
                                            </div>

                                            <div class="row tw-mt-8">
                                                <div class="col-md-4 text-left">{{ $content->working_hours_label }}:</div>
                                                <div class="col-md-8 text-left">
                                                    <small>{{ $job->working_hours }}</small> <small>{{ $job->working_days }}</small>
                                                </div>
                                            </div>

                                            <div class="row tw-mt-8">
                                                <div class="col-md-4 text-left">{{ $content->trans_exp_label }}:</div>
                                                <div class="col-md-8 text-left">
                                                    <small>{{ $job->trans_exp_name }}</small>

                                                    @if ($job->transportation_detail)
                                                        <br>
                                                        <small>{{ $job->transportation_detail }}</small>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="row tw-mt-8">
                                                <div class="col-md-4 text-left">{{ $content->company_name_label }}:</div>
                                                <div class="col-md-8 text-left">
                                                    <small>{{ $job->company_name }}</small>
                                                </div>
                                            </div>

                                            <div class="row tw-mt-8">
                                                <div class="col-md-4 text-left">{{ $content->requirement_label }}:</div>
                                                <div class="col-md-8 text-left">
                                                    <small>{!! $job->requirement !!}</small>
                                                </div>
                                            </div>

                                            @if ($job->benefits)
                                                <div class="row tw-mt-8">
                                                    <div class="col-md-4 text-left">Benifits:</div>
                                                    <div class="col-md-8 text-left">
                                                        <small>{!! $job->benefits !!}</small>
                                                    </div>
                                                </div>
                                            @endif

                                            <hr>

                                            <div class="row">
                                                @if ((int) $job->apply_link === 123 || $job->send_email)

                                                    @include('jobs.partials.no-status-check-apply-button')

                                                @else

                                                    @php $language = $user_lang_name @endphp

                                                    <div class="col-md-12 col-sm-12 col-xs-12">
                                                        @if ((int) $job->job_status_id === 3)

                                                            <a
                                                                href="{{ $job->apply_link }}"
                                                                target="_blank"
                                                                class="btn btn-primary btn-lg btn-block btn-apply"
                                                                onclick="alert('{{ $alert }}')"
                                                            >
                                                                {{ $content->btn_apply_label }}
                                                            </a>

                                                            @if ($language === "english")
                                                                <ul>
                                                                    <li>Please apply only once per job.</li>
                                                                    <li>Please provide an 11-digit phone number.</li>
                                                                    <li>If you apply, you must attend the interview.</li>
                                                                    <li>If you are hired, please call the other companies you applied to and cancel the interviews.</li>
                                                                </ul>
                                                            @else
                                                                <ul>
                                                                    <li>&#12539;&#24540;&#21215;&#12399;1&#27714;&#20154;1&#24540;&#21215;&#12414;&#12391;&#12395;&#12375;&#12390;&#12367;&#12384;&#12373;&#12356;&#12290; </li>
                                                                    <li>&#12539;&#38651;&#35441;&#30058;&#21495;&#12399;11&#26689;&#12398;&#12418;&#12398;&#12434;&#20351;&#29992;&#12375;&#12390;&#12367;&#12384;&#12373;&#12356;&#12290;</li>
                                                                    <li>&#12539;&#24540;&#21215;&#12434;&#12375;&#12383;&#12425;&#24517;&#12378;&#38754;&#25509;&#12395;&#34892;&#12387;&#12390;&#12367;&#12384;&#12373;&#12356;&#12290;</li>
                                                                    <li>&#12539;&#25505;&#29992;&#12364;&#27770;&#12414;&#12387;&#12383;&#22580;&#21512;&#12399;&#12289;&#20182;&#12395;&#24540;&#21215;&#12375;&#12383;&#20225;&#26989;&#12395;&#38651;&#35441;&#12434;&#12363;&#12369;&#12390;&#38754;&#25509;&#12398;&#21462;&#12426;&#12420;&#12417;&#12434;&#12375;&#12390;&#12367;&#12384;&#12373;&#12356;&#12290;</li>
                                                                </ul>
                                                            @endif

                                                        @elseif ((int) $job->job_status_id === 6)
                                                            <div class="tw-mb-5 tw-py-3 text-center tw-text-3xl tw-text-red-600 tw-bg-amber-300 tw-rounded-xl tw-shadow-md">
                                                                Quota Full
                                                            </div>
                                                        @endif
                                                    </div>

                                                @endif
                                            </div>


                                        </div>

                                    </div>
                                    @if ((int) $job->job_status_id === 3)
                                        @php $language = $user_lang_name @endphp
                                        <div class="row">
                                            <div class="col-md-10 col-md-offset-1 col-xs-12 text-uppercase tw-text-red-500">

                                                @if (stripos($job->apply_link, "shigotoin") > 0)
                                                    @if ($language === 'japanese')
                                                        &#12300;&#27880;&#24847;&#25991;&#35328;&#65306;&#24540;&#21215;&#20808;&#20225;&#26989;&#65288;&#12471;&#12468;&#12488;inではない&#65289;&#12363;&#12425;&#12398;&#12513;&#12540;&#12523;&#12539;&#38651;&#35441;&#36899;&#32097;&#12395;&#24517;&#12378;&#23550;&#24540;&#12375;&#12390;&#12367;&#12384;&#12373;&#12356;&#12290;&#36855;&#24785;&#12513;&#12540;&#12523;&#12501;&#12457;&#12523;&#12480;&#12398;&#12481;&#12455;&#12483;&#12463;&#12418;&#12362;&#24536;&#12428;&#12394;&#12367;&#65281;&#12301;
                                                    @elseif ($language === 'english')
                                                        Please be sure to respond to emails and phone calls from the company you are applying to (not Shigoto in). Don't forget to check your spam folder!
                                                    @endif
                                                @elseif (stripos($job->apply_link, "arubaito-ex") > 0)
                                                    @if ($language === 'japanese')
                                                        &#12300;&#27880;&#24847;&#25991;&#35328;&#65306;&#24540;&#21215;&#20808;&#20225;&#26989;&#65288;&#12450;&#12523;&#12496;&#12452;&#12488;EX&#12391;&#12399;&#12394;&#12356;&#65289;&#12363;&#12425;&#12398;&#12513;&#12540;&#12523;&#12539;&#38651;&#35441;&#36899;&#32097;&#12395;&#24517;&#12378;&#23550;&#24540;&#12375;&#12390;&#12367;&#12384;&#12373;&#12356;&#12290;&#36855;&#24785;&#12513;&#12540;&#12523;&#12501;&#12457;&#12523;&#12480;&#12398;&#12481;&#12455;&#12483;&#12463;&#12418;&#12362;&#24536;&#12428;&#12394;&#12367;&#65281;&#12301;
                                                    @elseif ($language === 'english')
                                                        Please be sure to respond to emails and phone calls from the company you are applying to (not Arubaito EX). Don't forget to check your spam folder!
                                                    @endif
                                                @elseif (stripos($job->apply_link, "baitoru") > 0)
                                                    @if ($language === 'japanese')
                                                        &#12300;&#27880;&#24847;&#25991;&#35328;&#65306;&#24540;&#21215;&#20808;&#20225;&#26989;&#65288;&#12496;&#12452;&#12488;&#12523;&#12391;&#12399;&#12394;&#12356;&#65289;&#12363;&#12425;&#12398;&#12513;&#12540;&#12523;&#12539;&#38651;&#35441;&#36899;&#32097;&#12395;&#24517;&#12378;&#23550;&#24540;&#12375;&#12390;&#12367;&#12384;&#12373;&#12356;&#12290;&#36855;&#24785;&#12513;&#12540;&#12523;&#12501;&#12457;&#12523;&#12480;&#12398;&#12481;&#12455;&#12483;&#12463;&#12418;&#12362;&#24536;&#12428;&#12394;&#12367;&#65281;&#12301;
                                                    @elseif ($language === 'english')
                                                        Please be sure to respond to emails and phone calls from the company you are applying to (not Baitoru). Don't forget to check your spam folder!
                                                    @endif
                                                @elseif (stripos($job->apply_link, "townwork") > 0)
                                                    @if ($language === 'japanese')
                                                        &#12300;&#27880;&#24847;&#25991;&#35328;&#65306;&#24540;&#21215;&#20808;&#20225;&#26989;&#65288;&#12479;&#12454;&#12531;&#12527;&#12540;&#12463;&#12391;&#12399;&#12394;&#12356;&#65289;&#12363;&#12425;&#12398;&#12513;&#12540;&#12523;&#12539;&#38651;&#35441;&#36899;&#32097;&#12395;&#24517;&#12378;&#23550;&#24540;&#12375;&#12390;&#12367;&#12384;&#12373;&#12356;&#12290;&#36855;&#24785;&#12513;&#12540;&#12523;&#12501;&#12457;&#12523;&#12480;&#12398;&#12481;&#12455;&#12483;&#12463;&#12418;&#12362;&#24536;&#12428;&#12394;&#12367;&#65281;&#12301;
                                                    @elseif ($language === 'english')
                                                        Please be sure to respond to emails and phone calls from the company you are applying to (not Townwork). Don't forget to check your spam folder!
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    @if (app()->environment('production'))
                                    <ins class="adsbygoogle"
                                        style="display:inline-block;width:360px;height:800px"
                                        data-ad-client="ca-pub-5261166510941827"
                                        data-ad-slot="9902449989">
                                    </ins>
                                    <script>(adsbygoogle = window.adsbygoogle || []).push({})</script>
                                    @endif

                                @else

                                    @php $language = $user_lang_name @endphp

                                    @if ($language == "vietnamese")

                                            Xin l&#7895;i, c&#244;ng vi&#7879;c n&#224;y &#273;&#227; k&#7871;t th&#250;c tuy&#7875;n d&#7909;ng. Xin vui l&#242;ng "Click v&#224;o &#273;&#226;y" &#273;&#7875; t&#236;m ki&#7871;m th&#234;m nhi&#7873;u vi&#7879;c kh&#225;c

                                        </div>
                                    @elseif ($language == 'japanese')

                                            &#30003;&#12375;&#35379;&#12372;&#12374;&#12356;&#12414;&#12379;&#12435;&#12364;&#12289;&#12371;&#12398;&#12472;&#12519;&#12502;&#12399;&#26399;&#38480;&#20999;&#12428;&#12391;&#12377;&#12290;&#12381;&#12398;&#20182;&#12398;&#20181;&#20107;&#12395;&#12388;&#12356;&#12390;&#12399;&#12289;

                                        </div>
                                    @else

                                            Sorry this job has been expired. For more jobs please

                                        </div>
                                    @endif

                                @endif

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <h3 class="text-center page-header m120">Related Jobs</h3>
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


<div class="modal fade" id="japLevelModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog ">
        <div class="modal-content">
            <button type="button" class="close closebtn" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>

            <div class="modal-header">
                <h4 class="text-center">Japanese Skill (N1 - N5) Explanation</h4>
            </div>

            <div class="modal-body">

                <div class="widget clearfix">

                    <div class="post-padding item-price">

                        <h5>N5 means: </h5>
                        <p>
                            Only basic Japanese. It requires understanding of some everyday basic conversation as well as understanding some basic everyday topics written in Hiragana and Katakana and a few basic kanji.
                        </p>

                        <h5>N4 means: </h5>
                        <p>
                            This level requires understanding of basic everyday topics written in kanji and being
                            able to listen and make everyday conversation in Japanese as well.
                        </p>

                        <h5>N3 means: </h5>
                        <p>
                            This level requires a good understanding of more formal and informal writing. It also
                            requires individuals to be able have conversation in a more natural way.
                        </p>

                        <h5>N2 means: </h5>
                        <p>
                            This level requires a clear understanding of Japanese conversation on a wider range of
                            topics and issues. One must also be able to read and understand a wider range of kanji.
                        </p>

                        <h5>N1 means: </h5>
                        <p>
                            This is the highest skill level. One must be able read and understand a variety of writings related to different circumstances. The N1 level requires the ability to comprehend all orally presented materials in a coherent and logical way.
                        </p>

                    </div>

                </div>

            </div>
        </div>
    </div>
</div>

{!! $schema_script ?? '' !!}
@endsection
