@extends('layouts.frontend')

@section('content')
    <div class="section wb no-top-padding">
        <div class="container">

            <div class="row">
                <div class="col-md-12">
                    <h3 class="page-header text-center">About Nihonarubaito.com</h3>
                </div>
            </div>

			<div class="row">
				<div class="col-md-10 col-md-offset-1">
					<h3>Our Mission</h3>

					<p>
						Nihonarubaito.com is a job search platform dedicated to helping foreigners find part-time jobs (arubaito) in Japan. We provide job information in multiple languages including English, Vietnamese, Chinese, Japanese, and Korean.
					</p>

					<h3>What We Offer</h3>

					<p>Our platform makes it easy to:</p>

					<ul>
						<li>Browse part-time job listings across all 47 prefectures in Japan</li>
						<li>Search for jobs by location, category, and keywords</li>
						<li>View job details in your preferred language</li>
						<li>Apply for jobs directly through our platform</li>
						<li>Subscribe to receive job alerts matching your preferences</li>
					</ul>

					<h3>For Job Seekers</h3>

					<p>
						Whether you are a student, working holiday visa holder, or a resident looking for part-time work, Nihonarubaito.com helps you find opportunities near you. Create a free account to save your job preferences and receive personalized recommendations.
					</p>

					<h3>For Employers</h3>

					<p>
						If you are looking to hire international workers for part-time positions, Nihonarubaito.com connects you with a diverse pool of multilingual candidates across Japan.
					</p>

					<h3>Contact Us</h3>

					<p>
						Have questions? Visit our <a href="{{ url('contact') }}">contact page</a> or email us at support@nihonarubaito.com.
					</p>
				</div>
			</div>
        </div>
    </div>
@endsection
