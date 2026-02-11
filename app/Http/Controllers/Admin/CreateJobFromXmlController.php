<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Job;
use App\Models\Language;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateJobFromXmlController extends Controller
{
    public function create(Request $request)
    {
        $user = session('user');

        if ($request->isMethod('post')) {
            $rules = [
                'delete_at'  => 'required|integer|min:1',
                'xml_data'   => 'required|string',
                'apply_link' => 'required|string',
            ];

            if ($user->role_id === User::ROLE_ADMIN) {
                $rules['lang_id'] = 'required|integer|min:1';
            }

            $validated = $request->validate($rules);

            // Sanitize bare & characters that aren't already XML entities
            $xmlString = preg_replace('/&(?!amp;|lt;|gt;|quot;|apos;|#\d+;|#x[\da-fA-F]+;)/', '&amp;', $validated['xml_data']);

            try {
                $xml = simplexml_load_string($xmlString);
            } catch (\Exception $e) {
                return back()->withInput()->withErrors(['xml_data' => 'Invalid XML data: ' . $e->getMessage()]);
            }

            if ($xml === false) {
                return back()->withInput()->withErrors(['xml_data' => 'Invalid XML data.']);
            }

            $langId = ($user->role_id === User::ROLE_ADMIN) ? $validated['lang_id'] : ($user->lang_id ?? 1);
            $deleteInDays = max(1, (int) $validated['delete_at']);
            $applyLink = $validated['apply_link'];
            $imgLink = (string) $request->input('img_link', '');
            $featured = $request->has('featured') ? 1 : 0;
            $sendEmail = $request->has('send_email') ? 1 : 0;
            $userId = ($user->role_id === User::ROLE_ADMIN && (int) $request->input('user_id'))
                ? (int) $request->input('user_id') : $user->id;
            $imgId = (int) $request->input('images_img_id', 0);

            $jobData = $this->createJobArray(
                $xml, $langId, $featured, $sendEmail, $applyLink, $imgLink, $deleteInDays, $userId, $imgId
            );

            $job = Job::create($jobData);
            $job->update(['job_no' => (string) $job->id]);

            return redirect("/admin/jobs/{$job->job_no}/edit")
                ->with('success', 'Job has been successfully created from XML.');
        }

        // GET - show form
        $advertiserList = User::where('role_id', User::ROLE_ADVERTISER)
            ->get()
            ->mapWithKeys(fn($u) => [$u->id => "{$u->first_name} {$u->last_name}"])
            ->prepend('Please select', 0)
            ->toArray();

        $data = [
            'activeSideMenu'  => 'jobs',
            'advertiser_list'  => $advertiserList,
            'images_img_id'    => 0,
            'images_img_name'  => '',
            'images_img_ext'   => '',
            'featured'         => false,
            'send_email'       => false,
        ];

        if ($user->role_id === User::ROLE_ADMIN) {
            $data['language_list'] = Language::pluck('english', 'id')->prepend('Please select', 0)->toArray();
            $data['role_id'] = $user->role_id;
        }

        return view('admin.jobs.create-from-xml', $data);
    }

    public function uploadFile(Request $request)
    {
        if (!$request->ajax()) {
            return response()->json(['err' => 'Invalid request.']);
        }

        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            return response()->json(['err' => 'No file uploaded or upload error.']);
        }

        $file = $request->file('file');

        // Validate file type
        $allowedMimes = ['application/xml', 'text/xml'];
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'xml' && !in_array($file->getMimeType(), $allowedMimes)) {
            return response()->json(['err' => 'Only XML files are allowed.']);
        }

        // Validate file size (2MB)
        if ($file->getSize() > 2 * 1024 * 1024) {
            return response()->json(['err' => 'File size must be less than 2MB.']);
        }

        try {
            $content = file_get_contents($file->getRealPath());
            // Sanitize bare & characters that aren't already XML entities
            $content = preg_replace('/&(?!amp;|lt;|gt;|quot;|apos;|#\d+;|#x[\da-fA-F]+;)/', '&amp;', $content);
            $xml = simplexml_load_string($content);

            if ($xml === false) {
                return response()->json(['err' => 'Invalid XML file.']);
            }

            $user = session('user');
            $jobs = [];

            foreach ($xml->job as $jobXml) {
                $jobs[] = $this->createJobArray(
                    $jobXml,
                    1,           // lang_id = English
                    0,           // featured = false
                    0,           // send_email = false
                    '',          // apply_link = empty
                    '',          // img_link = empty
                    10,          // delete_at = 10 days
                    $user->id,   // user_id
                    0            // img_id
                );
            }

            if (empty($jobs)) {
                return response()->json(['err' => 'No job elements found in XML.']);
            }

            // Batch insert
            foreach ($jobs as $jobData) {
                $job = Job::create($jobData);
                $job->update(['job_no' => (string) $job->id]);
            }

            return response()->json([
                'success' => true,
                'message' => 'XML processed successfully. ' . count($jobs) . ' jobs have been created.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['err' => 'Error processing XML: ' . $e->getMessage()]);
        }
    }

    private function createJobArray(
        \SimpleXMLElement $xml,
        int $langId,
        int $featured,
        int $sendEmail,
        string $applyLink,
        string $imgLink,
        int $deleteInDays,
        int $userId,
        int $imgId
    ): array {
        // Description + responsibilities
        $description = (string) ($xml->description ?? '');
        if (isset($xml->description->responsibilities)) {
            $items = [];
            foreach ($xml->description->responsibilities->item as $item) {
                $items[] = (string) $item;
            }
            if ($items) {
                $description .= '<br /><br />' . implode('<br />', $items);
            }
        }

        // Station lines
        $station = '';
        if (isset($xml->station->line)) {
            $lines = [];
            foreach ($xml->station->line as $line) {
                $lines[] = (string) $line;
            }
            $station = implode('<br />', $lines);
        } else {
            $station = (string) ($xml->station ?? '');
        }

        // Wage
        $wage = '';
        $wageDetail = '';
        if (isset($xml->wage->base)) {
            $wage = (string) $xml->wage->base;
            $wageItems = [];
            foreach ($xml->wage->children() as $child) {
                $wageItems[] = (string) $child;
            }
            $wageDetail = implode(' <br />', $wageItems);
        } else {
            $wage = (string) ($xml->wage ?? '');
        }

        // Benefits
        $benefits = '';
        if (isset($xml->benefits->item)) {
            $items = [];
            foreach ($xml->benefits->item as $item) {
                $items[] = (string) $item;
            }
            $benefits = implode('<br />', $items);
        }

        // Requirements
        $requirement = '';
        if (isset($xml->requirement->item)) {
            $items = [];
            foreach ($xml->requirement->item as $item) {
                $items[] = (string) $item;
            }
            $requirement = implode('<br />', $items);
        } else {
            $requirement = (string) ($xml->requirement ?? '');
        }

        return [
            'job_no'               => '',
            'title'                => (string) ($xml->title ?? ''),
            'company_name'         => (string) ($xml->company ?? ''),
            'description'          => $description,
            'job_category_id'      => (int) ($xml->job_category_id ?? 0),
            'prefecture_id'        => (int) ($xml->prefecture_id ?? 0),
            'area_id'              => (int) ($xml->area_id ?? 0),
            'station'              => $station,
            'address'              => (string) ($xml->address ?? ''),
            'japanese_level'       => (int) ($xml->japanese_level ?? 0),
            'working_hours'        => (string) ($xml->working_hours ?? ''),
            'working_days'         => (string) ($xml->working_days ?? ''),
            'wage'                 => $wage,
            'wage_type_id'         => (int) ($xml->wage_type_id ?? 0),
            'wage_detail'          => $wageDetail,
            'trans_exp_id'         => (int) ($xml->trans_exp_id ?? 0),
            'transportation_detail' => (string) ($xml->transportation_access ?? ''),
            'benefits'             => $benefits,
            'requirement'          => $requirement,
            'apply_link'           => $applyLink ?: (string) ($xml->apply_link ?? ''),
            'img_link'             => $imgLink,
            'img_path'             => '',
            'img_name'             => '',
            'img_ext'              => '',
            'img_id'               => $imgId,
            'featured'             => $featured,
            'send_email'           => $sendEmail,
            'delete_at'            => date('Y-m-d', strtotime("+{$deleteInDays} days")),
            'lang_id'              => $langId,
            'job_status_id'        => Job::STATUS_PENDING,
            'user_id'              => $userId,
        ];
    }
}
