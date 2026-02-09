<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PageController extends Controller
{
    public function contact()
    {
        return view('pages.contact', [
            'page_title'       => 'Jobs in Japan | nihonarubaito Contact Us Page',
            'page_description' => 'Nihoarubaito contact Us page where you can enquire about latest jobs, add or update any jobs.',
            'active_nav'       => 'contact',
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
                        ->from($request->input('sender_email'), $request->input('sender_name'));
            });
        } catch (\Exception $e) {
            // Log but don't crash - same as CI3 behavior
            \Log::error('Contact form email failed: ' . $e->getMessage());
        }

        return redirect('contact')->with('success', 'Your message has been sent.');
    }

    public function privacy()
    {
        // CI3 doesn't set page_title/page_description for privacy page
        // Falls back to $site_name / empty string via layout defaults
        return view('pages.privacy');
    }

    public function about()
    {
        return view('pages.about', [
            'page_title'       => 'About Us | Nihonarubaito.com',
            'page_description' => 'Learn about Nihonarubaito.com - a multilingual job search platform for part-time jobs in Japan.',
        ]);
    }

    public function faq()
    {
        return view('pages.faq', [
            'page_title'       => 'FAQ | Nihonarubaito.com',
            'page_description' => 'Frequently asked questions about Nihonarubaito.com - find answers about job search, applications, and accounts.',
        ]);
    }

    public function terms()
    {
        return view('pages.terms', [
            'page_title'       => 'Terms of Service | Nihonarubaito.com',
            'page_description' => 'Nihonarubaito.com Terms of Service - Read our terms and conditions for using the platform.',
        ]);
    }
}
