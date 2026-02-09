<div class="col-md-12 col-sm-12 col-xs-12">

    @php $apply = $job->send_email ? 'apply-2nd-option' : 'apply' @endphp

    <a href="{{ url("jobs/{$job->job_no}/{$apply}") }}" class="btn btn-primary btn-lg btn-block" target="_blank">
        {{ $content->btn_apply_label }}
    </a>
</div>
