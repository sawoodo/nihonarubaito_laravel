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
                       <link rel="preload" as="image" type="image/webp" fetchpriority="high" href="{{ url('frontend/images/logo.webp') }}">


                    </a>
                </div>
                <!-- end navbar header -->

                <div id="navbar" class="navbar-collapse collapse">
                    <ul class="nav navbar-nav">
                        <!-- <li class="dropdown hasmenu yamm-half">
                            <a href="#" data-toggle="dropdown" class="dropdown-toggle">
                                Home
                                <b class="fa fa-angle-down"></b>
                            </a>

                            <ul class="dropdown-menu">
                                <li>
                                    <div class="yamm-content">

                                        <div class="row">
                                            <div class="col-sm-12">
                                                <div class="tabbable row-fluid">

                                                    <ul class="nav nav-tabs col-md-4">
                                                        <li class="active color2"><a href="#b" data-toggle="tab">Special Pages <i class="fa fa-angle-right"></i></a></li>
                                                        <li class="color3"><a href="#c" data-toggle="tab">Custom Pages <i class="fa fa-angle-right"></i></a></li>
                                                    </ul>

                                                    <div class="tab-content col-md-8">
                                                        <div class="tab-pane secondcol active" id="b">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <ul>
                                                                        <li><a href="job-single.html">Job Single Page</a></li>
                                                                        <li><a href="job-search.html">Job Search</a></li>
                                                                        <li><a href="job-search-map.html">Job Search Map</a></li>
                                                                        <li><a href="job-add.html">Add a Job</a></li>
                                                                        <li><a href="employer-dashboard.html">Employer Dashboard</a></li>
                                                                        <li><a href="employer-listing.html">Employer Listing</a></li>
                                                                        <li><a href="employer-profile.html">Employer Profile</a></li>
                                                                    </ul>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <ul>
                                                                        <li><a href="freelancer-profile.html">Freelancer Profile</a></li>
                                                                        <li><a href="freelancer-search.html">Freelancer Search</a></li>
                                                                        <li><a href="freelancer-search-map.html">Freelancer Search Map</a></li>
                                                                        <li><a href="freelancer-add-resume.html">Add a Resume / CV</a></li>
                                                                        <li><a href="freelancer-dashboard.html">Freelancer Dashboard</a></li>
                                                                        <li><a href="freelancer-listing.html">Freelancer Listing</a></li>
                                                                        <li><a href="freelancer-edit-resume.html">Freelancer Edit Profile</a></li>
                                                                    </ul>
                                                                </div>
                                                            </div>

                                                            <hr>

                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <p>If you are looking special job and freelancer pages, right place here! Tons of options, special pages and more.</p>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="tab-pane thirdcol" id="c">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <ul>
                                                                        <li><a href="page-about.html">About us</a></li>
                                                                        <li><a href="page-services.html">Custom Services</a></li>
                                                                        <li><a href="shop.html">Shop Page</a></li>
                                                                        <li><a href="shop-single.html">Shop Single</a></li>
                                                                        <li><a href="shop-single-alt.html">Shop Single Alt</a></li>
                                                                        <li><a href="shop-cart.html">Shop Cart</a></li>
                                                                        <li><a href="shop-checkout.html">Shop Checkout</a></li>
                                                                    </ul>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <ul>
                                                                        <li><a href="page-pricing.html">Plans & Pricing</a></li>
                                                                        <li><a href="page-testimonial.html">Testimonials</a></li>
                                                                        <li><a href="page-shortcodes.html">Shortcodes</a></li>
                                                                        <li><a href="page-contact.html">Contact Page</a></li>
                                                                        <li><a href="page-login.html">Login & Register</a></li>
                                                                        <li><a href="page-faqs.html">FAQS Page</a></li>
                                                                        <li><a href="page-404.html">Not Found</a></li>
                                                                    </ul>
                                                                </div>
                                                            </div>

                                                            <hr>

                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <p>Looking creative page examples? Sure, we build special page examples for you. About, contact, register and more.</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </li>
                            </ul>
                        </li> -->
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
