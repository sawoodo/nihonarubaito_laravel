
        <!-- DEFAULT JAVASCRIPT FILES -->
        <!-- ********************************************** -->
        <script src="{{ mix('/js/manifest.js') }}" defer></script>
	    <script src="{{ mix('/js/vendor.js') }}" defer></script>
        <script src="{{ mix('/js/front-app.js') }}" defer></script>


        @if (isset($js))
            @foreach ((array) $js as $key => $file)
                @if ($key === 'mix')
                    @if (is_array($file))
                        @foreach ($file as $_file)
                            <script src="{{ mix($_file) }}" type="text/javascript" defer></script>
                        @endforeach
                    @endif
                @else
                    <script src="{{ url($file) }}" type="text/javascript" defer></script>
                @endif
            @endforeach
        @endif

        @stack('page-scripts')

            <!-- <script>
                (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
                (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
                m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
                })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

                ga('create', 'UA-89925520-1', 'auto');
                ga('send', 'pageview');
            </script> -->

        <script>
            function analyticsOnScroll() {
                var head = document.getElementsByTagName('head')[0]
                var script = document.createElement('script')
                script.type = 'text/javascript';
                script.src = 'https://www.googletagmanager.com/gtag/js?id=G-8ZMLZ8JK5L'
                head.appendChild(script);
                document.removeEventListener('scroll', analyticsOnScroll);
            };
            document.addEventListener('scroll', analyticsOnScroll);
        </script>

        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', 'G-8ZMLZ8JK5L');
        </script>
