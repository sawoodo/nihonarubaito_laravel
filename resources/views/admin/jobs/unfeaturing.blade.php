@extends('layouts.admin')

@section('content')
    <div class="container-fluid">

        <div class="row">
            <div class="col-md-12">
                <h3 class="page-header">Unfeaturing Jobs</h3>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12 text-center">
                <div class="well">
                    <h4>
                        All featured jobs from <strong class="tw-text-red-500">{{ date('d M, Y', strtotime('-1 day')) }}</strong> and before will be marked as unfeatured.
                    </h4>
                    <br>
                    <form action="{{ url('admin/jobs/unfeature') }}" method="POST">
                        @csrf
                        <h4>Continue?</h4>
                        <br>
                        <button type="submit" class="btn tw-btn-sky">
                            Yes, Unfeature All <i class="fa fa-check"></i>
                        </button>
                        <a href="{{ url('admin/jobs') }}" class="btn btn-default">Cancel</a>
                    </form>
                </div>
            </div>
        </div>

    </div>
@endsection
