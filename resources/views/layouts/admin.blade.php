<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Backend - {{ $site_name }}</title>
    <link rel="icon" href="data:,">

    <link href="{{ mix('/css/backend-app.css') }}" rel="stylesheet" type="text/css">

    @stack('page-styles')

    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
        <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

<body>

    <div id="wrapper">

        <!-- Navigation -->
        <nav class="navbar navbar-default navbar-static-top" role="navigation" style="margin-bottom: 0">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="{{ url('admin') }}">
                    {{ $site_name }} - Backend
                </a>
            </div>

            <ul class="nav navbar-top-links navbar-right">
                <li>
                    <a href="{{ url('admin/logout') }}">
                        Logout
                    </a>
                </li>
            </ul>

            @include('partials.admin.sidebar')
        </nav>

        <!-- Page Content -->
        <div id="page-wrapper">
            @yield('content')
        </div>
        <!-- /#page-wrapper -->

        <input type="hidden" id="base_url" value="{{ url('/') }}/">

    </div>
    <!-- /#wrapper -->

    <script src="{{ mix('/js/manifest.js') }}"></script>
    <script src="{{ mix('/js/vendor.js') }}"></script>
    <script src="{{ mix('/js/backend-app.js') }}"></script>
    <script src="{{ mix('/global/index.js') }}"></script>

    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    </script>

    @stack('page-scripts')

</body>

</html>
