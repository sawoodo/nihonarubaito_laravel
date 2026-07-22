@extends('layouts.frontend')

@include('partials.breadcrumb-schema')

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

                    <h2>About Nihon Arubaito</h2>

                    <h3>What is Nihon Arubaito?</h3>
                    <p>Nihon Arubaito is a job platform built specifically for foreign residents in Japan who are looking for part-time work (&#12450;&#12523;&#12496;&#12452;&#12488; / arubaito). We provide job listings in English so you can understand the details &ndash; location, pay, working hours, and requirements &ndash; before you apply. The platform currently lists {{ $faqData['active_jobs'] }} active positions across all 47 prefectures, with Tokyo, Osaka, Aichi, and Fukuoka having the most listings. Over {{ $faqData['total_subscribers'] }} foreign residents use our platform to find work.</p>

                    <h3>Is Nihon Arubaito free?</h3>
                    <p>Yes, completely free. There are no registration fees, no subscription charges, and no hidden costs. You can browse all job listings, subscribe for job alerts, and apply to positions at no cost.</p>

                    <h3>What does &ldquo;arubaito&rdquo; mean?</h3>
                    <p>Arubaito (&#12450;&#12523;&#12496;&#12452;&#12488;) is the Japanese word for part-time work. It comes from the German word &ldquo;Arbeit&rdquo; meaning work. In Japan, arubaito is the standard term for part-time or temporary employment. You will see it written on job postings, storefronts, and employment websites throughout Japan. The short form &ldquo;baito&rdquo; (&#12496;&#12452;&#12488;) is also commonly used.</p>

                    <h2>Finding Jobs</h2>

                    <h3>What types of part-time jobs are available for foreigners in Japan?</h3>
                    <p>The most common part-time jobs for foreigners on our platform are restaurant work (&#12524;&#12473;&#12488;&#12521;&#12531;), packing and sorting in warehouses (&#20181;&#20998;&#12369; / shiwake), hotel bed making and cleaning (&#12505;&#12483;&#12489;&#12513;&#12452;&#12461;&#12531;&#12464;), convenience store work (&#12467;&#12531;&#12499;&#12491;), and delivery. Based on our platform data, packing and sorting is the most requested category among subscribers, but restaurant jobs receive the most applications. Warehouse and sorting positions tend to have less competition per opening and are often the easiest to get hired for since they require minimal Japanese and customer interaction.</p>

                    <h3>What is a &ldquo;hand cash&rdquo; job?</h3>
                    <p>Hand cash (&#25163;&#28193;&#12375; / tewatashi) means you receive your wages in cash directly at the end of your shift rather than through a monthly bank transfer. Hand cash jobs are especially popular among foreign workers who need immediate income or have not yet set up a Japanese bank account. Common hand cash positions include warehouse sorting, event setup, moving assistance, and some cleaning jobs. You can browse hand cash jobs by prefecture on our platform &ndash; for example, <a href="{{ url('hand-cash-jobs-in-tokyo') }}">hand cash jobs in Tokyo</a> or <a href="{{ url('hand-cash-jobs-in-osaka') }}">hand cash jobs in Osaka</a>.</p>

                    <h3>What is the difference between &ldquo;daily payment&rdquo; and &ldquo;hand cash&rdquo;?</h3>
                    <p>Daily payment (&#26085;&#25173;&#12356; / nippai) means your wages are calculated daily, but the actual payment may arrive in your bank account the next business day or within a few days. Hand cash (&#25163;&#28193;&#12375; / tewatashi) means you receive physical cash at the end of your working day. Some jobs offer both &ndash; daily calculation with same-day cash payment. If you specifically need cash in hand the same day, look for listings that mention &#25163;&#28193;&#12375;. You can browse <a href="{{ url('daily-payment-jobs-in-tokyo') }}">daily payment jobs</a> on our platform.</p>

                    <h3>Which areas have the most jobs?</h3>
                    <p>Based on our current listings, Tokyo has the most active positions, followed by Osaka, Aichi (Nagoya area), Kanagawa (Yokohama/Kawasaki), Saitama, Chiba, Fukuoka, and Kyoto. Within Tokyo, the areas with the highest job concentration are around Shinjuku, Shibuya, Ikebukuro, Shinagawa, and Akihabara stations. In Osaka, Umeda, Namba, and Tennoji have the most listings. If you are in a smaller prefecture with fewer listings, consider checking neighboring prefectures &ndash; many workers commute across prefecture borders for better job options.</p>

                    <h2>Wages and Earnings</h2>

                    <h3>How much can I earn working part-time in Japan?</h3>
                    <p>Part-time wages in Japan vary by prefecture and job type. The national average minimum wage is &yen;1,121 per hour. Tokyo has the highest minimum wage at &yen;1,226 per hour, followed by Kanagawa at &yen;1,225. On our platform, hourly wages for active listings range from the prefectural minimum up to &yen;2,000+ for specialized or night shift positions. Shifts after 10 PM are required by law to pay a 25% deep night premium (&#28145;&#22812;&#25163;&#24403; / shinya teate), so night work can pay &yen;1,500 per hour or more. A student working 28 hours per week at &yen;1,200 per hour would earn approximately &yen;134,000 per month before taxes.</p>

                    <h3>What is the minimum wage in Japan?</h3>
                    <p>Japan sets minimum wages by prefecture, updated every October. As of October 2025, the highest minimum wages are Tokyo (&yen;1,226), Kanagawa (&yen;1,225), Osaka (&yen;1,202), Saitama (&yen;1,178), and Aichi (&yen;1,152). The lowest minimum wages are in Akita and Iwate at &yen;951. Employers are legally required to pay at least the prefectural minimum wage, regardless of your nationality or visa status. If an employer offers below minimum wage, that is illegal &ndash; do not accept it.</p>

                    <h2>Visas and Work Rules</h2>

                    <h3>Can I work part-time in Japan as a foreigner?</h3>
                    <p>Yes, but you need proper work authorization. The most common situations are: student visa holders who have obtained a Permission to Engage in Activity Other Than That Permitted (&#36039;&#26684;&#22806;&#27963;&#21205;&#35377;&#21487; / shikakugai katsudou kyoka), dependent visa holders with the same permission, working holiday visa holders, spouse visa holders, and permanent residents. If you are on a student or dependent visa, you must apply for the work permission at your local immigration office before you start working. Working without authorization is a serious immigration violation that can result in visa cancellation and deportation.</p>

                    <h3>How many hours can I work on a student visa?</h3>
                    <p>Students with work permission can work up to 28 hours per week during regular school terms. During official school breaks (summer, winter, spring holidays), you can work up to 40 hours per week. These limits are strictly enforced &ndash; immigration tracks your working hours through your employer's tax reporting, and exceeding the limit is one of the most common reasons for student visa renewal rejection. If you work at multiple jobs, the 28-hour limit applies to the combined total across all employers.</p>

                    <h3>What documents do I need for a job interview?</h3>
                    <p>Always bring your residence card (&#22312;&#30041;&#12459;&#12540;&#12489; / zairyuu kaado) &ndash; employers are legally required to verify your identity and work permission. Some employers will also ask for your My Number card (&#12510;&#12452;&#12490;&#12531;&#12496;&#12540;&#12459;&#12540;&#12489;) or notification letter. Depending on the employer, you may need a Japanese-format resume called rirekisho (&#23653;&#27508;&#26360;), which you can buy at any convenience store for about &yen;100-200. Arrive a few minutes early, dress neatly, and greet the interviewer with a polite bow. Many part-time job interviews in Japan are brief &ndash; 15 to 30 minutes.</p>

                    <h3>Do I need to speak Japanese to work part-time?</h3>
                    <p>It depends on the job type. Warehouse sorting, packing, hotel bed making, and factory work require minimal Japanese &ndash; basic greetings and understanding simple instructions is usually enough, and many of your coworkers will also be foreign residents. Convenience store and restaurant jobs typically require conversational Japanese since you interact with customers. On Nihon Arubaito, each listing indicates the expected Japanese level. If you are just starting out with limited Japanese, warehouse and cleaning positions are usually the best entry point.</p>

                    <h2>Using the Platform</h2>

                    <h3>How do I get notified about new jobs?</h3>
                    <p>Visit the <a href="{{ url('subscribe') }}">Subscribe</a> page and set your preferred prefecture, job categories, specific areas, payment type (hand cash, daily payment, or monthly), and shift preferences. You can choose to receive alerts weekly, daily, or immediately when a matching job is posted. You can also set special alerts for hand cash jobs or positions above a certain wage.</p>

                    <h3>How do I apply for a job on Nihon Arubaito?</h3>
                    <p>Browse the job listings and click on any position to see the full details in English, including wages, working hours, station access, and requirements. When you find a job you want to apply for, click the apply button. This takes you to the employer's application page where you complete the process. Nihon Arubaito provides the job information in English, but the application process with the employer will typically be in Japanese. If you need help, ask a Japanese-speaking friend, your school's career office, or the Hello Work (&#12495;&#12525;&#12540;&#12527;&#12540;&#12463;) office in your area.</p>

                    <h3>I have a question not answered here. How can I reach you?</h3>
                    <p>Please visit our <a href="{{ url('contact') }}">Contact</a> page or email us directly at support@nihonarubaito.com. We typically respond within 1-2 business days.</p>

                </div>
            </div>
        </div>
    </div>
@endsection

@push('structured-data')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => [
        [
            '@type' => 'Question',
            'name' => 'What is Nihon Arubaito?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Nihon Arubaito is a job platform built specifically for foreign residents in Japan who are looking for part-time work (arubaito). We provide job listings in English so you can understand the details before you apply. The platform lists positions across all 47 prefectures, with Tokyo, Osaka, Aichi, and Fukuoka having the most listings.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'Is Nihon Arubaito free?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Yes, completely free. There are no registration fees, no subscription charges, and no hidden costs. You can browse all job listings, subscribe for job alerts, and apply to positions at no cost.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'What does "arubaito" mean?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Arubaito is the Japanese word for part-time work. It comes from the German word "Arbeit" meaning work. In Japan, arubaito is the standard term for part-time or temporary employment. The short form "baito" is also commonly used.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'What types of part-time jobs are available for foreigners in Japan?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'The most common part-time jobs for foreigners are restaurant work, packing and sorting in warehouses, hotel bed making and cleaning, convenience store work, and delivery. Warehouse and sorting positions tend to have less competition and require minimal Japanese.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'What is a "hand cash" job?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Hand cash (tewatashi) means you receive your wages in cash directly at the end of your shift rather than through a monthly bank transfer. Common hand cash positions include warehouse sorting, event setup, moving assistance, and some cleaning jobs.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'What is the difference between "daily payment" and "hand cash"?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Daily payment (nippai) means your wages are calculated daily, but the actual payment may arrive in your bank account the next business day or within a few days. Hand cash (tewatashi) means you receive physical cash at the end of your working day.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'Which areas have the most jobs?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Tokyo has the most active positions, followed by Osaka, Aichi (Nagoya area), Kanagawa (Yokohama/Kawasaki), Saitama, Chiba, Fukuoka, and Kyoto. Within Tokyo, the highest job concentration is around Shinjuku, Shibuya, Ikebukuro, Shinagawa, and Akihabara stations.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'How much can I earn working part-time in Japan?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Part-time wages vary by prefecture and job type. Tokyo has the highest minimum wage at \u00a51,226 per hour. Hourly wages range from the prefectural minimum up to \u00a52,000+ for specialized or night shift positions. Shifts after 10 PM pay a 25% deep night premium.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'What is the minimum wage in Japan?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Japan sets minimum wages by prefecture, updated every October. As of October 2025, the highest are Tokyo (\u00a51,226), Kanagawa (\u00a51,225), Osaka (\u00a51,202), Saitama (\u00a51,178), and Aichi (\u00a51,152). Employers must pay at least the prefectural minimum wage regardless of nationality.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'Can I work part-time in Japan as a foreigner?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Yes, but you need proper work authorization. Student and dependent visa holders need a Permission to Engage in Activity Other Than That Permitted. Working holiday visa holders, spouse visa holders, and permanent residents can also work part-time.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'How many hours can I work on a student visa?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Students with work permission can work up to 28 hours per week during regular school terms, and up to 40 hours per week during official school breaks. The 28-hour limit applies to the combined total across all employers.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'What documents do I need for a job interview?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Always bring your residence card (zairyuu kaado). Some employers also ask for your My Number card. You may need a Japanese-format resume called rirekisho, available at any convenience store for about \u00a5100-200.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'Do I need to speak Japanese to work part-time?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'It depends on the job type. Warehouse sorting, packing, hotel bed making, and factory work require minimal Japanese. Convenience store and restaurant jobs typically require conversational Japanese. Each listing on Nihon Arubaito indicates the expected Japanese level.',
            ],
        ],
        [
            '@type' => 'Question',
            'name' => 'How do I get notified about new jobs?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Visit the Subscribe page and set your preferred prefecture, job categories, specific areas, payment type, and shift preferences. You can choose weekly, daily, or immediate alerts when matching jobs are posted.',
            ],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
@endpush
