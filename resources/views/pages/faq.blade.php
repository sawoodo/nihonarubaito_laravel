@extends('layouts.frontend')

@section('content')
    <div class="section wb no-top-padding">
        <div class="container">

            <div class="row">
                <div class="col-md-12">
                    <h3 class="page-header text-center">Frequently Asked Questions</h3>
                </div>
            </div>

			<div class="row">
				<div class="col-md-10 col-md-offset-1">

					<h3>General Questions</h3>

					<h4>What is Nihonarubaito.com?</h4>
					<p>
						Nihonarubaito.com is a multilingual job search platform that helps foreigners living in Japan find part-time jobs (arubaito). Our job listings are available in English, Vietnamese, Chinese, Japanese, and Korean.
					</p>

					<h4>Is it free to use Nihonarubaito.com?</h4>
					<p>
						Yes, Nihonarubaito.com is completely free for job seekers. You can browse jobs, create an account, and apply for positions at no cost.
					</p>

					<h4>Do I need to create an account to apply for jobs?</h4>
					<p>
						You can browse job listings without an account, but creating a free account allows you to save preferences, receive job alerts, and track your applications.
					</p>

					<h3>Job Search</h3>

					<h4>How do I search for jobs?</h4>
					<p>
						You can search for jobs by keyword, prefecture, area, or job category using the search form on the homepage. You can also browse jobs by clicking on specific prefectures or areas.
					</p>

					<h4>What types of jobs are listed?</h4>
					<p>
						We list part-time jobs across various categories including restaurant/service, retail, factory/warehouse, office work, and more. All jobs are located in Japan.
					</p>

					<h4>How often are new jobs posted?</h4>
					<p>
						New jobs are added regularly. Subscribe to receive email notifications when new jobs matching your preferences are posted.
					</p>

					<h3>Applying for Jobs</h3>

					<h4>How do I apply for a job?</h4>
					<p>
						Click on a job listing to view the details, then click the "Apply Now" button. You will be guided through the application process.
					</p>

					<h4>What information do I need to provide when applying?</h4>
					<p>
						Typically you will need to provide your name, email, phone number, and basic personal information. Some jobs may require additional details.
					</p>

					<h3>Account & Subscription</h3>

					<h4>How do I subscribe for job alerts?</h4>
					<p>
						Visit the <a href="{{ url('subscribe') }}">Subscribe</a> page and fill in your details including preferred job categories and locations. You will receive email notifications when matching jobs are posted.
					</p>

					<h4>How do I change my password?</h4>
					<p>
						Log in to your account and navigate to the "Change Password" section from your dashboard menu.
					</p>

					<h3>Contact</h3>

					<h4>I have more questions. How can I reach you?</h4>
					<p>
						Please visit our <a href="{{ url('contact') }}">Contact</a> page to send us a message, or email us directly at support@nihonarubaito.com.
					</p>
				</div>
			</div>
        </div>
    </div>
@endsection
