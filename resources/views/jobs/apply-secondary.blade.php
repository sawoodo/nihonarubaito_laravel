@extends('layouts.frontend')

@section('content')
    <div class="section wb no-top-padding">
        <div class="container">
            <div class="row">

                @if (session('success'))
                    <div class="col-md-12">
                        <div class="alert alert-success text-center">
                            {{ session('success') }}
                        </div>
                    </div>
                @endif

               <form action="{{ url("jobs/{$job_no}/apply-2nd-option") }}" class="submit-form customform loginform" method="post">
                    @csrf

                    <h4>{{ $content->form_heading }}</h4>

                    <div class="row">

                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="input-group" @if ($errors->has('first_name')) style="margin-bottom: 0;" @endif>
                                <span class="input-group-addon"><i class="fa fa-user-circle"></i></span>
                                <input type="text" name="first_name" class="form-control" value="{{ old('first_name', '') }}" placeholder="{{ $content->first_name_label }}" tabindex="1" />
                            </div>
                            @if ($errors->has('first_name'))
                                <span class="error">{{ $errors->first('first_name') }}</span>
                            @endif
                        </div>

                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="input-group" @if ($errors->has('last_name')) style="margin-bottom: 0;" @endif>
                                <span class="input-group-addon"><i class="fa fa-user-o"></i></span>
                                <input type="text" name="last_name" class="form-control" value="{{ old('last_name', '') }}" placeholder="{{ $content->last_name_label }}" tabindex="2" />
                            </div>
                            @if ($errors->has('last_name'))
                                <span class="error">{{ $errors->first('last_name') }}</span>
                            @endif
                        </div>

                    </div>

                    <div class="row">

                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="input-group" @if ($errors->has('email')) style="margin-bottom: 0;" @endif>
                                <span class="input-group-addon"><i class="fa fa-envelope-o"></i></span>
                                <input type="text" name="email" class="form-control" value="{{ old('email', '') }}" placeholder="{{ $content->email_label }}" tabindex="6" />
                            </div>
                            @if ($errors->has('email'))
                                <span class="error">{{ $errors->first('email') }}</span>
                            @endif
                        </div>

                        <div class="col-md-6 col-sm-6 col-xs-12">
                            <div class="input-group" @if ($errors->has('phone')) style="margin-bottom: 0;" @endif>
                                <span class="input-group-addon"><i class="fa fa-phone"></i></span>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone', '') }}" placeholder="{{ $content->phone_label }}" tabindex="7" />
                            </div>
                            @if ($errors->has('phone'))
                                <span class="error">{{ $errors->first('phone') }}</span>
                            @endif
                        </div>

                    </div>

                    <br />

                    <button class="btn btn-custom btn-block">{{ $content->btn_apply_label }}</button>

                </form>

            </div>
        </div>
    </div>
@endsection
