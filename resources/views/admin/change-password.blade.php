@extends('layouts.admin')

@section('title', 'Change Password')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Change Password</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">

            @if(session('success'))
                <div class="alert alert-success text-center">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger text-center">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.change-password') }}">
                @csrf

                <div class="form-group {{ $errors->has('currentPass') ? 'has-error' : '' }}">
                    <label for="currentPass">Current Password :</label>
                    <input type="password" id="currentPass" name="currentPass" class="form-control input-sm" value="" placeholder="Enter current password">
                    @if($errors->has('currentPass'))
                        <span class="help-block">{{ $errors->first('currentPass') }}</span>
                    @endif
                </div>

                <div class="form-group {{ $errors->has('newPass') ? 'has-error' : '' }}">
                    <label for="newPass">New Password :</label>
                    <input type="password" id="newPass" name="newPass" class="form-control input-sm" value="" placeholder="Enter new password">
                    @if($errors->has('newPass'))
                        <span class="help-block">{{ $errors->first('newPass') }}</span>
                    @endif
                </div>

                <div class="form-group {{ $errors->has('newPassAgain') ? 'has-error' : '' }}">
                    <label for="newPassAgain">New Password Again :</label>
                    <input type="password" id="newPassAgain" name="newPassAgain" class="form-control input-sm" value="" placeholder="Enter new password">
                    @if($errors->has('newPassAgain'))
                        <span class="help-block">{{ $errors->first('newPassAgain') }}</span>
                    @endif
                </div>

                <button type="submit" class="btn btn-info pull-right">Change</button>
            </form>

        </div>
    </div>

</div>
@endsection
