@extends('layouts.admin')

@section('title', 'Edit FB Scheduled Post')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Edit FB Scheduled Post</h3>
        </div>
    </div>

    @if ($fb_post)
        <form action="{{ url("admin/fb-scheduled-posts/{$fb_post->id}/edit") }}" method="POST">
            @csrf

            <div class="row text-center">
                <div class="col-md-1">
                    <label>ID</label>
                    <div class="form-control input-sm">{{ $fb_post->id }}</div>
                </div>

                <div class="col-md-1">
                    <label>Published</label>
                    <div class="form-control input-sm">{{ $fb_post->published ? 'YES' : 'NO' }}</div>
                </div>

                <div class="col-md-2">
                    <label>Created At</label>
                    <div class="form-control input-sm">{{ $fb_post->created_at ? $fb_post->created_at->format('d-m-Y H:i:s') : '' }}</div>
                </div>

                <div class="col-md-2">
                    <div class="form-group {{ $errors->has('scheduled_at') ? 'has-error' : '' }}">
                        <label for="scheduled_at">Scheduled At:</label>
                        <input type="text" id="scheduled_at" name="scheduled_at" class="form-control input-sm text-center" value="{{ old('scheduled_at', $fb_post->scheduled_at) }}">
                        @error('scheduled_at') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <label>Post Content</label>
                    <div class="tw-flex tw-items-center tw-h-64 form-control input-sm">
                        {!! nl2br(e($fb_post->content)) !!}
                    </div>
                </div>
            </div>

            <div class="row tw-mt-8">
                <div class="col-md-5">
                    <button type="submit" class="btn btn-info">Update</button>
                </div>
            </div>
        </form>
    @else
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-danger text-center">No scheduled post found.</div>
            </div>
        </div>
    @endif

</div>
@endsection

@push('page-styles')
<link href="{{ url('plugins/datepicker/datepicker3.css') }}" rel="stylesheet">
@endpush

@push('page-scripts')
<script src="{{ url('plugins/datepicker/bootstrap-datepicker.js') }}"></script>
<script src="{{ mix('/js/backend-app-pages/admin/fb_scheduled_posts/edit.js') }}"></script>
@endpush
