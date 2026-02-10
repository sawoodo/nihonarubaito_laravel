@extends('layouts.frontend')

@section('content')
    <div class="section wb no-top-padding">
        <div class="container">

            <div class="row">
                <div class="col-md-12">
                    <h1 class="page-header text-center h3">Frequently Asked Questions</h1>
                </div>
            </div>

            <div class="row">
                <div class="col-md-10 col-md-offset-1">

                    <h3>General</h3>

                    <h4>What is Nihonarubaito.com?</h4>
                    <p>
                        Nihonarubaito.com is a free job board for foreigners seeking part-time work (arubaito) in Japan. We aggregate and translate job listings from Japanese job sites so you can browse opportunities in English, Vietnamese, Chinese, Japanese, and Korean.
                    </p>

                    <h4>What does "arubaito" mean?</h4>
                    <p>
                        "Arubaito" (アルバイト) is the Japanese term for part-time work. It comes from the German word "Arbeit" (meaning "work"). In Japan, arubaito refers to part-time or temporary employment, commonly held by students, homemakers, and foreigners.
                    </p>

                    <h4>Is it free to use?</h4>
                    <p>
                        Yes, Nihonarubaito.com is completely free for job seekers. There are no registration fees, subscription costs, or hidden charges. You can browse and access all job listings at no cost.
                    </p>

                    <h3>Finding Jobs</h3>

                    <h4>How do I search for jobs?</h4>
                    <p>
                        Use the search form on the homepage to filter jobs by keyword, prefecture, area, or job category. You can also browse jobs by clicking on specific prefectures or stations listed on the site.
                    </p>

                    <h4>How do I apply for a job?</h4>
                    <p>
                        Click on any job listing to view the full details. Each listing includes an apply button or link that will direct you to the employer's application process. You contact the employer directly — Nihonarubaito.com does not process applications.
                    </p>

                    <h4>What areas do you cover?</h4>
                    <p>
                        We list part-time jobs across all 47 prefectures of Japan, with the largest concentration in major cities like Tokyo, Osaka, Nagoya, Kyoto, and Fukuoka.
                    </p>

                    <h4>How often are jobs updated?</h4>
                    <p>
                        New jobs are added regularly. However, since listings are sourced from third-party sites, availability may change at any time. Always verify the position is still open directly with the employer.
                    </p>

                    <h3>About the Jobs</h3>

                    <h4>What does "daily payment" or "hand cash" mean?</h4>
                    <p>
                        Some part-time jobs in Japan pay on a daily basis (日払い, nippai) rather than the typical monthly pay cycle. "Hand cash" (手渡し, tewatashi) means you receive your wages in cash at the end of the work day. These are common for short-term or single-day jobs.
                    </p>

                    <h4>Are the jobs listed in English?</h4>
                    <p>
                        Our job listings are translated into English (and other languages) for your convenience. However, the actual workplace may require basic Japanese communication skills. Each listing notes the Japanese language level expected.
                    </p>

                    <h4>Do I need a work visa?</h4>
                    <p>
                        Yes, you need valid work authorization to work in Japan. This includes a work visa, a student visa with a "Permission to Engage in Activity Other Than That Permitted" (資格外活動許可), or permanent residency. Working without proper authorization is illegal in Japan.
                    </p>

                    <h3>Staying Updated</h3>

                    <h4>How can I get notified about new jobs?</h4>
                    <p>
                        Visit the <a href="{{ url('subscribe') }}">Subscribe</a> page and set your preferred job categories and locations. We will email you when new matching jobs are posted.
                    </p>

                    <h4>I have more questions. How can I reach you?</h4>
                    <p>
                        Please visit our <a href="{{ url('contact') }}">Contact</a> page to send us a message, or email us directly at support@nihonarubaito.com.
                    </p>

                </div>
            </div>
        </div>
    </div>
@endsection
