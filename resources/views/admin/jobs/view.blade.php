@extends('layouts.admin')

@section('content')
    <div class="container-fluid">

        <div class="row">
            <div class="col-md-12">
                <h3 class="page-header tw-flex tw-justify-between align-items-center">
                    View Job #{{ $job->job_no }}
                    <a href="{{ url('admin/jobs') }}" class="btn btn-sm btn-default">
                        <i class="fa fa-arrow-left"></i> Back to Jobs
                    </a>
                </h3>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                @if ($job->images_img_id)
                    <div class="thumbnail">
                        <img class="img-responsive" src="{{ url("frontend/images/jobs/{$job->images_img_name}{$job->images_img_ext}") }}" alt="{{ $job->images_img_name }}">
                    </div>
                @elseif ($job->img_name)
                    <div class="thumbnail">
                        <img class="img-responsive" src="{{ url("frontend/images/jobs/{$job->img_name}{$job->img_ext}") }}" alt="{{ $job->img_name }}">
                    </div>
                @else
                    <div class="well text-center">
                        <h3><i class="fa fa-picture-o"></i></h3>
                        <h4>No image</h4>
                    </div>
                @endif
            </div>
        </div>

        <div class="row">
            @if ($job->img_link)
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Image Link :</label>
                        <p class="form-control-static">{{ $job->img_link }}</p>
                    </div>
                </div>
            @endif

            <div class="col-md-3">
                <div class="form-group">
                    <label>Language :</label>
                    <p class="form-control-static">{{ $job->language }}</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Title :</label>
                    <p class="form-control-static">{{ $job->title }}</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Company Name :</label>
                    <p class="form-control-static">{{ $job->company_name }}</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label>Description :</label>
                    <p class="form-control-static">{{ $job->description }}</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Job Category :</label>
                    <p class="form-control-static">{{ $job->job_category }}</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Prefecture :</label>
                    <p class="form-control-static">{{ $job->prefecture }}</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Area :</label>
                    <p class="form-control-static">{{ $job->area }}</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Station :</label>
                    <p class="form-control-static">{{ $job->station }}</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Address :</label>
                    <p class="form-control-static">{{ $job->address }}</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Japanese Level :</label>
                    <p class="form-control-static">N{{ $job->japanese_level }}</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Working Hours :</label>
                    <p class="form-control-static">{{ $job->working_hours }}</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Working Days :</label>
                    <p class="form-control-static">{{ $job->working_days }}</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Wage :</label>
                    <p class="form-control-static">{{ $job->wage }}</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Wage Type :</label>
                    <p class="form-control-static">{{ $job->wage_type }}</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>Transportation Expense :</label>
                    <p class="form-control-static">{{ $job->trans_exp }}</p>
                </div>
            </div>
            <div class="col-md-9">
                <div class="form-group">
                    <label>Requirement :</label>
                    <p class="form-control-static">{{ $job->requirement }}</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label>Apply Link :</label>
                    <p class="form-control-static">{{ $job->apply_link }}</p>
                </div>
            </div>
        </div>

    </div>
@endsection
