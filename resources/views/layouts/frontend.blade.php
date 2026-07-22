<!DOCTYPE html>
<html lang="en">

<head>
      <link rel="alternate" href="https://nihonarubaito.com/" hreflang="en-jp" />

	 <link rel="canonical" href="{{ $canonical ?? 'https://nihonarubaito.com' }}"/>
    <!-- Preload the logo image with as="image" -->
    <link rel="preload" href="{{ url('frontend/images/logo.webp') }}" as="image" type="image/webp" fetchpriority="high">



    @if (app()->environment('production'))


        <meta name="msvalidate.01" content="0A462D3D792FB7C2F504AA6C33F11ED9" />
        <meta name="ahrefs-site-verification" content="c9537c42e07b30d0fb1cfb6400ac1d3407d9ea24de2e3de761673dd747a3fa3a">

        @if(isset($load_value_commerce) && (bool) $load_value_commerce === true)
            <script type="text/javascript" language="javascript">var vc_pid ="885252281";</script>
            <script type="text/javascript" src="//aml.valuecommerce.com/vcdal.js" async></script>
        @endif


        {{-- Staggered third-party loading: single scroll listener, offsets prevent main-thread pile-up --}}
        <script>
        (function(){
            var fired=false;
            function onInteract(){
                if(fired)return;fired=true;
                document.removeEventListener('scroll',onInteract);
                document.removeEventListener('click',onInteract);

                // 1) AdSense — immediate (ads most time-sensitive)
                var as=document.createElement('script');as.async=true;
                as.src='//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js';
                as.onload=function(){(adsbygoogle=window.adsbygoogle||[]).push({google_ad_client:"ca-pub-5261166510941827",enable_page_level_ads:true})};
                document.head.appendChild(as);

                // 2) GA4 — 1s delay
                setTimeout(function(){
                    var gs=document.createElement('script');gs.async=true;
                    gs.src='https://www.googletagmanager.com/gtag/js?id=G-8ZMLZ8JK5L';
                    document.head.appendChild(gs);
                },1000);

                // 3) Facebook Pixel — 2s delay
                setTimeout(function(){
                    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
                    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
                    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}
                    (window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
                    fbq('init','928026947957142');fbq('track','PageView');
                },2000);
            }
            document.addEventListener('scroll',onInteract,{once:true,passive:true});
            document.addEventListener('click',onInteract,{once:true});
            setTimeout(onInteract,5000);
        })();
        </script>

        <!-- Global Site Tag (gtag.js) - Google Analytics -->
       <!-- <script async src="https://www.googletagmanager.com/gtag/js?id=UA-89925520-1"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', 'UA-89925520-1');
        </script>

        <script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
        <script>
            (adsbygoogle = window.adsbygoogle || []).push({
                google_ad_client: "ca-pub-5261166510941827",
                enable_page_level_ads: true
            });
        </script>  -->
        <!-- Auto Ads -->

        <!-- Google tag 4 (gtag.js)
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-8ZMLZ8JK5L"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', 'G-8ZMLZ8JK5L'); -->
        </script>

    @endif

    <meta charset="utf-8">
    @yield('status_headers')
    @if (!app()->environment('production'))
        <meta name="robots" content="noindex" />
    @endif

    <meta name="google-site-verification" content="wnPHh1WTXhupwh42KsDIBKqMJ7fAg5AbTs6py2-ye5s" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">

    <!-- SITE META -->
    <title>{{ $page_title ?? $site_name }}</title>
    <meta name="description" content="{{ $page_description ?? '' }}">

    {!! isset($keywords) && $keywords ? '<meta name="keywords" content="' . $keywords . '" />' : '' !!}
    <meta property="og:url" content="{{ $og_url ?? '' }}" />
    {!! isset($og_title) && $og_title ? '<meta property="og:title" content="' . $og_title . '" />' : '' !!}
    <meta property="og:description" content="{{ $og_description ?? '' }}" />
    <meta property="og:site_name" content="Nihon Arubaito - Part-time Jobs in Japan" />
    <meta property="og:type" content="{{ $og_type ?? 'article' }}" />
    <meta property="og:image" content="{{ $og_image ?? url('frontend/images/og-default.png') }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:image" content="{{ $og_image ?? url('frontend/images/og-default.png') }}" />
    <!-- FAVICONS -->
    <link rel="shortcut icon" href="{{ url('frontend/images/favicon.ico') }}" type="image/x-icon">






    <!-- Preload critical fonts -->
    <link rel="preload" href="/fonts/poppins-medium-webfont.woff2" as="font" type="font/woff2" crossorigin="anonymous">
    <link rel="preload" href="/fonts/poppins-light-webfont.woff2" as="font" type="font/woff2" crossorigin="anonymous">
    <link rel="preload" href="/fonts/fontawesome-webfont.woff2" as="font" type="font/woff2" crossorigin="anonymous">

    <!-- CSS loaded asynchronously to eliminate render-blocking -->
    <link rel="stylesheet" href="{{ mix('/css/front-app.css') }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ mix('/css/front-app.css') }}"></noscript>


    @if (isset($css))
        <!-- Custom CSS -->
        @foreach ((array) $css as $file)
        <link href="{{ url($file) }}" rel="stylesheet" type="text/css">
        @endforeach
    @endif

    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
    <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->

    @stack('structured-data')

</head>
<body>

    <!-- START SITE -->
    <div id="wrapper">

        @include('partials.topbar')

        @include('partials.navigation')

        <main>
        @yield('content')
        </main>

        @include('partials.footer')

        <input type="hidden" name="base_url" id="base_url" value="{{ url('/') }}/">
        <input type="hidden" id="lang_selected" value="{{ $lang_selected ?? 0 }}">

        @include('partials.scripts')

    </body>
</html>
