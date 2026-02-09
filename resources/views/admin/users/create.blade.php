@extends('layouts.admin')

@section('title', 'Create New User')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Create New User</h3>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.users.create') }}">
        @csrf

        <div class="row">
            <div class="col-md-6">
                <div class="form-group {{ $errors->has('first_name') ? 'has-error' : '' }}">
                    <label for="first_name">First Name :</label>
                    <input type="text" id="first_name" name="first_name" class="form-control input-sm" value="{{ old('first_name') }}" placeholder="Enter First Name">
                    @if($errors->has('first_name'))
                        <span class="help-block">{{ $errors->first('first_name') }}</span>
                    @endif
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group {{ $errors->has('last_name') ? 'has-error' : '' }}">
                    <label for="last_name">Last Name :</label>
                    <input type="text" id="last_name" name="last_name" class="form-control input-sm" value="{{ old('last_name') }}" placeholder="Enter Last Name">
                    @if($errors->has('last_name'))
                        <span class="help-block">{{ $errors->first('last_name') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group {{ $errors->has('email') ? 'has-error' : '' }}">
                    <label for="email">Email :</label>
                    <input type="text" id="email" name="email" class="form-control input-sm" value="{{ old('email') }}" placeholder="Enter Email">
                    @if($errors->has('email'))
                        <span class="help-block">{{ $errors->first('email') }}</span>
                    @endif
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group {{ $errors->has('password') ? 'has-error' : '' }}">
                    <label for="password">Password :</label>
                    <input type="text" id="password" name="password" class="form-control input-sm" value="{{ old('password') }}" placeholder="Enter Password">
                    @if($errors->has('password'))
                        <span class="help-block">{{ $errors->first('password') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group {{ $errors->has('role_id') ? 'has-error' : '' }}">
                    <label for="role_id">Role :</label>
                    <select id="role_id" name="role_id" class="form-control input-sm">
                        @foreach($roles as $val => $label)
                            <option value="{{ $val }}" {{ old('role_id') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($errors->has('role_id'))
                        <span class="help-block">{{ $errors->first('role_id') }}</span>
                    @endif
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group {{ $errors->has('lang_id') ? 'has-error' : '' }}">
                    <label for="lang_id">Language :</label>
                    <select id="lang_id" name="lang_id" class="form-control input-sm">
                        @foreach($langList as $val => $label)
                            <option value="{{ $val }}" {{ old('lang_id') == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($errors->has('lang_id'))
                        <span class="help-block">{{ $errors->first('lang_id') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-info">Create</button>
    </form>

</div>
@endsection
