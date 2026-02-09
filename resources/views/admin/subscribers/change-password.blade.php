@extends('layouts.admin')

@section('title', 'Change Password')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">
                Change Password <small>({{ $subscriber->email }})</small>
            </h3>
        </div>
    </div>

    @if(session('success'))
        <div class="row">
            <div class="col-md-6">
                <div class="alert alert-success fade in text-center">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">&times;</button>
                    {{ session('success') }}
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.subscribers.change-password', $subscriber->id) }}">
        @csrf

        <div class="row">
            <div class="col-md-6">
                <div class="form-group {{ $errors->has('new_password') ? 'has-error' : '' }}">
                    <label for="new_password">New Password :</label>
                    <input type="password" id="new_password" name="new_password" class="form-control input-sm" value="" tabindex="1" placeholder="Enter New Password">
                    @if($errors->has('new_password'))
                        <span class="help-block">{{ $errors->first('new_password') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group {{ $errors->has('confirm_password') ? 'has-error' : '' }}">
                    <label for="confirm_password">Confirm Password :</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control input-sm" value="" tabindex="2" placeholder="Enter Confirm Password">
                    @if($errors->has('confirm_password'))
                        <span class="help-block">{{ $errors->first('confirm_password') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <button type="submit" class="btn btn-info pull-right" tabindex="3">Change Password</button>
            </div>
        </div>
    </form>
</div>
@endsection
