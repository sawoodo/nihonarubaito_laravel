{{-- Homepage E-E-A-T Content Section --}}
{{-- Shows only for English users on page 1 --}}

@if(session('language', 1) == 1 && !request()->route('page'))
<div class="tw-mt-12 tw-mb-8" style="max-width: 900px;">

    <h2 style="font-size: 1.4em; font-weight: 700; color: #333; margin-bottom: 15px;">
        Browse Part-Time Jobs by Prefecture
    </h2>
    <div style="margin-bottom: 30px; line-height: 2.2;">
        <div style="margin-bottom: 8px;">
            <strong style="color: #555; font-size: 0.9em;">Kanto:</strong>
            <a href="/part-time-jobs-in-tokyo" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Tokyo</a> ·
            <a href="/part-time-jobs-in-kanagawa" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Kanagawa</a> ·
            <a href="/part-time-jobs-in-saitama" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Saitama</a> ·
            <a href="/part-time-jobs-in-chiba" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Chiba</a> ·
            <a href="/part-time-jobs-in-ibaraki" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Ibaraki</a> ·
            <a href="/part-time-jobs-in-tochigi" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Tochigi</a> ·
            <a href="/part-time-jobs-in-gunma" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Gunma</a>
        </div>
        <div style="margin-bottom: 8px;">
            <strong style="color: #555; font-size: 0.9em;">Kansai:</strong>
            <a href="/part-time-jobs-in-osaka" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Osaka</a> ·
            <a href="/part-time-jobs-in-kyoto" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Kyoto</a> ·
            <a href="/part-time-jobs-in-hyogo" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Hyogo</a> ·
            <a href="/part-time-jobs-in-nara" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Nara</a> ·
            <a href="/part-time-jobs-in-shiga" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Shiga</a> ·
            <a href="/part-time-jobs-in-wakayama" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Wakayama</a>
        </div>
        <div style="margin-bottom: 8px;">
            <strong style="color: #555; font-size: 0.9em;">Tokai:</strong>
            <a href="/part-time-jobs-in-aichi" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Aichi</a> ·
            <a href="/part-time-jobs-in-shizuoka" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Shizuoka</a> ·
            <a href="/part-time-jobs-in-gifu" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Gifu</a> ·
            <a href="/part-time-jobs-in-mie" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Mie</a>
        </div>
        <div style="margin-bottom: 8px;">
            <strong style="color: #555; font-size: 0.9em;">Hokkaido & Tohoku:</strong>
            <a href="/part-time-jobs-in-hokkaido" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Hokkaido</a> ·
            <a href="/part-time-jobs-in-miyagi" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Miyagi</a> ·
            <a href="/part-time-jobs-in-fukushima" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Fukushima</a> ·
            <a href="/part-time-jobs-in-aomori" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Aomori</a> ·
            <a href="/part-time-jobs-in-iwate" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Iwate</a> ·
            <a href="/part-time-jobs-in-yamagata" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Yamagata</a> ·
            <a href="/part-time-jobs-in-akita" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Akita</a>
        </div>
        <div style="margin-bottom: 8px;">
            <strong style="color: #555; font-size: 0.9em;">Kyushu & Okinawa:</strong>
            <a href="/part-time-jobs-in-fukuoka" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Fukuoka</a> ·
            <a href="/part-time-jobs-in-kumamoto" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Kumamoto</a> ·
            <a href="/part-time-jobs-in-kagoshima" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Kagoshima</a> ·
            <a href="/part-time-jobs-in-nagasaki" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Nagasaki</a> ·
            <a href="/part-time-jobs-in-oita" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Oita</a> ·
            <a href="/part-time-jobs-in-miyazaki" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Miyazaki</a> ·
            <a href="/part-time-jobs-in-saga" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Saga</a> ·
            <a href="/part-time-jobs-in-okinawa" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Okinawa</a>
        </div>
        <div style="margin-bottom: 8px;">
            <strong style="color: #555; font-size: 0.9em;">Koshinetsu & Hokuriku:</strong>
            <a href="/part-time-jobs-in-niigata" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Niigata</a> ·
            <a href="/part-time-jobs-in-nagano" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Nagano</a> ·
            <a href="/part-time-jobs-in-yamanashi" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Yamanashi</a> ·
            <a href="/part-time-jobs-in-toyama" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Toyama</a> ·
            <a href="/part-time-jobs-in-ishikawa" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Ishikawa</a> ·
            <a href="/part-time-jobs-in-fukui" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Fukui</a>
        </div>
        <div style="margin-bottom: 8px;">
            <strong style="color: #555; font-size: 0.9em;">Chugoku & Shikoku:</strong>
            <a href="/part-time-jobs-in-hiroshima" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Hiroshima</a> ·
            <a href="/part-time-jobs-in-okayama" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Okayama</a> ·
            <a href="/part-time-jobs-in-yamaguchi" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Yamaguchi</a> ·
            <a href="/part-time-jobs-in-shimane" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Shimane</a> ·
            <a href="/part-time-jobs-in-tottori" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Tottori</a> ·
            <a href="/part-time-jobs-in-ehime" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Ehime</a> ·
            <a href="/part-time-jobs-in-kagawa" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Kagawa</a> ·
            <a href="/part-time-jobs-in-tokushima" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Tokushima</a> ·
            <a href="/part-time-jobs-in-kochi" style="color: #1a73e8; text-decoration: none; margin: 0 6px;">Kochi</a>
        </div>
    </div>

    <h2 style="font-size: 1.4em; font-weight: 700; color: #333; margin-bottom: 15px;">
        Find Jobs by Category
    </h2>
    <div style="margin-bottom: 30px; display: flex; flex-wrap: wrap; gap: 8px;">
        <a href="/hand-cash-jobs-in-japan" style="display: inline-block; padding: 8px 16px; background: #f0f7ff; border: 1px solid #d0e3f7; border-radius: 20px; color: #1a73e8; text-decoration: none; font-size: 0.9em;">Hand Cash Jobs</a>
        <a href="/daily-payment-jobs-in-japan" style="display: inline-block; padding: 8px 16px; background: #f0f7ff; border: 1px solid #d0e3f7; border-radius: 20px; color: #1a73e8; text-decoration: none; font-size: 0.9em;">Daily Payment Jobs</a>
        <a href="/bed-making-jobs-in-japan" style="display: inline-block; padding: 8px 16px; background: #f0f7ff; border: 1px solid #d0e3f7; border-radius: 20px; color: #1a73e8; text-decoration: none; font-size: 0.9em;">Bed Making / Hotel Cleaning</a>
        <a href="/convenience-store-jobs-in-japan" style="display: inline-block; padding: 8px 16px; background: #f0f7ff; border: 1px solid #d0e3f7; border-radius: 20px; color: #1a73e8; text-decoration: none; font-size: 0.9em;">Convenience Store</a>
        <a href="/restaurant-service-jobs-in-japan" style="display: inline-block; padding: 8px 16px; background: #f0f7ff; border: 1px solid #d0e3f7; border-radius: 20px; color: #1a73e8; text-decoration: none; font-size: 0.9em;">Restaurant / Service</a>
        <a href="/packing-sorting-jobs-in-japan" style="display: inline-block; padding: 8px 16px; background: #f0f7ff; border: 1px solid #d0e3f7; border-radius: 20px; color: #1a73e8; text-decoration: none; font-size: 0.9em;">Packing / Sorting</a>
        <a href="/factory-jobs-in-japan" style="display: inline-block; padding: 8px 16px; background: #f0f7ff; border: 1px solid #d0e3f7; border-radius: 20px; color: #1a73e8; text-decoration: none; font-size: 0.9em;">Factory Work</a>
        <a href="/warehouse-jobs-in-japan" style="display: inline-block; padding: 8px 16px; background: #f0f7ff; border: 1px solid #d0e3f7; border-radius: 20px; color: #1a73e8; text-decoration: none; font-size: 0.9em;">Warehouse</a>
        <a href="/kitchen-staff-jobs-in-japan" style="display: inline-block; padding: 8px 16px; background: #f0f7ff; border: 1px solid #d0e3f7; border-radius: 20px; color: #1a73e8; text-decoration: none; font-size: 0.9em;">Kitchen Staff</a>
    </div>

    <h2 style="font-size: 1.4em; font-weight: 700; color: #333; margin-bottom: 12px;">
        Part-Time Jobs Near Me for Foreigners in Japan
    </h2>
    <p style="color: #444; line-height: 1.8; margin-bottom: 12px; font-size: 0.95em;">
        Nihon Arubaito lists over {{ number_format($active_jobs ?? 1700) }} part-time jobs across all 47 prefectures of Japan, translated from Japanese into English. Most arubaito (アルバイト) listings on Japanese job boards are written entirely in kanji, which makes them difficult for foreign residents to read even with basic conversational Japanese. Every listing on this platform includes the station name in both English and Japanese, the hourly wage, working hours, and a description you can understand before applying.
    </p>
    <p style="color: #444; line-height: 1.8; margin-bottom: 12px; font-size: 0.95em;">
        The most common part-time jobs for foreigners in Japan are <a href="/convenience-store-jobs-in-japan" style="color: #1a73e8;">convenience store</a> staff (コンビニ/konbini), <a href="/restaurant-service-jobs-in-japan" style="color: #1a73e8;">restaurant and izakaya service</a>, hotel <a href="/bed-making-jobs-in-japan" style="color: #1a73e8;">bed making</a> (ベッドメイキング/beddo meikingu), <a href="/warehouse-jobs-in-japan" style="color: #1a73e8;">warehouse packing and sorting</a>, and <a href="/factory-jobs-in-japan" style="color: #1a73e8;">factory work</a>. Among these, packing and sorting jobs typically have the lowest language requirement — many accept workers with minimal Japanese. <a href="/hand-cash-jobs-in-japan" style="color: #1a73e8;">Hand cash jobs</a> (手渡し/tewatashi) pay you in cash on the same day, while <a href="/daily-payment-jobs-in-japan" style="color: #1a73e8;">daily payment jobs</a> (日払い/nikkyuu) transfer your pay within one to three business days.
    </p>
    <p style="color: #444; line-height: 1.8; margin-bottom: 25px; font-size: 0.95em;">
        <a href="/part-time-jobs-in-tokyo" style="color: #1a73e8;">Tokyo</a>, <a href="/part-time-jobs-in-osaka" style="color: #1a73e8;">Osaka</a>, and <a href="/part-time-jobs-in-aichi" style="color: #1a73e8;">Aichi</a> have the highest number of listings, but prefectures like <a href="/part-time-jobs-in-fukuoka" style="color: #1a73e8;">Fukuoka</a>, <a href="/part-time-jobs-in-chiba" style="color: #1a73e8;">Chiba</a>, and <a href="/part-time-jobs-in-saitama" style="color: #1a73e8;">Saitama</a> also have hundreds of active openings. You can <a href="/subscribe" style="color: #1a73e8;">subscribe for free alerts</a> and get notified when new jobs are posted in your area.
    </p>

    <h2 style="font-size: 1.4em; font-weight: 700; color: #333; margin-bottom: 12px;">
        How Part-Time Pay Works in Japan
    </h2>
    <p style="color: #444; line-height: 1.8; margin-bottom: 12px; font-size: 0.95em;">
        Japan's minimum wage (最低賃金/saitei chingin) is set by prefecture and updated every October. As of October 2025, Tokyo pays the highest at ¥1,226 per hour, followed by Kanagawa at ¥1,225 and Osaka at ¥1,202. The lowest minimum wages are in Akita and Iwate at ¥951 per hour. The national average is ¥1,121. All employers must pay at least the minimum wage regardless of your nationality or visa type.
    </p>
    <p style="color: #444; line-height: 1.8; margin-bottom: 25px; font-size: 0.95em;">
        Night shifts (22:00–05:00) are required by law to pay a 25% premium (深夜手当/shinya teate), so a ¥1,226 base rate in Tokyo becomes ¥1,533 after 10 PM. Many listings on Nihon Arubaito show both the base wage and the night rate. Transportation costs (交通費/koutsuuhi) are usually reimbursed separately — check each listing for details.
    </p>

    <h2 style="font-size: 1.4em; font-weight: 700; color: #333; margin-bottom: 15px;">
        Common Questions About Part-Time Work in Japan
    </h2>

    <div style="margin-bottom: 25px;">
        <h3 style="font-size: 1.05em; font-weight: 600; color: #333; margin-bottom: 6px;">How do I find part-time jobs near me in Japan?</h3>
        <p style="color: #444; line-height: 1.7; font-size: 0.93em; margin-bottom: 0;">
            Select your prefecture from the search form above or browse the prefecture links on this page. Nihon Arubaito shows jobs near train stations across Japan with the station name in both English and Japanese (kanji + romaji), so you can match listings to stations on your commute. You can also <a href="/subscribe" style="color: #1a73e8;">subscribe for free email alerts</a> filtered by your prefecture and preferred job categories.
        </p>
    </div>

    <div style="margin-bottom: 25px;">
        <h3 style="font-size: 1.05em; font-weight: 600; color: #333; margin-bottom: 6px;">Can foreigners work part-time in Japan?</h3>
        <p style="color: #444; line-height: 1.7; font-size: 0.93em; margin-bottom: 0;">
            Yes, but you need the right visa status. Student visa holders (留学/ryuugaku) can work up to 28 hours per week during term and 40 hours during school breaks with a Permission to Engage in Activity Other Than That Permitted (資格外活動許可/shikakugai katsudou kyoka). Dependent visa holders (家族滞在/kazoku taizai) can work up to 28 hours per week with the same permission. Working holiday visa holders have no hour restrictions.
        </p>
    </div>

    <div style="margin-bottom: 25px;">
        <h3 style="font-size: 1.05em; font-weight: 600; color: #333; margin-bottom: 6px;">What is a hand cash job (手渡し)?</h3>
        <p style="color: #444; line-height: 1.7; font-size: 0.93em; margin-bottom: 0;">
            A hand cash job (手渡し/tewatashi) pays your wages in physical cash, usually at the end of your shift or the same day. This is different from daily payment (日払い/nikkyuu), where the employer processes your pay daily but transfers it to your bank account within one to three business days. Hand cash jobs are popular with foreign workers who need immediate access to their earnings or who have not yet opened a Japanese bank account. Browse <a href="/hand-cash-jobs-in-japan" style="color: #1a73e8;">hand cash jobs across Japan</a>.
        </p>
    </div>

    <div style="margin-bottom: 25px;">
        <h3 style="font-size: 1.05em; font-weight: 600; color: #333; margin-bottom: 6px;">Do I need to speak Japanese to get a part-time job?</h3>
        <p style="color: #444; line-height: 1.7; font-size: 0.93em; margin-bottom: 0;">
            Basic conversational Japanese helps for most positions, but some job types require very little speaking. <a href="/warehouse-jobs-in-japan" style="color: #1a73e8;">Warehouse packing and sorting</a>, <a href="/factory-jobs-in-japan" style="color: #1a73e8;">factory assembly</a>, and hotel <a href="/bed-making-jobs-in-japan" style="color: #1a73e8;">bed making</a> jobs often involve minimal customer interaction. Convenience store and restaurant jobs typically require enough Japanese to greet customers and take simple orders. Job listings on Nihon Arubaito note when positions specifically welcome foreigners (外国人歓迎/gaikokujin kangei) or require no experience (未経験OK/mikeiken OK).
        </p>
    </div>

    <div style="margin-bottom: 25px;">
        <h3 style="font-size: 1.05em; font-weight: 600; color: #333; margin-bottom: 6px;">How much can I earn working part-time in Japan?</h3>
        <p style="color: #444; line-height: 1.7; font-size: 0.93em; margin-bottom: 0;">
            A student visa holder working 28 hours per week in Tokyo at minimum wage (¥1,226/hr) earns approximately ¥137,000 per month before taxes. In prefectures with lower minimum wages like Fukuoka (¥1,004/hr), the same hours yield about ¥112,000 per month. Night shift workers earn 25% more after 10 PM. Be aware of the ¥1,030,000 annual income threshold — if you earn more than this as a dependent, your tax status and health insurance obligations may change.
        </p>
    </div>

