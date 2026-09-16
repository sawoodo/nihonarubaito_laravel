@extends('layouts.admin')

@section('title', 'FB Scheduled Posts V2')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">FB Scheduled Posts V2 (Enhanced Queue)</h3>
            <p class="text-muted">Daily quota: Tokyo 8/day · Kanto 6/day · Osaka 8/day</p>
        </div>
    </div>

    @include('partials.admin.flash-message')

    {{-- Territory Tabs --}}
    <div class="row">
        <div class="col-md-12">
            <ul class="nav nav-tabs tw-mb-4">
                <li class="{{ $tab === 'tokyo' ? 'active' : '' }}">
                    <a href="{{ route('admin.fb-v2.index', ['tab' => 'tokyo']) }}">
                        Tokyo <span class="badge">{{ $counts['tokyo'] }}</span>
                    </a>
                </li>
                <li class="{{ $tab === 'kanto' ? 'active' : '' }}">
                    <a href="{{ route('admin.fb-v2.index', ['tab' => 'kanto']) }}">
                        Kanto <span class="badge">{{ $counts['kanto'] }}</span>
                    </a>
                </li>
                <li class="{{ $tab === 'osaka' ? 'active' : '' }}">
                    <a href="{{ route('admin.fb-v2.index', ['tab' => 'osaka']) }}">
                        Osaka <span class="badge">{{ $counts['osaka'] }}</span>
                    </a>
                </li>
                <li class="{{ $tab === 'other' ? 'active' : '' }}">
                    <a href="{{ route('admin.fb-v2.index', ['tab' => 'other']) }}">
                        Other <span class="badge">{{ $counts['other'] }}</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    {{-- Posts Table --}}
    <div class="row">
        <div class="col-md-12 tw-rounded-3xl tw-bg-slate-200">
            @if (count($posts) > 0)
                @php
                    $border = 'tw-border-y-0 tw-border-l-0 tw-border-r tw-border-solid tw-border-slate-200';
                @endphp

                <div class="table-responsive">
                    <table class="tw-w-full tw-border-spacing-x-0 tw-border-spacing-y-[10px] tw-border-separate">
                        <thead>
                            <tr class="tw-bg-white tw-shadow-xl">
                                <th class="tw-w-1/12 tw-rounded-l-3xl {{ $border }}"><div class="tw-px-2 tw-py-3 text-center">ID</div></th>
                                <th class="tw-w-2/12 {{ $border }}"><div class="tw-px-2 tw-py-3">Prefecture</div></th>
                                <th class="tw-w-5/12 {{ $border }}"><div class="tw-px-2 tw-py-3">Content Preview</div></th>
                                <th class="tw-w-2/12 {{ $border }} text-center"><div class="tw-px-2 tw-py-3">Created</div></th>
                                <th class="tw-w-2/12 tw-rounded-r-3xl"><div class="tw-px-2 tw-py-3 text-center">Scheduled</div></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($posts as $post)
                                <tr class="tw-bg-white tw-shadow-xl">
                                    <td class="tw-rounded-l-3xl {{ $border }}">
                                        <div class="tw-px-2 tw-py-3 text-center">{{ $post->id }}</div>
                                    </td>
                                    <td class="{{ $border }}">
                                        <div class="tw-px-2 tw-py-3">
                                            {{ $post->prefecture_name ?? '⚠️ No Prefecture' }}
                                        </div>
                                    </td>
                                    <td class="{{ $border }} text-left">
                                        <div class="tw-px-2 tw-py-3">
                                            {{ Str::limit($post->content, 100) }}
                                        </div>
                                    </td>
                                    <td class="{{ $border }} text-center">
                                        <div class="tw-px-2 tw-py-3">
                                            {{ $post->created_at ? $post->created_at->format('d-m-Y H:i') : '' }}
                                        </div>
                                    </td>
                                    <td class="tw-rounded-r-3xl text-center">
                                        <div class="tw-px-2 tw-py-3">
                                            {{ $post->scheduled_at ? $post->scheduled_at->format('d-m-Y H:i') : '' }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if ($totalPages > 1)
                    <div class="text-center tw-py-4">
                        <ul class="pagination pagination-sm">
                            @if ($currentPage > 1)
                                <li><a href="{{ route('admin.fb-v2.index', ['tab' => $tab, 'page' => 1]) }}"><span class="glyphicon glyphicon-step-backward"></span></a></li>
                            @endif

                            @php
                                $start = max(1, $currentPage - 3);
                                $end = min($totalPages, $currentPage + 3);
                            @endphp

                            @for ($i = $start; $i <= $end; $i++)
                                <li class="{{ $i === $currentPage ? 'active' : '' }}">
                                    <a href="{{ route('admin.fb-v2.index', ['tab' => $tab, 'page' => $i]) }}">{{ $i }}</a>
                                </li>
                            @endfor

                            @if ($currentPage < $totalPages)
                                <li><a href="{{ route('admin.fb-v2.index', ['tab' => $tab, 'page' => $totalPages]) }}"><span class="glyphicon glyphicon-step-forward"></span></a></li>
                            @endif
                        </ul>
                    </div>
                @endif

                <div class="text-center tw-py-2">
                    <small class="text-muted">Showing {{ count($posts) }} of {{ $totalRows }} posts</small>
                </div>
            @else
                <div class="text-center">
                    <div class="alert tw-my-5 tw-bg-red-500 tw-text-white tw-rounded-full tw-shadow-lg tw-shadow-gray-400 fade in">
                        No unposted jobs in this territory.
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
