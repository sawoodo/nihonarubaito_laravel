@extends('layouts.frontend')

@include('partials.breadcrumb-schema')

@section('content')
    <div class="section wb no-top-padding">
        <div class="container">

            <div class="row">
                <div class="col-md-12">
                    <h1 class="page-header text-center h3">Privacy Policy</h1>
                </div>
            </div>

            <div class="row">
                <div class="col-md-10 col-md-offset-1">

                    <p><em>Last updated: February 2026</em></p>

                    <h3>Introduction</h3>
                    <p>
                        At Nihonarubaito.com, we respect your privacy. This Privacy Policy explains what information we collect when you visit our website and how we use it. By using Nihonarubaito.com, you consent to the practices described in this policy.
                    </p>

                    <h3>Information We Collect</h3>
                    <p>Nihonarubaito.com is a free job information platform. We collect minimal data:</p>
                    <ul>
                        <li><strong>Analytics data:</strong> We use Google Analytics to understand how visitors use our site. This collects anonymized data such as pages visited, time spent on the site, browser type, and approximate geographic location.</li>
                        <li><strong>Cookies:</strong> We use cookies for Google Analytics tracking and Google Ads (AdSense) to display relevant advertisements. These cookies help measure ad performance and provide personalized ads based on your browsing activity.</li>
                        <li><strong>Contact form submissions:</strong> If you use our <a href="{{ url('contact') }}">contact form</a>, we collect your name, email address, and message content to respond to your inquiry.</li>
                        <li><strong>Subscription data:</strong> If you subscribe to job alerts, we collect your email address and job preferences to send you relevant notifications.</li>
                    </ul>

                    <h3>How We Use Your Information</h3>
                    <ul>
                        <li>To provide and improve our job listing services</li>
                        <li>To analyze website traffic and usage patterns via Google Analytics</li>
                        <li>To display relevant advertisements via Google Ads (AdSense)</li>
                        <li>To respond to inquiries submitted through the contact form</li>
                        <li>To send job alert emails to subscribers</li>
                    </ul>

                    <h3>Third-Party Services</h3>
                    <p>We use the following third-party services that may collect data:</p>
                    <ul>
                        <li><strong>Google Analytics:</strong> Collects anonymized usage data to help us understand site traffic. <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Privacy Policy</a></li>
                        <li><strong>Google Ads (AdSense):</strong> Displays advertisements and may use cookies to personalize ads. <a href="https://policies.google.com/technologies/ads" target="_blank" rel="noopener">How Google uses cookies in advertising</a></li>
                    </ul>

                    <h3>Cookies</h3>
                    <p>
                        Cookies are small text files stored on your device. We use cookies for analytics and advertising purposes. You can control or disable cookies through your browser settings. Disabling cookies may affect the functionality of some features on our site.
                    </p>

                    <h3>Data Sharing</h3>
                    <p>
                        We do not sell, rent, or share your personal information with third parties, except as required by law or as described in this policy (i.e., through Google Analytics and Google Ads).
                    </p>

                    <h3>Data Retention</h3>
                    <p>
                        Contact form messages and subscription data are retained for as long as necessary to provide our services. You can request deletion of your data by contacting us.
                    </p>

                    <h3>Your Rights</h3>
                    <p>You have the right to:</p>
                    <ul>
                        <li>Request access to the personal data we hold about you</li>
                        <li>Request correction or deletion of your personal data</li>
                        <li>Opt out of Google Analytics tracking by installing the <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener">Google Analytics Opt-out Browser Add-on</a></li>
                        <li>Manage your ad personalization settings at <a href="https://adssettings.google.com" target="_blank" rel="noopener">Google Ads Settings</a></li>
                    </ul>

                    <h3>Changes to This Policy</h3>
                    <p>
                        We may update this Privacy Policy from time to time. Changes will be posted on this page with an updated revision date.
                    </p>

                    <h3>Contact Us</h3>
                    <p>
                        If you have any questions about this Privacy Policy, please <a href="{{ url('contact') }}">contact us</a> or email us at support@nihonarubaito.com.
                    </p>

                </div>
            </div>
        </div>
    </div>
@endsection
