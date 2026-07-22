@extends('layouts.frontend')

@if (isset($noindex) && $noindex)
@section('status_headers')
    <meta name="robots" content="noindex, follow" />
@endsection
@endif

@section('content')
    <div class="section lb no-top-padding">
        <div class="container">
            {!! $breadcrumb !!}

            <div class="text-center">
                <div class="heading-holder">
                    <h1>Part Time Jobs In Japan</h1>
                </div>
            </div>

            @include('listings.partials.search-form')

            <input type="hidden" id="hid_area_id" value="{{ $area_id }}">

            @if ($user_lang_name === 'english')
                @if (isset($regions) && count($regions) > 0)
                    <div class="row">
                        <div class="col-md-6">
                            <div
                                class="tag tw-mt-8 tw-bg-gradient-to-r hover:tw-from-rose-600 hover:tw-via-rose-600 hover:tw-to-orange-500
                                tw-border tw-border-black tw-border-solid tw-rounded-3xl tw-shadow-[5px_5px_10px_#727272] tw-group"
                                role="button" data-toggle="collapse" href="#collapseHandCash" aria-expanded="false" aria-controls="collapseHandCash">
                                <h2 class="tw-flex tw-justify-between tw-items-center tw-m-5 tw-text-3xl md:tw-text-5xl group-hover:tw-text-white poppinslight">
                                    {{ $content->hand_cash_label }}
                                    <i class="fa fa-chevron-circle-down"></i>
                                </h2>
                            </div>
                            <ul class="list-group collapse tw-mb-0" id="collapseHandCash">
                                @foreach ($regions as $region => $prefectures_in_region)
                                    <li class="list-group-item">
                                        <h3>{{ $region }}</h3>
                                        <div class="tw-flex tw-flex-wrap">
                                            @foreach ($prefectures_in_region as $pref)
                                                @php $prefSlug = strtolower($pref->prefecture_slug) @endphp
                                                <a href="{{ url("hand-cash-jobs-in-{$prefSlug}") }}" class="tw-mx-5">
                                                    Hand Cash Jobs In {{ $pref->prefecture }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <div
                                class="tag tw-mt-8 tw-bg-gradient-to-r hover:tw-from-rose-600 hover:tw-via-rose-600 hover:tw-to-orange-500
                                tw-border tw-border-black tw-border-solid tw-rounded-3xl tw-shadow-[5px_5px_10px_#727272] tw-group"
                                role="button" data-toggle="collapse" href="#collapsePartTime" aria-expanded="false" aria-controls="collapsePartTime">
                                <h2 class="tw-flex tw-justify-between tw-items-center tw-m-5 tw-text-3xl md:tw-text-5xl group-hover:tw-text-white poppinslight">
                                    {{ $content->part_time_label }}
                                    <i class="fa fa-chevron-circle-down"></i>
                                </h2>
                            </div>
                            <ul class="list-group collapse tw-mb-0" id="collapsePartTime">
                                @foreach ($regions as $region => $prefectures_in_region)
                                    <li class="list-group-item">
                                        <h3>{{ $region }}</h3>
                                        <div class="tw-flex tw-flex-wrap">
                                            @foreach ($prefectures_in_region as $pref)
                                                @php $prefSlug = strtolower($pref->prefecture_slug) @endphp
                                                <a href="{{ url("part-time-jobs-in-{$prefSlug}") }}" class="tw-mx-5">
                                                    Part Time Jobs In {{ $pref->prefecture }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @if (isset($popular_areas) && count($popular_areas) > 0)
                    <div class="row tw-my-8">
                        <div class="col-md-12">

                            <div
                                class="tag tw-mb-0 tw-bg-gradient-to-r hover:tw-from-rose-600 hover:tw-via-rose-600 hover:tw-to-orange-500
                                tw-border tw-border-black tw-border-solid tw-rounded-3xl tw-shadow-[5px_5px_10px_#727272] tw-group"
                                role="button" data-toggle="collapse" href="#collapsePopularAreas" aria-expanded="false" aria-controls="collapsePopularAreas">
                                <h2 class="tw-flex tw-justify-between tw-items-center tw-m-5 tw-text-3xl md:tw-text-5xl group-hover:tw-text-white poppinslight">
                                    Popular Areas
                                    <i class="fa fa-chevron-circle-down"></i>
                                </h2>
                            </div>

                            <div class="row collapse list-group tw-mb-0" id="collapsePopularAreas">
                                @foreach ($popular_areas as $prefecture => $areas)
                                    <div class="col-md-12">
                                        <div class="list-group-item">
                                            <h4>{{ $prefecture }}</h4>
                                            <div class="row">
                                                @foreach ($areas as $area)
                                                    @php $slug = Str::slug($area->area_slug) @endphp
                                                    <div class="col-xs-4 col-md-2 tw-mb-3 md:tw-mb-4 tw-text-xl md:tw-text-2xl">
                                                        <a href="{{ url("part-time-jobs-in-{$slug}") }}">
                                                            {{ $area->area }}
                                                        </a>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                        </div>
                    </div>
                @endif

                <div class="row tw-my-8">
                    <div class="col-md-12">

                        <div
                            class="tag tw-mb-0 tw-bg-gradient-to-r hover:tw-from-rose-600 hover:tw-via-rose-600 hover:tw-to-orange-500
                            tw-border tw-border-black tw-border-solid tw-rounded-3xl tw-shadow-[5px_5px_10px_#727272] tw-group"
                            role="button" data-toggle="collapse" href="#collapsePopularStations" aria-expanded="false" aria-controls="collapsePopularStations">
                            <h2 class="tw-flex tw-justify-between tw-items-center tw-m-5 tw-text-3xl md:tw-text-5xl group-hover:tw-text-white poppinslight">
                                Popular Stations <i class="fa fa-chevron-circle-down"></i>
                            </h2>
                        </div>

                        <div class="row collapse list-group tw-mb-0" id="collapsePopularStations">
                            <div class="col-md-12">
                                <div class="list-group-item">
                                    <div class="row tw-flex tw-flex-wrap tw-justify-center">
                                        @foreach ([
                                            'namba' => 'Namba Station',
                                            'umeda' => 'Umeda Station',
                                            'tennoji' => 'Tennoji Station',
                                            'osaka' => 'Osaka Station',
                                            'shinsaibashi' => 'Shinsaibashi Station',
                                            'tokyo' => 'Tokyo Station',
                                            'shinjuku' => 'Shinjuku Station',
                                            'ikebukuro' => 'Ikebukuro Station',
                                            'shibuya' => 'Shibuya Station',
                                            'kyoto' => 'Kyoto Station',
                                            'kyoto-kawaramachi' => 'Kyoto Kawaramachi Station',
                                            'hakata' => 'Hakata Station',
                                            'tenjin' => 'Tenjin Station',
                                            'nagoya' => 'Nagoya Station',
                                            'toyohashi' => 'Toyohashi Station',
                                            'sakae' => 'Sakae Station',
                                            'utsunomiya' => 'Utsunomiya Station',
                                            'sendai' => 'Sendai Station',
                                            'hirose-dori' => 'Hirose-dori Station',
                                            'omiya' => 'Omiya Station',
                                            'kawaguchi' => 'Kawaguchi Station',
                                            'kawasaki' => 'Kawasaki Station',
                                            'yokohama' => 'Yokohama Station',
                                            'sannomiya' => 'Sannomiya Station',
                                            'himeji' => 'Himeji Station',
                                            'funabashi' => 'Funabashi Station',
                                            'matsudo' => 'Matsudo Station',
                                            'kashiwa' => 'Kashiwa Station',
                                            'maebashi' => 'Maebashi Station',
                                            'gifu' => 'Gifu Station',
                                            'hamamatsu' => 'Hamamatsu Station',
                                            'shizuoka' => 'Shizuoka Station',
                                        ] as $stationSlug => $stationName)
                                            <div class="col-xs-4 col-md-2 tw-mb-3 md:tw-mb-4 tw-text-xl md:tw-text-2xl">
                                                <a href="{{ url("part-time-jobs-at-{$stationSlug}-station") }}">
                                                    {{ $stationName }}
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            @endif

            <div class="all-jobs job-listing clearfix">

                @if (isset($jobs) && count($jobs) > 0)
                    @foreach ($jobs as $job)
                        @include('listings.partials.job-card', ['job' => $job])
                    @endforeach

                    @if (isset($pagination) && $pagination)
                        <div class="job-tab">
                            <div class="row">
                                <div class="col-md-12 text-center">{!! $pagination !!}</div>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="job-tab">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="alert alert-danger text-center">No job found</div>
                            </div>
                        </div>
                    </div>
                @endif

            </div>

        </div>
    </div>

    <input type="hidden" id="select_prefecture_first_label_text" value="{{ $content->select_prefecture_first_label ?? 'Select prefecture first' }}">
@endsection

@push('page-scripts')
    <script src="{{ mix('js/frontend-app-pages/home/index.js') }}" defer></script>
@endpush

@push('structured-data')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "Nihon Arubaito",
    "url": "https://nihonarubaito.com/",
    "logo": "https://nihonarubaito.com/frontend/images/logo.webp",
    "description": "Nihonarubaito is a multilingual job search platform helping foreigners find part-time jobs in Japan.",
    "sameAs": [
        "https://www.facebook.com/nihonarubaito"
    ],
    "contactPoint": {
        "@type": "ContactPoint",
        "email": "support@nihonarubaito.com",
        "contactType": "customer service"
    }
}
</script>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "Nihon Arubaito",
    "url": "https://nihonarubaito.com/",
    "potentialAction": {
        "@type": "SearchAction",
        "target": "https://nihonarubaito.com/jobs/search?query={search_term_string}",
        "query-input": "required name=search_term_string"
    }
}
</script>
@endpush
