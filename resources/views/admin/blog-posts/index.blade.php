@extends('layouts.admin')

@section('title', 'Blog Posts')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">
                Blog Posts

                <a href="{{ route('admin.blog-posts.create') }}" class="btn btn-success btn-sm pull-right">
                    Create New Post
                </a>
            </h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">

            @if(session('success'))
                <div class="alert alert-success text-center">
                    {{ session('success') }}
                </div>
            @endif

            <div class="table-responsive">
                <table id="blog-posts-table" class="table table-condensed table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Language</th>
                            <th>Slug</th>
                            <th>Title</th>
                            <th>Post</th>
                            <th>Created By</th>
                            <th>Updated By</th>
                            <th>Edit</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

        </div>
    </div>
</div>
@endsection

@push('page-scripts')
<script src="{{ mix('/js/backend-app-pages/admin/blog_posts/index.js') }}"></script>
@endpush
