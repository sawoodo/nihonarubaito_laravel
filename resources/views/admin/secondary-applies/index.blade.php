@extends('layouts.admin')

@section('title', 'Secondary Applies')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header tw-flex tw-justify-between tw-items-center">
                Secondary Applies
            </h3>
        </div>
    </div>

    @include('partials.admin.flash-message')

    <div class="row tw-flex tw-justify-center tw-mb-5">
        <div class="col-md-2 text-center">
            <div class="form-group">
                <label for="from">From</label>
                <input type="text" id="from" name="from" class="form-control input-sm text-center date" value="{{ $from }}">
            </div>
        </div>
        <div class="col-md-2 text-center">
            <div class="form-group">
                <label for="to">To</label>
                <input type="text" id="to" name="to" class="form-control input-sm text-center date" value="{{ $to }}">
            </div>
        </div>
    </div>

    <div class="row tw-mb-5">
        <div class="col-md-12">
            <table id="table" class="table table-bordered table-hover table-striped text-center">
                <thead>
                    <tr>
                        <th class="col-md-1">Job No.</th>
                        <th class="col-md-1">First Name</th>
                        <th class="col-md-1">Last Name</th>
                        <th class="col-md-1">Email</th>
                        <th class="col-md-1">Phone</th>
                        <th class="col-md-1">Apply Date</th>
                    </tr>
                    <tr>
                        <th class="col-md-1">Job No.</th>
                        <th class="col-md-1">First Name</th>
                        <th class="col-md-1">Last Name</th>
                        <th class="col-md-1">Email</th>
                        <th class="col-md-1">Phone</th>
                        <th class="col-md-1">Apply Date</th>
                    </tr>
                </thead>
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
<script src="{{ mix('/js/backend-app-pages/admin/secondary_applies/index.js') }}"></script>
@endpush
