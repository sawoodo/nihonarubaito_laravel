@extends('layouts.admin')

@section('title', 'Edit Blog Post')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Edit Blog Post</h3>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.blog-posts.edit', $post->id) }}">
        @csrf

        <div class="row">
            <div class="col-md-6">
                <div class="form-group {{ $errors->has('lang_id') ? 'has-error' : '' }}">
                    <label for="lang_id">Language :</label>
                    <select id="lang_id" name="lang_id" class="form-control input-sm">
                        @foreach($langList as $val => $label)
                            <option value="{{ $val }}" {{ old('lang_id', $post->lang_id) == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($errors->has('lang_id'))
                        <span class="help-block">{{ $errors->first('lang_id') }}</span>
                    @endif
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group">
                    <label for="slug">Slug :</label>
                    <input type="text" id="slug" name="slug" class="form-control input-sm" value="{{ $post->slug }}" readonly>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="form-group {{ $errors->has('title') ? 'has-error' : '' }}">
                    <label for="title">Title :</label>
                    <input type="text" id="title" name="title" class="form-control input-sm" value="{{ old('title', $post->title) }}" placeholder="Enter title">
                    @if($errors->has('title'))
                        <span class="help-block">{{ $errors->first('title') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="form-group {{ $errors->has('post') ? 'has-error' : '' }}">
                    <label for="post">Post :</label>
                    <textarea id="post" name="post" class="form-control tinymce" rows="15">{{ old('post', $post->post) }}</textarea>
                    @if($errors->has('post'))
                        <span class="help-block">{{ $errors->first('post') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-info">Update</button>
    </form>

</div>
@endsection

@push('page-scripts')
<script src="{{ mix('/js/backend-app-pages/admin/blog_posts/create_edit.js') }}"></script>
@endpush
