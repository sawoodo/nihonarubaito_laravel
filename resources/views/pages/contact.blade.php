@extends('layouts.frontend')

@section('content')
    <div class="section no-top-padding">
        <div class="container">

            <div class="row">

                <div class="content col-md-8 col-md-offset-2">
                    <div class="post-padding">

                        <div class="content-title">
                            <h4>{{ $content->page_heading_label }}</h4>
                            <hr>
                        </div>

                        @if (session('success'))
                            <div class="alert alert-success text-center">
                                <h4>{{ session('success') }}</h4>
                            </div>
                        @endif

                        <hr class="invis">

                        <form action="{{ url('contact') }}" id="contact-form" class="submit-form" method="post" accept-charset="utf-8">
                            @csrf

                            <div class="row">

                                <div class="col-md-6 {{ $errors->has('sender_name') ? 'has-error' : '' }}">
                                    <label for="sender_name">{{ $content->name_label }}</label><input type="text" name="sender_name" value="{{ old('sender_name', '') }}" id="sender_name" class="form-control" placeholder="{{ $content->placeholder_name_field_label }}"  />
                                    @if ($errors->has('sender_name'))
                                        <span class="help-block">{{ $errors->first('sender_name') }}</span>
                                    @endif
                                </div>

                                <div class="col-md-6 {{ $errors->has('sender_email') ? 'has-error' : '' }}">
                                    <label for="sender_email">{{ $content->email_label }}</label><input type="text" name="sender_email" value="{{ old('sender_email', '') }}" id="sender_email" class="form-control" placeholder="{{ $content->placeholder_email_field_label }}"  />
                                    @if ($errors->has('sender_email'))
                                        <span class="help-block">{{ $errors->first('sender_email') }}</span>
                                    @endif
                                </div>

                            </div>

                            <hr class="invis">

                            <div class="row">
                                <div class="col-md-12 {{ $errors->has('subject') ? 'has-error' : '' }}">
                                    <label for="subject">{{ $content->subject_label }}</label><input type="text" name="subject" value="{{ old('subject', '') }}" id="subject" class="form-control" placeholder="{{ $content->placeholder_subject_field_label }}"  />
                                    @if ($errors->has('subject'))
                                        <span class="help-block">{{ $errors->first('subject') }}</span>
                                    @endif
                                </div>
                            </div>

                            <hr class="invis">

                            <div class="row">
                                <div class="col-md-12 {{ $errors->has('email_message') ? 'has-error' : '' }}">
                                    <label for="email_message">{{ $content->message_label }}</label><textarea name="email_message" cols="30" rows="5" id="email_message" class="form-control" style="resize: none;" placeholder="{{ $content->placeholder_message_field_label }}">{{ old('email_message', '') }}</textarea>
                                    @if ($errors->has('email_message'))
                                        <span class="help-block">{{ $errors->first('email_message') }}</span>
                                    @endif
                                </div>
                            </div>

                            <hr class="invis">

                            <button class="btn btn-primary">
                                {{ $content->btn_submit_label }}
                            </button>

                        </form>
                    </div>
                </div>




                <!-- <div class="sidebar col-md-4">
                    <div class="widget clearfix">
                        <div class="postpager liststylepost">
                            <ul class="post-blog">
                                <li>
                                    <div class="post">
                                        <a href="job-single.html">
                                            <h4>Post Your Resume</h4>
                                        </a>
                                        <div class="blog-meta clearfix">
                                            <small>Recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.</small>
                                        </div>
                                    </div>
                                </li>
                                <li>
                                    <div class="post">
                                        <a href="job-single.html">
                                            <h4>Post Job Now</h4>
                                        </a>
                                        <div class="blog-meta clearfix">
                                            <small>Recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.</small>
                                        </div>
                                    </div>
                                </li>
                                <li>
                                    <div class="post">
                                        <a href="job-single.html">
                                            <h4>Create An Account</h4>
                                        </a>
                                        <div class="blog-meta clearfix">
                                            <small>Recently with desktop publishing software like Aldus PageMaker including versions of Lorem Ipsum.</small>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="widget clearfix">
                        <div class="widget-title">
                            <h4>Contact Details</h4>
                        </div>
                        <ul class="contact-details">
                            <li><a href="#"><i class="fa fa-phone"></i> +90 264 987 66 55</a></li>
                            <li><a href="#"><i class="fa fa-fax"></i> +90 264 987 66 54</a></li>
                            <li><a href="#"><i class="fa fa-envelope-o"></i> support@yoursite.com</a></li>
                            <li><a href="#"><i class="fa fa-link"></i> www.yoursite.com</a></li>
                        </ul>
                    </div>

                </div> -->
            </div>
        </div>
    </div>
@endsection
