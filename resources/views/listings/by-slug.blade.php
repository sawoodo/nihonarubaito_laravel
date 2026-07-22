@extends('layouts.frontend')

@include('partials.breadcrumb-schema')

@section('content')
    <div class="section lb no-top-padding">
        <div class="container">
            {!! $breadcrumb !!}

            @if (isset($page_heading) && $page_heading)
                <div class="page-header text-center">
                    <div class="heading-holder">
                        <h1>{{ $page_heading }}</h1>
                        @if (isset($blog_post) && $blog_post)
                            @php
                                preg_match('/<p[^>]*>(.*?)<\/p>/s', $blog_post->post ?? '', $__firstPara);
                            @endphp
                            @if (!empty($__firstPara[0]))
                                <div class="prefecture-intro">{!! $__firstPara[0] !!}</div>
                            @endif
                        @else
                            <p>{{ $intro_paragraph }}</p>
                        @endif
                    </div>
                </div>
            @endif

            @include('listings.partials.search-form')

            @if ($popular_areas)
                <div class="list-group tw-mt-8">
                    <div class=" list-group-item">
                        <h3 class="text-center">Popular Areas</h3>

                        <div class="row">
                            @foreach ($popular_areas as $area)
                                <div class="col-xs-4 col-md-2 tw-mb-3 md:tw-mb-4 tw-text-xl md:tw-text-2xl">
                                    @php $slug = strtolower(str_replace(' ', '-', $area->area_slug)) @endphp
                                    <a href="{{ url($url . $slug) }}">{{ $area->area }}</a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <input type="hidden" id="hid_area_id" value="{{ $area_id }}">

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

            <div class="loadmorebutton text-center clearfix">
                <a href="#" class="btn btn-primary" id="loadMore">{{ $content->btn_load_more_label }}</a>
            </div>

            {{-- Subscribe CTA for empty prefecture pages --}}
            @if (isset($jobs) && $jobs->isEmpty() && isset($prefecture_name) && $prefecture_name)
                <div style="margin: 30px 0; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; text-align: center; color: white;" class="subscribe-cta">
                    <h3 style="margin: 0 0 10px 0; color: white; font-size: 1.3em;">No part-time jobs in {{ $prefecture_name }} right now</h3>
                    <p style="margin: 0 0 15px 0; opacity: 0.9;">New jobs are added daily. Subscribe to get notified when positions open up in {{ $prefecture_name }}.</p>
                    <a href="/subscribe" style="display: inline-block; padding: 12px 30px; background: white; color: #667eea; border-radius: 25px; text-decoration: none; font-weight: bold;">Subscribe for Updates</a>
                </div>
            @endif

            @if (isset($blog_post) && $blog_post)
                <div class="row tw-mt-8">
                    <div class="col-md-12 tw-mt-8">
                        <div class="blog-widget tw-mt-8">
                            @if ($blog_post->title)
                                <h2 class="text-center">{{ $blog_post->title }}</h2>
                            @endif

                            <div>{!! preg_replace('/<p[^>]*>.*?<\/p>/s', '', $blog_post->post, 1) !!}</div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Nearby prefectures navigation --}}
            @if (isset($neighbors) && count($neighbors) > 0)
                <div class="nearby-prefectures" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h3>Explore Jobs in Nearby Prefectures</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
                        @foreach ($neighbors as $neighbor)
                            <a href="/{{ $neighbor->slug }}" style="display: inline-block; padding: 8px 16px; background: #fff; border: 1px solid #ddd; border-radius: 20px; text-decoration: none; color: #333;">
                                {{ $neighbor->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>

    <script type="application/ld+json">
    {!! $structured_data !!}
    </script>

    <input type="hidden" id="select_prefecture_first_label_text" value="{{ $content->select_prefecture_first_label ?? 'Select prefecture first' }}">
@endsection

@if (isset($faq_items) && count($faq_items) > 0)
    @push('structured-data')
        <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn($faq) => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ], $faq_items),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
        </script>
    @endpush
@endif

@push('page-scripts')
    <script src="{{ mix('js/frontend-app-pages/home/index.js') }}" defer></script>
@endpush
