{{--
  "Publish anyway", for the publish check on the edit page. A form of its own,
  so it goes OUTSIDE the edit form. The reason is written to job_status_history
  against the job. Admins only, because JobController::publish() is.
--}}
@php
    $m = session('job_matches');
    $mayPublish = (int) (session('user')->role_id ?? 0) === \App\Models\User::ROLE_ADMIN;
@endphp

@if ($mayPublish && ! empty($m['certain']) && ($m['return_to'] ?? '') === 'publish')
    <form action="{{ url('admin/jobs/' . $m['publish_job_no'] . '/publish-anyway') }}" method="POST">
        @csrf
        <div class="panel panel-warning tw-mb-4">
            <div class="panel-heading">
                <strong>It is a different job?</strong>
            </div>
            <div class="panel-body">
                <div class="form-group {{ $errors->has('publish_reason') ? 'has-error' : '' }}">
                    <label for="publish_reason">
                        Say why, then publish it — required
                    </label>
                    <input type="text" id="publish_reason" name="publish_reason"
                           class="form-control input-sm" maxlength="200"
                           value="{{ old('publish_reason') }}"
                           placeholder="e.g. the source lists two separate vacancies at this shop, different shifts">
                    @error('publish_reason')
                        <span class="help-block">{{ $message }}</span>
                    @enderror
                    <small class="text-muted">
                        Saved against this job, so the decision has a record.
                    </small>
                </div>
                <button type="submit" class="btn btn-warning">
                    <i class="fa fa-exclamation-triangle"></i>
                    Publish anyway
                </button>
            </div>
        </div>
    </form>
@endif
