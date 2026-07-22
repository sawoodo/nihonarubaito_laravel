            <div class="section footer">
                <div class="container">
                    <div class="row">
                        <div class="col-md-10 col-md-offset-1 text-center">
                            <div class="widget clearfix">
                                <p>
                                    @if (empty($user_lang) || $user_lang == 1)
                                        Nihon Arubaito provides part-time job listings in English for foreign residents in Japan. Browse jobs, subscribe for alerts, and apply online for free.
                                    @elseif ($user_lang == 2)
                                        Nihon Arubaito为在日本的外国居民提供英语兼职工作信息。浏览工作、订阅提醒并免费在线申请。
                                    @elseif ($user_lang == 3)
                                        Nihon Arubaito cung c&#7845;p th&ocirc;ng tin vi&#7879;c l&agrave;m b&aacute;n th&#7901;i gian b&#7857;ng ti&#7871;ng Anh cho ng&#432;&#7901;i n&#432;&#7899;c ngo&agrave;i t&#7841;i Nh&#7853;t B&#7843;n. Duy&#7879;t vi&#7879;c l&agrave;m, &#273;&#259;ng k&yacute; nh&#7853;n th&ocirc;ng b&aacute;o v&agrave; &#7913;ng tuy&#7875;n tr&#7921;c tuy&#7871;n mi&#7877;n ph&iacute;.
                                    @elseif ($user_lang == 4)
                                        にほんアルバイトは、英語、ベトナム語、中国語で読める、日本で住んでいる働きたい外国人のためのアルバイト求人情報サイトです。
                                    @endif
                                </p>

                                <!-- <div class="link-widget">
                                    <div id="fb-root"></div>
                                                                        <script>
                                            (function(d, s, id) {
                                                var js, fjs = d.getElementsByTagName(s)[0];
                                                if (d.getElementById(id)) return;
                                                js = d.createElement(s); js.id = id;
                                                js.src = "//connect.facebook.net/en_GB/sdk.js#xfbml=1&version=v2.8";
                                                fjs.parentNode.insertBefore(js, fjs);
                                            }(document, 'script', 'facebook-jssdk'));
                                        </script>

                                    <div class="fb-page" data-href="https://www.facebook.com/nihonarubaito/" data-small-header="false" data-adapt-container-width="true" data-hide-cover="false" data-show-facepile="true">
                                        <blockquote cite="https://www.facebook.com/nihonarubaito/" class="fb-xfbml-parse-ignore">
                                            <a href="https://www.facebook.com/nihonarubaito/">NihonArubaito.com</a>
                                        </blockquote>
                                    </div>

                                </div> -->

                                <div style="margin-top: 15px; color: hsla(0,0%,100%,.4);">
                                    <a href="{{ url('bed-making-jobs-in-japan') }}" style="color: hsla(0,0%,100%,.4);">Bed Making Jobs</a> &middot;
                                    <a href="{{ url('sorting-jobs-in-japan') }}" style="color: hsla(0,0%,100%,.4);">Sorting Jobs</a> &middot;
                                    <a href="{{ url('dish-washing-jobs-in-japan') }}" style="color: hsla(0,0%,100%,.4);">Dish Washing Jobs</a> &middot;
                                    <a href="{{ url('light-work-jobs-in-japan') }}" style="color: hsla(0,0%,100%,.4);">Light Work Jobs</a> &middot;
                                    <a href="{{ url('restaurant-jobs-in-japan') }}" style="color: hsla(0,0%,100%,.4);">Restaurant Jobs</a> &middot;
                                    <a href="{{ url('convenience-store-jobs-in-japan') }}" style="color: hsla(0,0%,100%,.4);">Convenience Store Jobs</a>
                                </div>

                                <div style="margin-top: 10px; color: hsla(0,0%,100%,.4);">
                                    <a href="{{ url('privacy-policy') }}" style="color: hsla(0,0%,100%,.4);">Privacy Policy</a> &middot;
                                    <a href="{{ url('terms-of-service') }}" style="color: hsla(0,0%,100%,.4);">Terms of Service</a> &middot;
                                    <a href="{{ url('faq') }}" style="color: hsla(0,0%,100%,.4);">FAQ</a> &middot;
                                    <a href="{{ url('contact') }}" style="color: hsla(0,0%,100%,.4);">Contact</a>
                                </div>

                                <p style="margin-top: 15px; color: hsla(0,0%,100%,.4); font-size: 13px;">&copy; 2016&ndash;{{ date('Y') }} Nihon Arubaito. All rights reserved.</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
