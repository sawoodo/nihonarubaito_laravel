@extends('layouts.admin')

@section('title', 'Areas')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header tw-flex tw-justify-between tw-items-center">
                Areas
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
                        <th class="col-md-1 text-center">ID</th>
                        <th class="col-md-1 text-center">Prefecture</th>
                        <th class="col-md-1 text-center">Chinese</th>
                        <th class="col-md-1 text-center">English</th>
                        <th class="col-md-1 text-center">Japanese</th>
                        <th class="col-md-1 text-center">Korean</th>
                        <th class="col-md-1 text-center">Vietnamese</th>
                        <th class="col-md-1 text-center">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('page-scripts')
    <script src="{{ mix('/js/backend-app-pages/admin/areas/index.js') }}"></script>
@endpush
