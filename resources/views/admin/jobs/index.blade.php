@extends('layouts.admin')

@section('content')
    <div class="container-fluid">

        <div class="row">
            <div class="col-md-12">
                <h3 class="page-header tw-flex tw-justify-between align-items-center">
                    Jobs
                    <div>
                        <a href="{{ url('admin/jobs/unfeaturing') }}" class="btn btn-sm tw-btn-red">
                            Make Unfeature <i class="fa fa-star-o fa-fw"></i>
                        </a>
                        <a href="{{ url('admin/jobs/create-from-xml') }}" class="btn btn-sm tw-btn-indigo">
                            Create From XML <i class="fa fa-file-excel-o fa-fw"></i>
                        </a>
                        <a href="{{ url('admin/jobs/create') }}" class="btn btn-sm tw-btn-emerald">
                            Create <i class="fa fa-plus fa-fw"></i>
                        </a>
                    </div>
                </h3>
            </div>
        </div>

        <form action="{{ url('admin/jobs/' . $job_status) }}" method="POST">
            @csrf
            <div class="row tw-flex tw-justify-center">
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="lang_id">Language :</label>
                        <select name="lang_id" id="lang_id" class="form-control input-sm">
                            @foreach ($language_list as $id => $name)
                                <option value="{{ $id }}" {{ $lang_id == $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="form-group">
                        <label for="user_id">User :</label>
                        <select name="user_id" id="user_id" class="form-control input-sm">
                            @foreach ($user_list as $id => $name)
                                <option value="{{ $id }}" {{ $user_id == $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-1">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-info btn-sm btn-block">Go</button>
                    </div>
                </div>
            </div>
        </form>

        <hr>

        <div class="row tw-flex tw-justify-center">
            <div class="col-md-6">
                @php $selected = '<i class="fa fa-asterisk fa-spin tw-text-red-500"></i>' @endphp
                <nav aria-label="navigation">
                    <ul class="pager">
                        <li><a href="{{ url('admin/jobs/all') }}">{!! $active_tab == 'all' ? $selected : '' !!} All</a></li>
                        <li><a href="{{ url('admin/jobs/featured') }}">{!! $active_tab == 'featured' ? $selected : '' !!} Featured</a></li>
                        <li><a href="{{ url('admin/jobs/draft') }}">{!! $active_tab == 'draft' ? $selected : '' !!} Draft</a></li>
                        <li><a href="{{ url('admin/jobs/published') }}">{!! $active_tab == 'published' ? $selected : '' !!} Published</a></li>
                        <li><a href="{{ url('admin/jobs/expired') }}">{!! $active_tab == 'expired' ? $selected : '' !!} Expired</a></li>
                        <li><a href="{{ url('admin/jobs/trashed') }}">{!! $active_tab == 'trashed' ? $selected : '' !!} Trashed</a></li>
                    </ul>
                </nav>
            </div>

            <form action="{{ url('admin/jobs/' . $job_status . '/search') }}" method="POST" id="search-form">
                @csrf
                <div class="col-md-3 text-center">
                    <div class="form-group">
                        <label for="from">From</label>
                        <input type="text" id="from" name="from" class="form-control input-sm text-center date" value="{{ $from }}">
                    </div>
                </div>

                <div class="col-md-3 text-center">
                    <div class="form-group">
                        <label for="to">To</label>
                        <input type="text" id="to" name="to" class="form-control input-sm text-center date" value="{{ $to }}">
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="search">Search</label>
                    <div class="input-group">
                        <input type="text" id="search" name="search" class="form-control input-sm" value="{{ $search }}" placeholder="Search...">
                        <span class="input-group-btn">
                            <button class="btn btn-primary btn-sm tip" title="Click to search">
                                <i class="fa fa-search"></i>
                            </button>
                        </span>
                    </div>
                    <br>
                </div>
            </form>
        </div>

        @if ($pagination)
            <div class="text-center">{!! $pagination !!}</div>
        @endif

        <div class="row">
            <div class="col-md-12 text-center">

                @include('partials.admin.flash-message')

                <table class="table table-condensed table-hover">
                    <thead>
                        <tr>
                            <th colspan="10">
                                <div class="pull-right">
                                    Total <span class="label label-primary">{{ number_format($total_records) }}</span> records found -
                                    Showing page <span class="label label-primary">{{ $page_number }}</span>
                                </div>
                            </th>
                        </tr>
                        <tr>
                            <th class="text-center">
                                <div class="tw-border-0 tw-border-b tw-border-solid tw-border-black">Job No</div>
                                <div>Status</div>
                            </th>
                            <th class="col-md-3">Title</th>
                            <th class="col-md-2">Company Name</th>
                            <th class="col-md-1 text-center">Job Category</th>
                            <th class="col-md-1 text-center">Prefecture</th>
                            <th class="col-md-1 text-center">Station</th>
                            <th class="col-md-1 text-center">Language</th>
                            <th class="col-md-2 text-center">Created By</th>
                            <th class="col-md-2 text-center">Updated By</th>
                        </tr>
                    </thead>

                    <tbody>
                        @if ($jobs->count())
                            @foreach ($jobs as $job)
                                @php $jobStatusId = (int) $job->job_status_id @endphp
                                <tr class="text-center">
                                    <td class="v-align-middle">
                                        <div>{{ $job->job_no }}</div>
                                        <div>
                                            @if ($jobStatusId === 2)
                                                <span class="label label-default">{{ $job->job_status }}</span>
                                            @elseif ($jobStatusId === 3)
                                                <span class="label label-success">{{ $job->job_status }}</span>
                                            @elseif ($jobStatusId === 4)
                                                <span class="label label-warning">{{ $job->job_status }}</span>
                                            @elseif ($jobStatusId === 5)
                                                <span class="label label-danger">{{ $job->job_status }}</span>
                                            @elseif ($jobStatusId === 6)
                                                <span class="label tw-bg-lime-500">{{ $job->job_status }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="v-align-middle text-left">
                                        {{ $job->title }}
                                        @if ($job->featured)
                                            <i class="fa fa-star fa-fw tw-text-amber-400 tw-drop-shadow-lg"></i>
                                        @endif
                                    </td>
                                    <td class="v-align-middle text-left">{{ $job->company_name }}</td>
                                    <td class="v-align-middle">{{ $job->job_category }}</td>
                                    <td class="v-align-middle">{{ $job->prefecture }}</td>
                                    <td class="v-align-middle">{{ $job->station }}</td>
                                    <td class="v-align-middle">{{ $job->language }}</td>
                                    <td class="v-align-middle h6">
                                        <div class="tw-border-0 tw-border-b tw-border-solid tw-border-black">{{ $job->created_by_name }}</div>
                                        <div>{{ date('d M, Y', strtotime($job->date)) }}</div>
                                        <div>{{ date('h:i A', strtotime($job->date)) }}</div>
                                    </td>
                                    <td class="v-align-middle h6">
                                        @if ($job->updated_by)
                                            <div class="tw-border-0 tw-border-b tw-border-solid tw-border-black">{{ $job->updated_by_name }}</div>
                                            <div>{{ date('d M, Y', strtotime($job->updated_at)) }}</div>
                                            <div>{{ date('h:i A', strtotime($job->updated_at)) }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr class="tw-bg-slate-200">
                                    <td colspan="9" class="text-right">
                                        @if (in_array($jobStatusId, [2, 3]))
                                            @if ($job->featured)
                                                <a href="{{ url("admin/jobs/{$job->job_no}/toggle-featured?featured=0") }}" class="btn btn-xs tip" title="Remove from Home Page">
                                                    <i class="fa fa-star-half-o fa-2x tw-text-amber-400"></i>
                                                </a>
                                            @else
                                                <a href="{{ url("admin/jobs/{$job->job_no}/toggle-featured?featured=1") }}" class="btn btn-xs tip" title="Show on Home Page">
                                                    <i class="fa fa-star fa-2x tw-text-amber-400"></i>
                                                </a>
                                            @endif
                                        @endif

                                        <a href="{{ url("admin/jobs/{$job->job_no}/view") }}" class="btn btn-xs tip" title="View">
                                            <i class="fa fa-eye fa-2x"></i>
                                        </a>

                                        <a href="{{ url("admin/jobs/{$job->job_no}/clone") }}" class="btn btn-xs tip" title="Clone">
                                            <i class="fa fa-clone fa-2x"></i>
                                        </a>

                                        @if (in_array($jobStatusId, [2, 4, 5]))
                                            <a href="{{ url("admin/jobs/{$job->job_no}/edit") }}" class="btn btn-xs text-warning tip" title="Edit">
                                                <i class="fa fa-pencil fa-2x"></i>
                                            </a>
                                        @endif

                                        @if ($role_id == 1)
                                            @if ($jobStatusId === 2)
                                                <a href="{{ url("admin/jobs/{$job->job_no}/publish") }}" class="btn btn-xs text-success tip" title="Publish">
                                                    <i class="fa fa-check-square-o fa-2x"></i>
                                                </a>
                                            @elseif ($jobStatusId === 3)
                                                <a href="{{ url("admin/jobs/{$job->job_no}/edit") }}" class="btn btn-xs text-warning tip" title="Edit">
                                                    <i class="fa fa-pencil fa-2x"></i>
                                                </a>
                                                <a href="{{ url("admin/jobs/{$job->job_no}/draft") }}" class="btn btn-xs text-danger tip" title="Draft">
                                                    <i class="fa fa-ban fa-2x"></i>
                                                </a>
                                                <a href="{{ url("admin/jobs/{$job->job_no}/trash") }}" class="btn btn-xs text-danger tip" title="Trash">
                                                    <i class="fa fa-trash-o fa-2x"></i>
                                                </a>
                                            @endif
                                        @endif

                                        @if ($jobStatusId === 2)
                                            <a href="{{ url("admin/jobs/{$job->job_no}/trash") }}" class="btn btn-xs text-danger tip" title="Trash">
                                                <i class="fa fa-trash-o fa-2x"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                            @if ($pagination)
                                <tr>
                                    <td colspan="10" class="text-center">{!! $pagination !!}</td>
                                </tr>
                            @endif
                        @else
                            <tr>
                                <td colspan="10">
                                    <div class="alert alert-danger text-center">No job found</div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>

            </div>
        </div>

    </div>
@endsection

@push('page-styles')
    <link href="{{ url('plugins/datepicker/datepicker3.css') }}" rel="stylesheet">
@endpush

@push('page-scripts')
    <script src="{{ url('plugins/datepicker/bootstrap-datepicker.js') }}"></script>
    <script src="{{ mix('/js/backend-app-pages/admin/jobs/index.js') }}"></script>
@endpush
