<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PageController extends Controller
{
    public function contact()
    {
        return view('pages.contact', [
            'page_title'       => 'Jobs in Japan | nihonarubaito Contact Us Page',
            'page_description' => 'Nihonarubaito contact us page where you can enquire about latest jobs, add or update any jobs.',
            'canonical'        => 'https://nihonarubaito.com/contact',
            'og_url'           => 'https://nihonarubaito.com/contact',
            'active_nav'       => 'contact',
            'breadcrumbItems'  => [['name' => 'Home', 'url' => url('/')], ['name' => 'Contact']],
        ]);
    }

    public function contactSubmit(Request $request)
    {
        $request->validate([
            'sender_name'  => 'required|string',
            'sender_email' => 'required|email',
            'subject'      => 'required|string|min:5',
            'email_message' => 'required|string|max:1000',
        ]);

        // Send email (same logic as CI3 Contact controller)
        $msg  = 'Sender Name: '  . $request->input('sender_name') . '<br><br>' . PHP_EOL . PHP_EOL;
        $msg .= 'Sender Email: ' . $request->input('sender_email') . '<br><br>' . PHP_EOL . PHP_EOL;
        $msg .= 'Subject: '      . $request->input('subject') . '<br><br>' . PHP_EOL . PHP_EOL;
        $msg .= 'Message: '      . $request->input('email_message') . '<br><br>' . PHP_EOL;

        try {
            Mail::html($msg, function ($message) use ($request) {
                $message->to('support@nihonarubaito.com')
                        ->subject('Contact Form: ' . $request->input('subject'))
                        ->replyTo($request->input('sender_email'), $request->input('sender_name'));
            });
        } catch (\Exception $e) {
            // Log but don't crash - same as CI3 behavior
            \Log::error('Contact form email failed: ' . $e->getMessage());
        }

        return redirect('contact')->with('success', 'Your message has been sent.');
    }

    public function privacy()
    {
        return view('pages.privacy', [
            'page_title'       => 'Privacy Policy | Nihonarubaito.com',
            'page_description' => 'Nihonarubaito.com Privacy Policy - Learn how we collect, use and protect your personal information.',
            'canonical'        => 'https://nihonarubaito.com/privacy-policy',
            'og_url'           => 'https://nihonarubaito.com/privacy-policy',
            'breadcrumbItems'  => [['name' => 'Home', 'url' => url('/')], ['name' => 'Privacy Policy']],
        ]);
    }

    public function about()
    {
        return view('pages.about', [
            'page_title'       => 'About Us | Nihonarubaito.com',
            'page_description' => 'Learn about Nihonarubaito.com - a multilingual job search platform for part-time jobs in Japan.',
            'canonical'        => 'https://nihonarubaito.com/about',
            'og_url'           => 'https://nihonarubaito.com/about',
            'breadcrumbItems'  => [['name' => 'Home', 'url' => url('/')], ['name' => 'About']],
        ]);
    }

    public function faq()
    {
        $faqData = cache()->remember('faq_platform_stats', 3600, function () {
            $activeJobs = DB::table('jobs')->where('job_status_id', 3)->count();
            $subscribers = DB::table('job_location_preferences')->distinct('user_id')->count('user_id');
            return [
                'active_jobs'       => number_format($activeJobs),
                'total_subscribers' => number_format($subscribers),
            ];
        });

        return view('pages.faq', [
            'page_title'       => 'FAQ - Part-Time Jobs in Japan for Foreigners | Nihon Arubaito',
            'page_description' => 'Answers about part-time work in Japan for foreigners. Hand cash jobs, daily payment, student visa work hours, minimum wage by prefecture, and how to find arubaito listed in English.',
            'canonical'        => 'https://nihonarubaito.com/faq',
            'og_url'           => 'https://nihonarubaito.com/faq',
            'breadcrumbItems'  => [['name' => 'Home', 'url' => url('/')], ['name' => 'FAQ']],
            'faqData'          => $faqData,
        ]);
    }

    public function terms()
    {
        return view('pages.terms', [
            'page_title'       => 'Terms of Service | Nihonarubaito.com',
            'page_description' => 'Nihonarubaito.com Terms of Service - Read our terms and conditions for using the platform.',
            'canonical'        => 'https://nihonarubaito.com/terms-of-service',
            'og_url'           => 'https://nihonarubaito.com/terms-of-service',
            'breadcrumbItems'  => [['name' => 'Home', 'url' => url('/')], ['name' => 'Terms of Service']],
        ]);
    }
}
