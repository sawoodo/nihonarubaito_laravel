@extends('layouts.frontend')

@section('content')
    <div class="section lb no-top-padding">
        <div class="container">
            {!! $breadcrumb !!}

            @if (isset($page_heading) && $page_heading)
                <div class="page-header text-center">
                    <div class="heading-holder">
                        <h1>{{ $page_heading }}</h1>
                        <p>{{ $intro_paragraph }}</p>
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
                                    @php $location = strtolower(str_replace(' ', '-', $area->area)) @endphp
                                    <a href="{{ url($url . $location) }}">{{ $area->area }}</a>
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

            @if (isset($blog_post) && $blog_post)
                <div class="row tw-mt-8">
                    <div class="col-md-12 tw-mt-8">
                        <div class="blog-widget tw-mt-8">
                            @if ($blog_post->title)
                                <h2 class="text-center">{{ $blog_post->title }}</h2>
                            @endif

                            <div>{!! $blog_post->post !!}</div>
                        </div>
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

@push('page-scripts')
    <script src="{{ mix('js/frontend-app-pages/home/index.js') }}"></script>
@endpush
