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
                <input type="text" id="from" class="form-control input-sm datepicker" value="{{ $today }}">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>To</label>
                <input type="text" id="to" class="form-control input-sm datepicker" value="{{ $today }}">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="table-responsive">
                <table id="application-logs-table" class="table table-condensed table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Job No.</th>
                            <th>Merchant</th>
                            <th>Click Date</th>
                            <th>Order Date</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Apply Count</th>
                            <th>Created By</th>
                            <th>Updated By</th>
                            <th>Link</th>
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
<script src="{{ mix('/js/backend-app-pages/admin/application_logs/index.js') }}"></script>
@endpush
