@extends('layouts.frontend')

@section('content')
    <div class="section lb">
        <div class="container">
            <div class="row">
                <div class="col-md-6 col-sm-6 col-md-offset-3 col-sm-offset-3 col-xs-12">

                    <form action="{{ url('account/login') }}" method="POST" id="loginForm" class="submit-form customform loginform">
                        @csrf
                        <h4>Login</h4>

                        @if ($authentication_failed)
                            <div class="alert alert-danger text-center">
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">&times;</button>
                                Invalid email or password
                            </div>
                        @endif

                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-user"></i></span>
                            <input type="text" id="txtEmail" name="email" class="form-control" value="{{ old('email', '') }}" placeholder="Email">
                        </div>
                        @error('email')
                            <span class="error">{{ $message }}</span>
                        @enderror

                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-lock"></i></span>
                            <input type="password" id="txtPass" name="password" class="form-control" value="" placeholder="Please enter password">
                        </div>
                        @error('password')
                            <span class="error">{{ $message }}</span>
                        @enderror

                        <div>
                            <input type="submit" id="btnLogin" name="btnLogin" class="btn btn-custom" value="Login">
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
@endsection
