@extends('layouts.admin')

@section('title', 'FB Scheduled Posts')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">FB Scheduled Posts</h3>
        </div>
    </div>

    @include('partials.admin.flash-message')

    <div class="row">
        <div class="col-md-12 tw-rounded-3xl tw-bg-slate-200">
            @if (count($fb_posts) > 0)
                @if ($pagination)
                    <div class="text-center">{!! $pagination !!}</div>
                @endif

                <form action="{{ url('admin/fb-scheduled-posts') }}" method="GET" class="tw-py-4{{ $pagination ? '' : ' tw-mt-8' }}">
                    <div class="row">
                        <div class="col-md-5">
                            <label class="control-label">Content</label>
                            <input type="text" name="q" value="{{ e($q) }}" class="form-control" placeholder="Search content...">
                        </div>

                        <div class="col-md-2">
                            <label>Created Date</label>
                            <input type="text" id="created_at" name="created_at" class="form-control text-center date" value="{{ $created_at }}">
                        </div>

                        <div class="col-md-2">
                            <label>Scheduled Date</label>
                            <input type="text" id="scheduled_at" name="scheduled_at" class="form-control text-center date" value="{{ $scheduled_at }}">
                        </div>

                        <div class="col-md-3">
                            <label class="control-label tw-invisible">&nbsp;</label>
                            <div class="btn-group tw-w-full">
                                <button class="btn btn-primary tw-w-1/2">
                                    <i class="fa fa-search"></i> Search
                                </button>
                                <a href="{{ url('admin/fb-scheduled-posts') }}" class="btn btn-default tw-w-1/2">Reset</a>
                            </div>
                        </div>
                    </div>
                </form>

                @php
                    $border = 'tw-border-y-0 tw-border-l-0 tw-border-r tw-border-solid tw-border-slate-200';
                    $badgeBase = 'tw-px-5 tw-py-px tw-inline-block tw-shadow-lg tw-text-white tw-rounded-full';
                @endphp

                <div class="table-responsive">
                    <table class="tw-w-full tw-border-spacing-x-0 tw-border-spacing-y-[10px] tw-border-separate">
                        <thead>
                            <tr class="tw-bg-white tw-shadow-xl">
                                <th class="tw-w-1/12 tw-rounded-l-3xl {{ $border }}"><div class="tw-px-2 tw-py-3 text-center">ID</div></th>
                                <th class="tw-w-5/12 {{ $border }}"><div class="tw-px-2 tw-py-3">Content</div></th>
                                <th class="tw-w-1/12 {{ $border }} text-center"><div class="tw-px-2 tw-py-3">Language</div></th>
                                <th class="tw-w-1/12 {{ $border }} text-center"><div class="tw-px-2 tw-py-3">Published</div></th>
                                <th class="tw-w-1/12 {{ $border }} text-center"><div class="tw-px-2 tw-py-3">Created At</div></th>
                                <th class="tw-w-1/12 {{ $border }} text-center"><div class="tw-px-2 tw-py-3">Scheduled At</div></th>
                                <th class="tw-w-1/12 {{ $border }} text-center"><div class="tw-px-2 tw-py-3">Run At</div></th>
                                <th class="tw-w-1/12 tw-rounded-r-3xl"><div class="tw-px-2 tw-py-3 text-center">Actions</div></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($fb_posts as $post)
                                <tr class="tw-bg-white tw-shadow-xl text-center">
                                    <td class="tw-rounded-l-3xl {{ $border }}">
                                        <div class="tw-px-2 tw-py-3 text-center">{{ $post->id }}</div>
                                    </td>
                                    <td class="{{ $border }} text-left">
                                        <div class="tw-px-2 tw-py-3">{!! nl2br(e($post->content)) !!}</div>
                                    </td>
                                    <td class="{{ $border }}">
                                        <div class="tw-px-2 tw-py-3">{{ $post->language }}</div>
                                    </td>
                                    <td class="{{ $border }}">
                                        <div class="tw-px-2 tw-py-3">
                                            @if ($post->published)
                                                <div class="{{ $badgeBase }} tw-bg-lime-500 tw-shadow-lime-400 tw-w-24">YES</div>
                                            @else
                                                <div class="{{ $badgeBase }} tw-bg-red-500 tw-shadow-red-300 tw-w-24">NO</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="{{ $border }}">
                                        <div class="tw-px-2 tw-py-3">
                                            {{ $post->created_at ? $post->created_at->format('d-m-Y') : '' }}<br>
                                            {{ $post->created_at ? $post->created_at->format('H:i:s') : '' }}
                                        </div>
                                    </td>
                                    <td class="{{ $border }}">
                                        <div class="tw-px-2 tw-py-3">
                                            {{ $post->scheduled_at ? $post->scheduled_at->format('d-m-Y') : '' }}<br>
                                            {{ $post->scheduled_at ? $post->scheduled_at->format('H:i:s') : '' }}
                                        </div>
                                    </td>
                                    <td class="{{ $border }}">
                                        <div class="tw-px-2 tw-py-3">
                                            @if ($post->published)
                                                @if ($post->run_at)
                                                    {{ $post->run_at->format('d-m-Y') }}<br>
                                                    {{ $post->run_at->format('H:i:s') }}
                                                    <div class="{{ $badgeBase }} {{ $post->run_type === 'Manually' ? 'tw-bg-orange-500 tw-shadow-orange-300' : 'tw-bg-emerald-500 tw-shadow-emerald-300' }}">
                                                        {{ $post->run_type }}
                                                    </div>
                                                @endif
                                            @else
                                                <div class="{{ $badgeBase }} tw-bg-red-500 tw-shadow-red-300 tw-text-lg">Not Run Yet</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="tw-rounded-r-3xl">
                                        @if (!$post->published)
                                            <div class="tw-px-2 tw-py-3 text-center">
                                                <a href="{{ url("admin/fb-scheduled-posts/{$post->id}/post") }}" class="btn btn-xs tw-btn-emerald tip" title="Post on FB">
                                                    <i class="fa fa-send fa-2x"></i>
                                                </a>
                                                <a href="{{ url("admin/fb-scheduled-posts/{$post->id}/edit") }}" class="btn btn-xs tw-btn-purple tip" title="Edit">
                                                    <i class="fa fa-pencil-square-o fa-2x"></i>
                                                </a>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($pagination)
                    <div class="text-center">{!! $pagination !!}</div>
                @endif
            @else
                <div class="text-center">
                    <div class="alert tw-my-5 tw-bg-red-500 tw-text-white tw-rounded-full tw-shadow-lg tw-shadow-gray-400 fade in">
                        No scheduled post found.
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('page-styles')
<link href="{{ url('plugins/datepicker/datepicker3.css') }}" rel="stylesheet">
@endpush

@push('page-scripts')
<script src="{{ url('plugins/datepicker/bootstrap-datepicker.js') }}"></script>
<script src="{{ url('js/backend-app-pages/admin/fb_scheduled_posts/index.js') }}"></script>
@endpush
