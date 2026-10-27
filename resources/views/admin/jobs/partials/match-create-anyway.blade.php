{{--
  Goes INSIDE the create form. Replaces the old one-click
  "Create Anyway (Ignore Duplicate)" button: creating a second copy of a job
  we already have now costs a sentence, and that sentence is written to
  job_status_history against the new job. Before this, the Duplicates review
  page had never been used and dismissed_duplicates held 0 rows — there was no
  record anywhere of a duplicate decision being taken.
--}}
@php
    $m = session('job_matches');
    $certain = $m['certain'] ?? [];
@endphp

@if ($certain)
    <input type="hidden" name="skip_duplicate_check" value="1">
    <input type="hidden" name="matched_job_no" value="{{ $certain[0]['job_no'] }}">

    <div class="panel panel-warning tw-mb-4">
        <div class="panel-heading">
            <strong>It is a different job?</strong>
        </div>
        <div class="panel-body">
            <div class="form-group {{ $errors->has('duplicate_reason') ? 'has-error' : '' }}">
                <label for="duplicate_reason">
                    Say why, then create it — required
                </label>
                <input type="text" id="duplicate_reason" name="duplicate_reason"
                       class="form-control input-sm" maxlength="200"
                       value="{{ old('duplicate_reason') }}"
                       placeholder="e.g. the source lists two separate vacancies at this shop, different shifts">
                @error('duplicate_reason')
                    <span class="help-block">{{ $message }}</span>
                @enderror
                <small class="text-muted">
                    Saved against the new job, so the decision has a record.
                </small>
            </div>
            <button type="submit" class="btn btn-warning">
                <i class="fa fa-exclamation-triangle"></i>
                It's a different job — create anyway
            </button>
        </div>
    </div>
@endif
