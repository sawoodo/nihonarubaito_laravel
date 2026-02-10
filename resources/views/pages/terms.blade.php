@extends('layouts.frontend')

@section('content')
    <div class="section wb no-top-padding">
        <div class="container">

            <div class="row">
                <div class="col-md-12">
                    <h1 class="page-header text-center h3">Terms of Service</h1>
                </div>
            </div>

            <div class="row">
                <div class="col-md-10 col-md-offset-1">

                    <p><em>Last updated: February 2026</em></p>

                    <h3>Acceptance of Terms</h3>
                    <p>
                        By accessing and using Nihonarubaito.com, you agree to be bound by these Terms of Service. If you do not agree with any part of these terms, please do not use our website.
                    </p>

                    <h3>About Our Service</h3>
                    <p>
                        Nihonarubaito.com is a free information platform that aggregates part-time job (arubaito) listings in Japan for English-speaking foreigners. We are <strong>not an employment agency</strong>. We provide translated job information to help job seekers discover opportunities, but we do not directly employ, recruit, or place candidates.
                    </p>

                    <h3>Job Listing Accuracy</h3>
                    <p>
                        Job listings on Nihonarubaito.com are sourced and translated from third-party Japanese job sites. While we strive for accuracy:
                    </p>
                    <ul>
                        <li>We <strong>do not guarantee</strong> the accuracy, completeness, or current availability of any job listing.</li>
                        <li>Job details such as wages, working hours, and requirements may change without notice.</li>
                        <li>Translations are provided for convenience and may not perfectly reflect the original listing.</li>
                        <li>Users should <strong>verify all job details directly with the employer</strong> before accepting any position.</li>
                    </ul>

                    <h3>Free Service</h3>
                    <p>
                        Nihonarubaito.com is completely free for job seekers. We do not charge any fees for browsing, searching, or accessing job listings. Our service is supported by advertising.
                    </p>

                    <h3>User Responsibilities</h3>
                    <p>By using our service, you agree to:</p>
                    <ul>
                        <li>Use the service only for lawful purposes.</li>
                        <li>Provide accurate information when using the contact form or subscribing to alerts.</li>
                        <li>Not attempt to scrape, copy, or reproduce our content without permission.</li>
                        <li>Not use the service to send unsolicited communications or spam.</li>
                    </ul>

                    <h3>Limitation of Liability</h3>
                    <p>
                        Nihonarubaito.com is provided "as is" without warranties of any kind. We shall not be liable for any damages arising from:
                    </p>
                    <ul>
                        <li>Inaccurate, outdated, or incomplete job listing information</li>
                        <li>Actions taken by employers or job seekers based on information found on our site</li>
                        <li>Loss of employment opportunities or any decisions based on our content</li>
                        <li>Temporary unavailability of the service</li>
                    </ul>

                    <h3>Intellectual Property</h3>
                    <p>
                        The website design, layout, and original content of Nihonarubaito.com are protected by intellectual property laws. You may not reproduce, distribute, or create derivative works without our written permission.
                    </p>

                    <h3>Changes to Terms</h3>
                    <p>
                        We reserve the right to modify these Terms of Service at any time. Changes will be posted on this page. Your continued use of the service after changes constitutes acceptance of the modified terms.
                    </p>

                    <h3>Governing Law</h3>
                    <p>
                        These Terms of Service are governed by the laws of Japan. Any disputes shall be resolved in the courts of Japan.
                    </p>

                    <h3>Contact</h3>
                    <p>
                        If you have any questions about these Terms of Service, please <a href="{{ url('contact') }}">contact us</a> or email us at support@nihonarubaito.com.
                    </p>

                </div>
            </div>
        </div>
    </div>
@endsection
