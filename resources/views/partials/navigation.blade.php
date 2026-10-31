<header class="header">
    <div class="container-fluid">
        <nav class="navbar navbar-default yamm">
            <div class="container">

                <div class="navbar-header">
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>

                    <a class="navbar-brand" href="{{ url('/') }}">
                        <img src="{{ url('frontend/images/logo.webp') }}" alt="nihonarubaito" width="250" height="50" fetchpriority="high">
                    </a>
                </div>
                <!-- end navbar header -->

                <div id="navbar" class="navbar-collapse collapse">
                    <ul class="nav navbar-nav">
                        <li{!! (isset($active_nav) && $active_nav == 'jobs') ? ' class="active"' : '' !!}>
                            <a href="{{ url('/') }}">{{ $content->nav_jobs_label }}</a>
                        </li>

                        <li{!! (isset($active_nav) && $active_nav == 'subscribe') ? ' class="active"' : '' !!}>
                            <a href="{{ url('subscribe') }}">{{ $content->nav_subscribe_label }}</a>
                        </li>
                        <li{!! (isset($active_nav) && $active_nav == 'contact') ? ' class="active"' : '' !!}>
                            <a href="{{ url('contact') }}">
                                {{ $content->nav_contact_label }}
                            </a>
                        </li>

                         <!-- <li{!! (isset($active_nav) && $active_nav == 'blog') ? ' class="active"' : '' !!}>
                            <a href="http://blog.nihonarubaito.com/">{{ $content->nav_blog_label ?? '' }}</a>
                        </li>  -->
                    </ul>

                    <ul class="nav navbar-nav navbar-right">
                        <li>
                            @if (empty($loggedin))
                            <a href="{{ url('account') }}" role="button">
                                {{ $content->nav_sign_in_label }}
                            </a>
                            @else
                            <a href="{{ url('account/logout') }}" >
                                {{ $content->nav_sign_out_label }}
                            </a>
                            @endif
                        </li>
                    </ul>



                    @if (!empty($loggedin))
                    <ul class="nav navbar-nav navbar-right">
                        <li class="dropdown yamm-half membermenu hasmenu">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                                <span style="padding: 0 10px;">
                                    {{ $content->menu_dashboard_label }}
                                </span>
                                <span class="caret"></span>
                            </a>

                            <ul class="dropdown-menu start-right">
                                <li class="dropdown-header">
                                    {{ $content->menu_welcome_label }}
                                    {{ $user->first_name ?? '' }} {{ $user->last_name ?? '' }}
                                </li>
                                <li>
                                    <a href="{{ url('profile') }}">
                                        <span class="fa fa-user fa-fw"></span> {{ $content->menu_my_profile_label }}
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ url('jobs/preference') }}">
                                        <span class="fa fa-cog fa-fw"></span> {{ $content->menu_job_preference_label }}
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ url('jobs/suggested') }}">
                                        <span class="fa fa-bullseye fa-fw"></span> {{ $content->menu_suggested_jobs_label }}
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ url('jobs/applied') }}">
                                        <span class="fa fa-check fa-fw"></span> {{ $content->menu_applied_jobs_label }}
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ url('account/change-password') }}">
                                        <span class="fa fa-key fa-fw"></span> {{ $content->menu_change_password_label }}
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ url('account/logout') }}">
                                        <span class="fa fa-power-off fa-fw"></span> {{ $content->nav_sign_out_label }}
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                    @endif
                    <!-- end dropdown -->
                </div>
                <!--/.nav-collapse -->

            </div>
            <!--/.container-fluid -->
        </nav>
    </div>
    <!-- end container -->
</header>
