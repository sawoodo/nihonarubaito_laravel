<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Admin Login - {{ config('app.name', 'Nihon Arubaito') }}</title>
    <link rel="icon" href="data:,">

    <link href="{{ mix('/css/backend-app.css') }}" rel="stylesheet" type="text/css">

    <style>
        body { background-color: #f5f5f5; }
        .login-panel { margin-top: 100px; }
    </style>
</head>

<body>

    <div class="container">
        <div class="row">
            <div class="col-md-4 col-md-offset-4">
                <div class="login-panel panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Admin Login</h3>
                    </div>
                    <div class="panel-body">

                        @if ($authentication_failed)
                            <div class="alert alert-danger text-center">
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">&times;</button>
                                Invalid email or password
                            </div>
                        @endif

                        <form action="{{ url('admin/login') }}" method="POST" role="form">
                            @csrf

                            <fieldset>
                                <div class="form-group">
                                    <input class="form-control" placeholder="Email" name="email" type="email" value="{{ old('email', '') }}" autofocus>
                                    @error('email')
                                        <span class="help-block text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <input class="form-control" placeholder="Password" name="password" type="password" value="">
                                    @error('password')
                                        <span class="help-block text-danger">{{ $message }}</span>
                                    @enderror
                                </div>

                                <button type="submit" class="btn btn-lg btn-success btn-block">Login</button>
                            </fieldset>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ mix('/js/manifest.js') }}"></script>
    <script src="{{ mix('/js/vendor.js') }}"></script>
    <script src="{{ mix('/js/backend-app.js') }}"></script>

</body>

</html>
