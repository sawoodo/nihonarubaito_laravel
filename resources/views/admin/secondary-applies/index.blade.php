@extends('layouts.admin')

@section('title', 'Secondary Applies')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Secondary Applies</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                <label>From</label>
                <input type="text" id="from" class="form-control input-sm datepicker" value="{{ $from }}">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>To</label>
                <input type="text" id="to" class="form-control input-sm datepicker" value="{{ $to }}">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="table-responsive">
                <table id="secondary-applies-table" class="table table-condensed table-bordered table-striped text-center">
                    <thead>
                        <tr>
                            <th>Job No.</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Apply Date</th>
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
<script src="{{ mix('/js/backend-app-pages/admin/secondary_applies/index.js') }}"></script>
@endpush
