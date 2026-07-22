<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Country;
use App\Models\JobCategoryPreference;
use App\Models\JobLocationPreference;
use App\Models\Prefecture;
use App\Models\SubscriberPreference;
use App\Models\User;
use App\Models\UserInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SubscribeController extends Controller
{
    public function index(Request $request)
    {
        $langId = (int) session('user_lang', 1);
        $langName = session('lang_name', 'english');

        if ($request->isMethod('post')) {
            return $this->handleSubscription($request, $langId, $langName);
        }

        // Genders dropdown
        $genders = [0 => 'Please select', 'Male' => 'Male', 'Female' => 'Female'];

        // Japanese levels dropdown
        $levels = [0 => 'Please select', 'N1' => 'N1', 'N2' => 'N2', 'N3' => 'N3', 'N4' => 'N4', 'N5' => 'N5'];

        // Job categories dropdown (id => name)
        $catList = [];
        $cats = Category::all();
        foreach ($cats as $c) {
            $catList[$c->id] = $c->$langName;
        }

        // Prefectures dropdown
        $prefectures = [0 => 'Please select'];
        foreach (Prefecture::all() as $p) {
            $prefectures[$p->id] = $p->$langName;
        }

        // Countries dropdown
        $countryList = [0 => 'Please select'];
        foreach (Country::all() as $c) {
            $countryList[$c->id] = $c->$langName;
        }

        return view('subscribe.index', [
            'genders'        => $genders,
            'levels'         => $levels,
            'job_categories' => $catList,
            'prefectures'    => $prefectures,
            'country_list'   => $countryList,
            'page_title'     => 'Subscribe | Nihon Arubaito',
            'page_description' => 'Subscribe to Nihon Arubaito to receive the latest part-time job notifications in Japan.',
            'canonical'      => 'https://nihonarubaito.com/subscribe',
            'og_url'         => 'https://nihonarubaito.com/subscribe',
            'active_nav'     => 'subscribe',
        ]);
    }

    private function handleSubscription(Request $request, int $langId, string $langName): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'age'            => 'required|integer|min:1|max:120',
            'gender'         => 'required|not_in:0',
            'phone'          => 'required|string',
            'country_id'     => 'required|integer|not_in:0',
            'japanese_level' => 'required|not_in:0',
            'job_category'   => 'required|array|min:1',
            'prefecture_id'  => 'required|integer|not_in:0',
            'areas'          => 'required|array|min:1',
        ]);

        // Generate random password
        $rawPassword = Str::random(6);
        $hashedPassword = Hash::make($rawPassword);

        // Create user
        $user = User::create([
            'first_name' => $request->input('first_name'),
            'last_name'  => $request->input('last_name'),
            'email'      => $request->input('email'),
            'password'   => $hashedPassword,
            'role_id'    => User::ROLE_SUBSCRIBER,
            'lang_id'    => $langId,
        ]);

        // Create user info
        UserInfo::create([
            'age'                => $request->input('age'),
            'gender'             => $request->input('gender'),
            'phone'              => $request->input('phone'),
            'country_id'         => $request->input('country_id'),
            'user_selected_lang' => $langId,
            'japanese_level'     => $request->input('japanese_level'),
            'user_id'            => $user->id,
        ]);

        // Create job category preferences
        $categories = $request->input('job_category', []);
        foreach ($categories as $catId) {
            JobCategoryPreference::create([
                'user_id'         => $user->id,
                'job_category_id' => $catId,
            ]);
        }

        // Create job location preferences
        $areas = $request->input('areas', []);
        foreach ($areas as $area) {
            // Format: p_{prefecture_id}-a_{area_id}
            $parts = explode('-', $area);
            if (count($parts) === 2) {
                $prefId = (int) str_replace('p_', '', $parts[0]);
                $areaId = (int) str_replace('a_', '', $parts[1]);
                JobLocationPreference::create([
                    'user_id'       => $user->id,
                    'prefecture_id' => $prefId,
                    'area_id'       => $areaId,
                ]);
            }
        }

        // Save enhanced subscriber preferences (all optional fields)
        SubscriberPreference::create([
            'user_id'                => $user->id,
            'area_ids'               => $request->input('enhanced_area_ids', []),
            'commute_neighboring'    => $request->boolean('commute_neighboring'),
            'wants_monthly_transfer' => $request->boolean('wants_monthly_transfer', true),
            'wants_daily_payment'    => $request->boolean('wants_daily_payment'),
            'wants_hand_cash'        => $request->boolean('wants_hand_cash'),
            'shift_morning'          => $request->boolean('shift_morning'),
            'shift_afternoon'        => $request->boolean('shift_afternoon'),
            'shift_evening'          => $request->boolean('shift_evening'),
            'shift_night'            => $request->boolean('shift_night'),
            'shift_any'              => $request->boolean('shift_any', true),
            'visa_type'              => $request->input('visa_type'),
            'japanese_level'         => $request->input('enhanced_japanese_level'),
            'max_hours_per_week'     => $request->input('max_hours_per_week'),
            'min_wage'               => $request->input('min_wage'),
            'alert_frequency'        => $request->input('alert_frequency', 'weekly'),
            'alert_hand_cash'        => $request->boolean('alert_hand_cash'),
            'alert_high_wage'        => $request->boolean('alert_high_wage'),
        ]);

        // Send notification email
        try {
            Mail::raw(
                "Welcome to Nihon Arubaito!\n\nYour account has been created.\nEmail: {$user->email}\nPassword: {$rawPassword}\n\nPlease login at: " . url('/account'),
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Welcome to Nihon Arubaito - Your Account Details');
                }
            );
        } catch (\Exception $e) {
            // Email sending failure shouldn't block registration
        }

        return redirect('/subscribe')->with('success', 'You have successfully subscribed! Please check your email for login credentials.');
    }
}
