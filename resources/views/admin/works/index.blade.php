@extends('layouts.admin')

@section('title', 'Works')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header tw-flex tw-justify-between tw-items-center">
                Works
                <a href="{{ route('admin.works.create') }}" class="btn btn-sm tw-btn-emerald">
                    Create <i class="fa fa-plus fa-fw"></i>
                </a>
            </h3>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success text-center">{{ session('success') }}</div>
    @endif

    <div class="row tw-mb-5">
        <div class="col-md-12">
            <table id="table" class="table table-bordered table-hover table-striped">
                <thead>
                    <tr>
                        <th class="col-md-12 text-center v-align-middle" colspan="5">Work</th>
                        <th class="col-md-1 text-center v-align-middle" rowspan="2">Actions</th>
                    </tr>
                    <tr>
                        <th class="col-md-1 text-center">Chinese</th>
                        <th class="col-md-1 text-center">English</th>
                        <th class="col-md-1 text-center">Japanese</th>
                        <th class="col-md-1 text-center">Korean</th>
                        <th class="col-md-1 text-center">Vietnamese</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('page-scripts')
    <script src="{{ mix('/js/backend-app-pages/admin/works/index.js') }}"></script>
@endpush
