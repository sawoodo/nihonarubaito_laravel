            <div class="section footer">
                <div class="container">
                    <div class="row">
                        <div class="col-md-10 col-md-offset-1 text-center">
                            <div class="widget clearfix">
                                <p>
                                    @if (empty($user_lang) || $user_lang == 1)
                                        NihonArubaito is providing part-time jobs information for people who are living and looking for job in Japan. You can see all the jobs, their complete information and apply online for free. In NihonArubaito.com, part-time job information is available in different languages(Chinese, English and Vietnamese). Please subscribe to get extra benifets for free
                                    @elseif ($user_lang == 2)
                                        Nihon Arubaito为在日本生活和寻找工作的人提供兼职工作信息。 你可以看到所有的工作，他们的完整信息，并免费在线申请。 在NihonArubaito.com，兼职工作信息有不同的语言（中文，英语和越南语）。 请订阅免费获得额外的益处
                                    @elseif ($user_lang == 3)
                                        NihonArubaito đang cung cấp một phần thông tin thời gian việc làm cho những người đang sống và tìm kiếm việc làm tại Nhật Bản. Bạn có thể xem tất cả các công việc, thông tin đầy đủ và nộp đơn trực tuyến miễn phí. Trong NihonArubaito.com, thông tin việc làm bán thời gian có sẵn trong các ngôn ngữ khác nhau (tiếng Trung, tiếng Anh và tiếng Việt). Hãy đăng ký để có benifets thêm miễn phí
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

                                <div style="margin-top: 15px;">
                                    <a href="{{ url('privacy-policy') }}">Privacy Policy</a> &middot;
                                    <a href="{{ url('terms-of-service') }}">Terms of Service</a> &middot;
                                    <a href="{{ url('faq') }}">FAQ</a> &middot;
                                    <a href="{{ url('contact') }}">Contact</a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
