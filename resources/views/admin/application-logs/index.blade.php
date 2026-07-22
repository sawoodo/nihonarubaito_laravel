@extends('layouts.admin')

@section('title', 'Application Logs')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Application Logs</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                <label>From</label>
                <input type="text" id="from" class="form-control input-sm datepicker" value="{{ $weekAgo }}">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>To</label>
                <input type="text" id="to" class="form-control input-sm datepicker" value="{{ $today }}">
            </div>
        </div>
    </div>

    <div class="row tw-mb-5">
        <div class="col-md-12">
            <div class="table-responsive">
                <table id="table" class="table table-bordered table-hover table-striped">
                    <thead>
                        <tr>
                            <th class="col-md-1">Job No.</th>
                            <th class="col-md-1">Merchant</th>
                            <th class="col-md-1">Click Date</th>
                            <th class="col-md-1">Order Date</th>
                            <th class="col-md-1">Title</th>
                            <th class="col-md-1">Category</th>
                            <th class="col-md-1">Apply Count</th>
                            <th class="col-md-1">Created By</th>
                            <th class="col-md-1">Updated By</th>
                            <th class="col-md-1">Link</th>
                        </tr>
                        <tr>
                            <th class="col-md-1">Job No.</th>
                            <th class="col-md-1">Merchant</th>
                            <th class="col-md-1">Click Date</th>
                            <th class="col-md-1">Order Date</th>
                            <th class="col-md-1">Title</th>
                            <th class="col-md-1">Category</th>
                            <th class="col-md-1">Apply Count</th>
                            <th class="col-md-1">Created By</th>
                            <th class="col-md-1">Updated By</th>
                            <th class="col-md-1">Link</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-styles')
    <link href="{{ url('plugins/datepicker/datepicker3.css') }}" rel="stylesheet">
@endpush

@push('page-scripts')
    <script src="{{ url('plugins/datepicker/bootstrap-datepicker.js') }}"></script>
    <script src="{{ mix('/js/backend-app-pages/admin/application_logs/index.js') }}"></script>
@endpush
