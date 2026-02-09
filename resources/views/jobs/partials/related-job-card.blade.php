@php $slug = \Illuminate\Support\Str::slug(strtolower($user_lang_name . '-' . $job->title)) @endphp
<div class="job-tab">
    <div class="row">

        <div class="col-md-2 col-sm-2 col-xs-6">

            <div class="badge freelancer-badge">{{ $job->category_name }}</div>

            <div class="post-media">
                <a href="{{ url("jobs/{$job->job_no}/detail/{$slug}") }}">
                    @php $img = ($job->images_img_id > 0) ? "{$job->images_img_name}{$job->images_img_ext}" : "{$job->img_name}{$job->img_ext}" @endphp

                    @if ($img)
                        <img src="{{ url("frontend/images/jobs/{$img}") }}" alt='job image' class="img-responsive img-thumbnail" />
                    @endif
                </a>
            </div>
        </div>

        <div class="col-md-6 col-sm-6 col-xs-6">
            <h4>
                <a href="{{ url("jobs/{$job->job_no}/detail/{$slug}") }}" >
                    {{ $job->title }}
                </a>
            </h4>
        </div>


        <div class="col-md-2 col-sm-2 col-xs-12">
            <div class="job-meta">
                <p><i class="fa fa-map-marker icon" aria-hidden="true"></i>{{ $job->area_name }}</p>
                <small>{{ $job->prefecture_name }}</small>
            </div>
        </div>

        <div class="col-md-2 col-sm-2 col-xs-12">
            <div class="jtext-center">
                <a href="{{ url("jobs/{$job->job_no}/detail/{$slug}") }}" class="btn btn-primary btn-sm btn-block">
                    {{ $content->btn_view_details_label }}
                </a>
            </div>
        </div>

    </div>
</div>
