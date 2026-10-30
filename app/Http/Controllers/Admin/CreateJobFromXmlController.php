<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Job;
use App\Models\Language;
use App\Models\Prefecture;
use App\Models\User;
use App\Models\Area;
use App\Services\AddressLocationResolver;
use App\Services\JobMatchApplier;
use App\Services\JobMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateJobFromXmlController extends Controller
{
    /**
     * Verdict from the last createJobArray() call: what the address said about
     * prefecture/area, what we changed, and what a human must check.
     * Read it immediately after the call -- the bulk path loops.
     */
    private array $lastLocationVerdict = [];

    /** Lazily built once per request; the areas lookup is ~1,892 rows. */
    private ?AddressLocationResolver $locationResolver = null;

    /**
     * Maps the translation prompt's short slugs to the SEO slugs stored in `tags`.
     * Slugs not listed here are looked up as-is.
     */
    private const TAG_SLUG_MAP = [
        // shift / time
        'early-morning'      => 'early-morning-jobs',
        'morning'            => 'morning-jobs',
        'daytime'            => 'daytime-jobs',
        'evening'            => 'evening-jobs',
        'night-shift'        => 'night-shift-jobs',
        // days / schedule
        'weekend'            => 'weekend-jobs',
        'weekdays-only'      => 'weekday-jobs',
        'flexible-shift'     => 'flexible-shift-jobs',
        'one-day-week'       => 'one-day-week-jobs',
        'four-plus-days'     => 'four-days-week-jobs',
        'short-term'         => 'short-term-jobs',
        'long-term'          => 'long-term-jobs',
        // payment
        'hand-cash'          => 'hand-cash-jobs',
        'daily-payment'      => 'daily-payment-jobs',
        'weekly-payment'     => 'weekly-payment-jobs',
        'advance-payment'    => 'advance-payment-jobs',
        'high-wage'          => 'high-wage-jobs',
        // audience
        'foreigners-welcome' => 'jobs-for-foreigners',
        'students-welcome'   => 'student-jobs',
        'no-experience'      => 'no-experience-jobs',
        'second-job'         => 'second-job',
        'homemakers-welcome' => 'jobs-for-homemakers',
        'freeters-welcome'   => 'freeter-jobs',
        'seniors-welcome'    => 'senior-jobs',
        'career-gap-ok'      => 'career-gap-ok-jobs',
        // conditions
        'near-station'       => 'near-station-jobs',
        'transport-paid'     => 'transport-paid-jobs',
        'training-provided'  => 'training-provided-jobs',
        'insurance'          => 'jobs-with-insurance',
        'uniform-provided'   => 'uniform-provided-jobs',
        'meals-provided'     => 'jobs-with-meals',
        'no-resume'          => 'no-resume-jobs',
        'appearance-free'    => 'appearance-free-jobs',
        // 'summer' has no prompt slug yet; summer-jobs stays manual
    ];

    public function create(Request $request)
    {
        $user = session('user');

        if ($request->isMethod('post')) {
            $rules = [
                'delete_at'  => 'required|integer|min:1',
                'xml_data'   => 'required|string',
                // Not required here: the XML may carry it. Enforced below.
                'apply_link' => 'nullable|string',
            ];

            if ($user->role_id === User::ROLE_ADMIN) {
                $rules['lang_id'] = 'required|integer|min:1';
            }

            $validated = $request->validate($rules);

            $xmlString = $this->cleanXmlInput($validated['xml_data']);

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
            // The form's link wins; the XML's is the fallback. Resolved here,
            // not inside createJobArray(), so the card shows and relinks the
            // link that would actually be stored.
            $applyLink = trim((string) ($validated['apply_link'] ?? '')) ?: $this->applyLinkFromXml($xml);
            if ($applyLink === '') {
                return back()->withInput()->withErrors(['apply_link' => 'The apply link field is required.']);
            }
            $imgLink = (string) $request->input('img_link', '');
            $featured = $request->has('featured') ? 1 : 0;
            $sendEmail = $request->has('send_email') ? 1 : 0;
            $userId = ($user->role_id === User::ROLE_ADMIN && (int) $request->input('user_id'))
                ? (int) $request->input('user_id') : $user->id;
            $imgId = (int) $request->input('images_img_id', 0);

            $jobData = $this->createJobArray(
                $xml, $langId, $featured, $sendEmail, $applyLink, $imgLink, $deleteInDays, $userId, $imgId
            );

            // Have we already got this job? JobMatcher ports the rules proven
            // on the whole published table in scripts/dedupe-plan.py; the two
            // checks this replaces (exact apply_link on statuses 2-3, and
            // company + prefecture + the first 30 characters of the title on
            // 1-3) missed 41 of 157 real repeats over 24-26 Oct 2026.
            $matches = JobMatcher::findMatches($jobData);
            $certain = array_values(array_filter($matches, fn ($m) => $m['verdict'] === 'certain'));
            $review  = array_values(array_filter($matches, fn ($m) => $m['verdict'] === 'review'));

            $createAnyway = (bool) $request->input('skip_duplicate_check');
            $anywayReason = trim((string) $request->input('duplicate_reason', ''));
            $matchedJobNo = (string) $request->input('matched_job_no', '');

            if ($certain) {
                $card = [
                    'certain'        => JobMatchApplier::forCard($certain),
                    'review'         => JobMatchApplier::forCard($review),
                    'new_link'       => $applyLink,
                    'delete_in_days' => $deleteInDays,
                    'return_to'      => 'xml',
                ];

                // No one-click bypass any more: creating a second copy of a
                // job we already have needs a typed reason, and that reason is
                // written to job_status_history against the new job.
                if (! $createAnyway) {
                    return back()->withInput()->with('job_matches', $card);
                }
                if (mb_strlen($anywayReason) < 10) {
                    return back()->withInput()
                        ->withErrors(['duplicate_reason' => 'Say in a few words why this is a different job (at least 10 characters).'])
                        ->with('job_matches', $card);
                }
            }

            $locationVerdict = $this->lastLocationVerdict;

            $job = Job::create($jobData);
            $job->update(['job_no' => (string) $job->id]);
            $this->attachTagsFromXml($xml, $job->id);

            $this->lastLocationVerdict = $locationVerdict;
            $this->logLocationVerdict((string) $job->job_no);

            $actor = $user->email ?? ('user:' . $user->id);

            if ($certain && $createAnyway && $anywayReason !== '') {
                JobMatchApplier::logCreatedDespite(
                    (string) $job->job_no,
                    $matchedJobNo !== '' ? $matchedJobNo : (string) $certain[0]['job']->job_no,
                    $anywayReason,
                    Job::STATUS_PENDING,
                    'xml-import',
                    $actor
                );
            }

            $created = 'Job has been successfully created from XML.';
            if ($review) {
                // Not certain, so it was never blocked — but say so, because
                // one of these may still be the same vacancy.
                $nos = implode(', #', array_map(fn ($m) => (string) $m['job']->job_no, $review));
                $created .= ' Similar job(s) worth checking: #' . $nos . '.';
            }

            $redirect = redirect("/admin/jobs/{$job->job_no}/edit")
                ->with('success', $created)
                ->with('location_verdict', $locationVerdict);

            // Unresolved prefecture: the job is saved but cannot be published until a
            // prefecture and area are set. Tell the user, with the raw XML value.
            if ((int) $jobData['prefecture_id'] <= 0
                || ! Prefecture::whereKey((int) $jobData['prefecture_id'])->exists()) {
                $rawPrefecture = trim((string) ($xml->prefecture_id ?? ''));
                $shown = $rawPrefecture === '' ? '(empty)' : $rawPrefecture;
                $redirect->with('error', "Prefecture '{$shown}' not recognised — job saved as pending; set prefecture and area before publishing.");
            }

            return $redirect;
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
            $content = $this->cleanXmlInput(file_get_contents($file->getRealPath()));
            $xml = simplexml_load_string($content);

            if ($xml === false) {
                return response()->json(['err' => 'Invalid XML file.']);
            }

            $user = session('user');
            $jobs = [];
            $xmlObjects = []; // Preserve XML for tag parsing

            $locationVerdicts = [];   // index-aligned with $jobs

            foreach ($xml->job as $jobXml) {
                $jobs[] = $this->createJobArray(
                    $jobXml,
                    1,           // lang_id = English
                    0,           // featured = false
                    0,           // send_email = false
                    $this->applyLinkFromXml($jobXml),
                    '',          // img_link = empty
                    10,          // delete_at = 10 days
                    $user->id,   // user_id
                    0            // img_id
                );
                // Capture before the next iteration overwrites it.
                $locationVerdicts[] = $this->lastLocationVerdict;
                $xmlObjects[] = $jobXml; // Keep XML for tag attachment
            }

            if (empty($jobs)) {
                return response()->json(['err' => 'No job elements found in XML.']);
            }

            // Batch insert with duplicate detection
            $dupCount = 0;
            $correctedCount = 0;
            $reviewCount = 0;
            $sameAs = [];
            foreach ($jobs as $index => $jobData) {
                // Bulk NEVER updates an existing job: a relink is a judgement
                // about one vacancy and belongs to a person. A certain match
                // is saved as a Draft naming the job it duplicates, so the
                // reviewer can relink by hand and delete the draft.
                $matches = JobMatcher::findMatches($jobData);
                $certain = array_values(array_filter($matches, fn ($m) => $m['verdict'] === 'certain'));

                if ($certain) {
                    $matchNo = (string) $certain[0]['job']->job_no;
                    $jobData['title'] = "[SAME AS {$matchNo}] " . $jobData['title'];
                    $jobData['job_status_id'] = Job::STATUS_DRAFT;
                    $dupCount++;
                    $sameAs[] = "same as {$matchNo}: " . $certain[0]['reason'];
                }
                $job = Job::create($jobData);
                $job->update(['job_no' => (string) $job->id]);
                $this->attachTagsFromXml($xmlObjects[$index], $job->id);

                $this->lastLocationVerdict = $locationVerdicts[$index] ?? [];
                $this->logLocationVerdict((string) $job->job_no);
                $correctedCount += count($locationVerdicts[$index]['corrections'] ?? []);
                $reviewCount    += count($locationVerdicts[$index]['review'] ?? []);
            }

            $msg = count($jobs) . ' jobs created.';
            if ($dupCount > 0) {
                $msg .= " {$dupCount} already exist and were saved as Draft, not published: "
                    . implode('; ', $sameAs) . '.';
            }
            if ($correctedCount > 0) {
                $msg .= " {$correctedCount} location field(s) corrected from the address.";
            }
            if ($reviewCount > 0) {
                $msg .= " {$reviewCount} need a manual location check.";
            }

            return response()->json([
                'success' => true,
                'message' => 'XML processed successfully. ' . $msg,
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
        // Description + responsibilities. See descriptionFromXml(): casting
        // the element to a string threw away every <br/> and the lookup for
        // <responsibilities> pointed at the wrong level, so 4,825 jobs in 60
        // days stored one run-on paragraph and no task list at all.
        $description = $this->descriptionFromXml($xml);

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
                if ($child->getName() === 'base') {
                    continue; // base is stored separately in `wage` column — skip to avoid duplication
                }
                $incentive = trim((string) $child);
                if ($incentive !== '') {
                    $wageItems[] = $incentive;
                }
            }
            $wageDetail = implode('<br/>', $wageItems);
        } else {
            $wage = (string) ($xml->wage ?? '');
        }

        // Benefits and requirements are printed RAW too
        // (resources/views/jobs/detail.blade.php:202 and :210), so they go
        // through the same allow-list as the description. innerMarkup keeps a
        // CDATA item's markup, which the plain string cast would have dropped.
        $benefits = '';
        if (isset($xml->benefits->item)) {
            $items = [];
            foreach ($xml->benefits->item as $item) {
                $items[] = $this->innerMarkup($item);
            }
            $benefits = $this->allowOnlyDescriptionTags(implode('<br />', $items));
        }

        $requirement = '';
        if (isset($xml->requirement->item)) {
            $items = [];
            foreach ($xml->requirement->item as $item) {
                $items[] = $this->innerMarkup($item);
            }
            $requirement = $this->allowOnlyDescriptionTags(implode('<br />', $items));
        } else {
            $requirement = $this->allowOnlyDescriptionTags($this->innerMarkup($xml->requirement ?? null));
        }


        // Validate area_id resolves — a bad id silently vanishes the job via the
        // inner join in Job::scopeWithLocalizedNames (no listing, no 404). Log-only:
        // the job still imports; this surfaces the failure for later triage.
        $xmlAreaId = (int) ($xml->area_id ?? 0);
        if ($xmlAreaId > 0 && !Area::whereKey($xmlAreaId)->exists()) {
            Log::warning('XML import: unresolvable area_id', [
                'area_id'       => $xmlAreaId,
                'prefecture_id' => (int) ($xml->prefecture_id ?? 0),
                'address'       => (string) ($xml->address ?? ''),
                'title'         => mb_substr((string) ($xml->title ?? ''), 0, 80),
            ]);
        }

        // Validate prefecture_id resolves — an unresolved prefecture used to make the
        // job unopenable in admin (inner join in scopeForEdit). The join is now LEFT,
        // so the job opens; this surfaces the bad feed value. create() also warns the
        // user on screen with the raw value.
        $rawPrefecture = trim((string) ($xml->prefecture_id ?? ''));
        $xmlPrefectureId = (int) ($xml->prefecture_id ?? 0);
        if ($xmlPrefectureId <= 0 || ! Prefecture::whereKey($xmlPrefectureId)->exists()) {
            // Log::error, not warning: production runs LOG_LEVEL=error, so a warning
            // would never be written and we would never collect the failing values.
            Log::error('XML import: unresolvable prefecture_id', [
                'raw_prefecture' => $rawPrefecture,
                'prefecture_id'  => $xmlPrefectureId,
                'area_id'        => (int) ($xml->area_id ?? 0),
                'address'        => (string) ($xml->address ?? ''),
                'title'          => mb_substr((string) ($xml->title ?? ''), 0, 80),
            ]);
        }
        $payload = [
            'job_no'               => '',
            // Decoded ONCE: a title inside CDATA is handed over verbatim by
            // the parser, so "Hall &amp; Kitchen" would be stored as those
            // five characters and rendered to a visitor as "Hall &amp;".
            // Four published jobs carry that today. Decoding here is safe for
            // the non-CDATA form too, where the parser has already decoded it
            // and there is nothing left to decode.
            'title'                => $this->decodeEntitiesOnce((string) ($xml->title ?? '')),
            'company_name'         => (string) ($xml->company ?? ''),
            'description'          => $description,
            'job_category_id'      => (int) ($xml->job_category_id ?? 0),
            'prefecture_id'        => (int) ($xml->prefecture_id ?? 0),
            'area_id'              => $xmlAreaId,
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
            'apply_link'           => $applyLink,
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

        // Address beats the feed's prefecture_id/area_id. Done here, before the
        // caller's duplicate check, so findDuplicate() compares the corrected
        // location rather than the wrong one.
        return $this->correctLocationFromAddress($payload, $payload['address']);
    }

    /**
     * The link in the XML itself. The element is <apply_url>: this used to read
     * <apply_link>, which the feed does not have, so every dropzone job was
     * stored with no link and JobMatcher lost its strongest signal on that
     * path. <apply_link> is still accepted for anything hand-written.
     */
    private function applyLinkFromXml(\SimpleXMLElement $xml): string
    {
        return trim((string) ($xml->apply_url ?? '')) ?: trim((string) ($xml->apply_link ?? ''));
    }

    /**
     * Attach tags parsed from the XML <tags> element to a newly created job.
     * NEVER throws — a tag failure must not break the job import.
     */
    private function attachTagsFromXml($xml, int $jobId): void
    {
        try {
            $raw = trim((string) ($xml->tags ?? ''));
            if ($raw === '') {
                return; // no tags element (old XML) — nothing to do, zero overhead
            }

            // slug => id, fetched once and cached (tags change almost never)
            $tagMap = \Illuminate\Support\Facades\Cache::remember(
                'tag_slug_id_map',
                3600,
                fn () => DB::table('tags')->pluck('id', 'slug')->toArray()
            );

            $unknown = [];
            $tagIds  = [];

            foreach (explode(',', $raw) as $slug) {
                $slug = trim($slug);
                if ($slug === '') {
                    continue;
                }
                // map short slug -> DB slug (or use as-is if not in the map)
                $dbSlug = self::TAG_SLUG_MAP[$slug] ?? $slug;

                if (isset($tagMap[$dbSlug])) {
                    $tagIds[$tagMap[$dbSlug]] = true; // dedupe via key
                } else {
                    $unknown[] = $slug;
                }
            }

            if (!empty($unknown)) {
                \Illuminate\Support\Facades\Log::warning('Unknown tag slugs in XML import', [
                    'job_id' => $jobId,
                    'slugs'  => $unknown,
                ]);
            }

            if (empty($tagIds)) {
                return;
            }

            // ONE batch insert. Composite PK (tag_id, job_id) makes duplicates a no-op.
            $rows = [];
            foreach (array_keys($tagIds) as $tagId) {
                $rows[] = ['job_id' => $jobId, 'tag_id' => $tagId];
            }
            DB::table('job_tag')->insertOrIgnore($rows);

        } catch (\Throwable $e) {
            // Tag failure must NEVER block the job import.
            \Illuminate\Support\Facades\Log::error('Tag attach failed (job still imported)', [
                'job_id' => $jobId,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Tidy the pasted XML enough for simplexml, WITHOUT reaching inside a
     * CDATA section.
     *
     * The two transforms below used to run over the whole string, and they
     * disagree with the parser about CDATA. html_entity_decode turned an
     * "&amp;" inside CDATA into "&", the bare-& rule turned it back into
     * "&amp;", and simplexml then handed that over verbatim because it does
     * not decode entities inside CDATA. Either spelling of "&" inside a CDATA
     * title therefore reached the database as the five characters "&amp;".
     *
     * CDATA exists precisely so its contents are not interpreted, so the
     * contents are now left exactly as the translator wrote them.
     */
    private function cleanXmlInput(string $raw): string
    {
        $xml = trim($raw);
        $xml = preg_replace('/^```xml\s*/', '', $xml);            // Remove markdown code fence start
        $xml = preg_replace('/```\s*$/', '', $xml);                // Remove markdown code fence end
        $xml = preg_replace('/^\x{FEFF}/u', '', $xml);            // Remove BOM
        $xml = preg_replace('/^[^<]*/', '', $xml, 1);             // Remove anything before first <

        // Split so that every CDATA section is its own part, and fix up only
        // the parts outside them.
        $parts = preg_split('/(<!\[CDATA\[.*?\]\]>)/s', $xml, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if (str_starts_with($part, '<![CDATA[')) {
                continue;                                          // untouched, on purpose
            }
            $part = html_entity_decode($part);                     // Fix &lt; → <
            // Sanitize bare & characters that aren't already XML entities
            $parts[$i] = preg_replace('/&(?!amp;|lt;|gt;|quot;|apos;|#\d+;|#x[\da-fA-F]+;)/', '&amp;', $part);
        }

        return implode('', $parts);
    }

    /** Tags a description may keep. Everything else is stripped on import. */
    private const DESCRIPTION_TAGS = ['br', 'b', 'strong', 'em', 'ul', 'li'];

    /**
     * The description, with the translator's formatting intact.
     *
     * Two bugs lived here. `(string) $xml->description` returns an element's
     * TEXT only, so every <br/> written as real markup was dropped; and the
     * <responsibilities> lookup tested $xml->description->responsibilities
     * while the old format puts that element NEXT TO <description>, so the
     * task list was never appended. Between them, 4,825 jobs imported in 60
     * days hold one run-on paragraph with no list and no line breaks.
     *
     * Both input shapes now work:
     *   old format  <description>text<br/>text</description> plus a sibling
     *               <responsibilities><item>…</item></responsibilities>
     *   new format  <description><![CDATA[<b>…</b><br/>- …]]></description>
     *
     * The CDATA path is unchanged in effect — its contents were already being
     * stored verbatim, which is why jobs from 2026-10-27 onward do have tags.
     */
    private function descriptionFromXml(\SimpleXMLElement $xml): string
    {
        // Skip a nested <responsibilities>: it is appended below, and
        // reading it here as well put every item in twice, the first time with
        // its tags stripped and its text glued to the sentence before it.
        $description = $this->innerMarkup($xml->description ?? null, ['responsibilities']);

        // Accept <responsibilities> in either place: inside <description>
        // (what the code used to look for) or beside it (what the XML sends).
        $lists = [];
        if (isset($xml->description->responsibilities)) {
            $lists[] = $xml->description->responsibilities;
        }
        if (isset($xml->responsibilities)) {
            $lists[] = $xml->responsibilities;
        }

        $items = [];
        foreach ($lists as $list) {
            foreach ($list->item as $item) {
                $text = trim($this->innerMarkup($item));
                if ($text !== '') {
                    $items[] = $text;
                }
            }
        }
        if ($items) {
            $description .= '<br /><br />' . implode('<br />', array_unique($items));
        }

        return $this->allowOnlyDescriptionTags($description);
    }

    /**
     * An element's inner markup: child elements kept as markup, and a CDATA
     * section's contents taken literally (CDATA is how the new format carries
     * its markup, so its text IS the markup).
     */
    private function innerMarkup(?\SimpleXMLElement $el, array $skip = []): string
    {
        if ($el === null) {
            return '';
        }

        $node = dom_import_simplexml($el);
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($skip && $child->nodeType === XML_ELEMENT_NODE
                && in_array(strtolower($child->nodeName), $skip, true)) {
                continue;
            }
            $out .= $child->nodeType === XML_CDATA_SECTION_NODE
                ? $child->nodeValue                                // literal contents
                : $child->ownerDocument->saveXML($child);          // <br/>, text, …
        }

        return $out;
    }

    /**
     * Keep only the formatting tags, and drop every attribute from them.
     *
     * The description is printed RAW on the job detail page
     * (resources/views/jobs/detail.blade.php), so whatever is stored here
     * reaches a browser unescaped. The feed is written by a model and pasted
     * by a person: neither is a reason to trust a <script>, a style attribute
     * or an onclick. An allow-list is the only safe shape for that sink.
     */
    private function allowOnlyDescriptionTags(string $html): string
    {
        // strip_tags keeps the TEXT inside a tag it removes, so a
        // <script>alert(1)</script> would leave "alert(1)" sitting in the
        // description as visible prose. Remove those elements whole first.
        $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1\s*>#is', '', $html);
        $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*/?>#i', '', $html);

        $allowed = '<' . implode('><', self::DESCRIPTION_TAGS) . '>';
        $html = strip_tags($html, $allowed);

        // strip_tags keeps attributes on the tags it allows; none are wanted.
        $names = implode('|', self::DESCRIPTION_TAGS);
        $html = preg_replace('/<(' . $names . ')\b[^>]*?(\/?)>/i', '<$1$2>', $html);

        return trim($html);
    }

    /**
     * Decode HTML entities exactly once.
     *
     * Only ever applied to a value the parser may have handed over verbatim
     * (CDATA). Running it on already-decoded text is a no-op, which is what
     * makes it safe to apply unconditionally.
     */
    private function decodeEntitiesOnce(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    /**
     * Correct prefecture_id / area_id from the Japanese address.
     *
     * The address in the feed is authoritative; the prefecture_id/area_id the
     * translator's tool emits is not (Kanagawa in particular). Where the
     * address is decisive and disagrees, the address wins. Where it is vague,
     * the XML value stands and the field is marked for manual review -- we
     * never guess, because a confident wrong location is worse than a blank.
     *
     * Returns the corrected payload; the verdict lands in $lastLocationVerdict.
     */
    private function correctLocationFromAddress(array $jobData, string $address): array
    {
        // Everything below is an enhancement to the import, never a dependency of
        // it. If the lookup cannot run, the job must still import with whatever
        // the XML said -- a failed location check is a note for a translator, not
        // a 500 on the import screen.
        $original = $jobData;

        $this->lastLocationVerdict = ['corrections' => [], 'review' => [], 'address' => $address];

        if (trim($address) === '') {
            $this->lastLocationVerdict['review'][] = [
                'field'  => 'prefecture_id',
                'reason' => 'no address in the feed — cannot verify',
            ];
            return $jobData;
        }

        try {
            $this->locationResolver ??= AddressLocationResolver::fromDatabase();
            $verdict = $this->locationResolver->resolve($address);
        } catch (\Throwable $e) {
            Log::error('XML import: location check unavailable', [
                'error'   => $e->getMessage(),
                'address' => $address,
            ]);
            $this->lastLocationVerdict = [
                'corrections' => [],
                'review'      => [
                    ['field' => 'prefecture_id', 'reason' => 'location check unavailable'],
                    ['field' => 'area_id',       'reason' => 'location check unavailable'],
                ],
                'address' => $address,
            ];
            return $original;   // untouched XML values
        }

        foreach ([['prefecture_id', 'prefecture'], ['area_id', 'area']] as [$field, $key]) {
            $got = $verdict[$key];
            $xmlValue = (int) ($jobData[$field] ?? 0);

            if (! $got['decisive'] || $got['id'] === null) {
                // Vague address: keep whatever the XML said and ask a human.
                $this->lastLocationVerdict['review'][] = [
                    'field'  => $field,
                    'reason' => $got['reason'],
                ];
                continue;
            }

            if ($got['id'] === $xmlValue) {
                continue;   // feed already agreed with the address
            }

            $jobData[$field] = $got['id'];
            $this->lastLocationVerdict['corrections'][] = [
                'field'     => $field,
                'from'      => $xmlValue,
                'to'        => $got['id'],
                'matched'   => $got['name'],
                'from_label' => $this->locationLabel($field, $xmlValue),
                'to_label'   => $this->locationLabel($field, $got['id']),
            ];
        }

        return $jobData;
    }

    /**
     * Human-readable name for a prefecture/area id, for notes and logs.
     * Memoised per request: the bulk path can correct hundreds of fields in one
     * loop, and an uncached lookup here would be a straight N+1.
     */
    private array $locationLabelCache = [];

    private function locationLabel(string $field, int $id): string
    {
        if ($id <= 0) {
            return '(not set)';
        }

        $key = $field . ':' . $id;
        if (isset($this->locationLabelCache[$key])) {
            return $this->locationLabelCache[$key];
        }

        try {
            $name = $field === 'prefecture_id'
                ? Prefecture::whereKey($id)->value('english')
                : Area::whereKey($id)->value('english');
        } catch (\Throwable $e) {
            // A missing label must not cost us the correction itself.
            $name = null;
        }

        return $this->locationLabelCache[$key] = (string) ($name ?: "id {$id}");
    }

    /**
     * Append one line per correction so we can count feed errors per prefecture.
     *
     * Written straight to its own file rather than through Log::: production
     * runs LOG_LEVEL=error, so Log::info()/warning() would be discarded and we
     * would silently collect nothing. Tab-separated for awk/grep counting.
     */
    private function logLocationVerdict(string $jobNo): void
    {
        $v = $this->lastLocationVerdict;
        if (empty($v['corrections']) && empty($v['review'])) {
            return;
        }

        $dir = storage_path('logs');
        $file = $dir . '/location-corrections-' . date('Y-m') . '.log';
        $stamp = date('c');
        $addr = str_replace(["\t", "\n", "\r"], ' ', (string) ($v['address'] ?? ''));
        $lines = '';

        foreach ($v['corrections'] as $c) {
            $lines .= implode("\t", [
                $stamp, 'CORRECTED', $jobNo, $c['field'],
                $c['from'] . ' (' . $c['from_label'] . ')',
                $c['to'] . ' (' . $c['to_label'] . ')',
                'matched=' . $c['matched'], $addr,
            ]) . "\n";
        }
        foreach ($v['review'] as $r) {
            $lines .= implode("\t", [
                $stamp, 'REVIEW', $jobNo, $r['field'], '', '', $r['reason'], $addr,
            ]) . "\n";
        }

        try {
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($file, $lines, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Logging must never break an import.
            Log::error('location-correction log write failed', ['error' => $e->getMessage()]);
        }
    }
}
