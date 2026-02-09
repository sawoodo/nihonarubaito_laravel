<div class="navbar-default sidebar" role="navigation">
    <div class="sidebar-nav navbar-collapse">
        <ul class="nav" id="side-menu">
            <li>
                <a href="{{ url('admin/areas') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'areas' ? 'class="active"' : '' !!}>
                    <i class="fa fa-map-marker fa-2x fa-fw tw-text-red-500"></i> Areas
                </a>
            </li>

            <li>
                <a href="{{ url('admin/categories') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'categories' ? 'class="active"' : '' !!}>
                    <i class="fa fa-flash fa-2x fa-fw tw-text-amber-400"></i> Categories
                </a>
            </li>

            <li>
                <a href="{{ url('admin/works') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'work' ? 'class="active"' : '' !!}>
                    <i class="fa fa-cogs fa-2x fa-fw tw-text-red-600"></i> Works
                </a>
            </li>

            <li>
                <a href="{{ url('admin/work-descriptions') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'work-descriptions' ? 'class="active"' : '' !!}>
                    <i class="fa fa-info-circle fa-2x fa-fw tw-text-orange-500"></i> Work Descriptions
                </a>
            </li>

            <li>
                <a href="{{ url('admin/images') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'images' ? 'class="active"' : '' !!}>
                    <i class="fa fa-file-image-o fa-2x fa-fw tw-text-lime-500"></i> Gallery
                </a>
            </li>

            @if ($role_id == 1)
                <li>
                    <a href="{{ url('admin/scrapped-data') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'scrapped-data' ? 'class="active"' : '' !!}>
                        <i class="fa fa-database fa-2x fa-fw tw-text-green-500"></i> Scrapped Data
                    </a>
                </li>
            @endif

            <li>
                <a href="{{ url('admin/jobs') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'jobs' ? 'class="active"' : '' !!}>
                    <i class="fa fa-black-tie fa-2x fa-fw tw-text-violet-500"></i> Jobs
                </a>
            </li>

            @if ($role_id == 1)
                <li>
                    <a href="{{ url('admin/application-logs') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'application-logs' ? 'class="active"' : '' !!}>
                        <i class="fa fa-pie-chart fa-2x fa-fw tw-text-purple-700"></i> Application Logs
                    </a>
                </li>

                <li>
                    <a href="{{ url('admin/secondary-applies') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'secondary-applies' ? 'class="active"' : '' !!}>
                        <i class="fa fa-signal fa-2x fa-fw tw-text-purple-700"></i> Secondary Applies
                    </a>
                </li>

                <li>
                    <a href="{{ url('admin/fb-scheduled-posts') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'fb_scheduled_posts' ? 'class="active"' : '' !!}>
                        <i class="fa fa-facebook-official fa-2x fa-fw tw-text-blue-500"></i> Scheduled Post
                    </a>
                </li>

                <li>
                    <a href="{{ url('admin/blog-posts') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'blog-posts' ? 'class="active"' : '' !!}>
                        <i class="fa fa-rss-square fa-2x fa-fw tw-text-indigo-600"></i> Blog Posts
                    </a>
                </li>

                <li>
                    <a href="{{ url('admin/subscribers') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'subscribers' ? 'class="active"' : '' !!}>
                        <i class="fa fa-envelope fa-2x fa-fw tw-text-rose-500"></i> Subscribers
                    </a>
                </li>

                <li>
                    <a href="{{ url('admin/users') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'users' ? 'class="active"' : '' !!}>
                        <i class="fa fa-users fa-2x fa-fw tw-text-purple-500"></i> Users
                    </a>
                </li>
            @endif

            <li>
                <a href="{{ url('admin/change-password') }}" {!! isset($activeSideMenu) && $activeSideMenu === 'change_password' ? 'class="active"' : '' !!}>
                    <i class="fa fa-key fa-2x fa-fw tw-text-emerald-500"></i> Change Password
                </a>
            </li>
        </ul>
    </div>
</div>