</div>

<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
        {
            "@type": "Question",
            "name": "How do I find part-time jobs near me in Japan?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Select your prefecture from the search form or browse prefecture links on the Nihon Arubaito homepage. Jobs are listed near train stations across Japan with station names in both English and Japanese. You can subscribe for free email alerts filtered by prefecture and job category."
            }
        },
        {
            "@type": "Question",
            "name": "Can foreigners work part-time in Japan?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Yes, with the right visa status. Student visa holders can work up to 28 hours per week during term and 40 hours during school breaks with a Permission to Engage in Activity Other Than That Permitted (資格外活動許可). Dependent visa holders can work up to 28 hours per week with the same permission. Working holiday visa holders have no hour restrictions."
            }
        },
        {
            "@type": "Question",
            "name": "What is a hand cash job (手渡し)?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "A hand cash job (手渡し/tewatashi) pays wages in physical cash, usually at the end of your shift. This differs from daily payment (日払い/nikkyuu), where pay is processed daily but transferred to your bank account within 1-3 business days. Hand cash jobs are popular with foreign workers who need immediate access to earnings."
            }
        },
        {
            "@type": "Question",
            "name": "Do I need to speak Japanese to get a part-time job?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Basic conversational Japanese helps for most positions, but warehouse packing, factory assembly, and hotel bed making jobs often require minimal speaking. Convenience store and restaurant jobs typically need enough Japanese for simple customer interaction. Listings note when positions welcome foreigners (外国人歓迎) or require no experience (未経験OK)."
            }
        },
        {
            "@type": "Question",
            "name": "How much can I earn working part-time in Japan?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "A student working 28 hours per week in Tokyo at minimum wage (¥1,226/hr) earns approximately ¥137,000 per month before taxes. Night shifts pay 25% more after 10 PM. Be aware of the ¥1,030,000 annual income threshold — earning more as a dependent may change your tax status and insurance obligations."
            }
        }
    ]
}
</script>
@endif
