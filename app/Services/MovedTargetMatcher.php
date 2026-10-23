<?php

namespace App\Services;

/**
 * Decides whether a shigotoin redirect actually lands on OUR job.
 *
 * WHY THIS EXISTS
 * shigotoin rotates offer ids, and an old /offer/<id> usually 301s to the same
 * employer's new listing — but not always. Measured 2026-10-22 over 404 MOVED
 * rows: 8 landed somewhere else entirely. One of our Tokyo parcel-sorting jobs
 * redirected to a sweets-sales job in Osaka. Rewriting apply_link on those would
 * have sent applicants to an unrelated employer, so a redirect is never trusted
 * on its own.
 *
 * WHAT COUNTS AS A MATCH
 * Either the company name or a place name from our address appears in the
 * destination page's <title>. Both signals are needed because shigotoin writes
 * the BRANCH where we store the PARENT (丸亀製麺イオンモール橿原店 vs
 * 株式会社トリドールホールディングス), and conversely a staffing agency's name
 * is identical across the whole country, where only the place distinguishes.
 *
 * TWO BUGS THIS CLASS IS CAREFUL ABOUT -- both silently inflated the
 * "different job" count when they were present:
 *   1. Greedy place matching. On 山形県天童市南町1-6-13 a greedy
 *      ([^都道府県]{2,6})([市区町村]) captures 天童市南町, so 天童 never gets
 *      compared with the page's 丸亀製麺天童店. The name class here excludes
 *      市区町村 so it stops at the first suffix.
 *   2. Single-kanji cities. 柏市 and 堺市 are one character before the suffix,
 *      so a {2,6} minimum skips them entirely. We capture both 柏 and 柏市 but
 *      only ever COMPARE names of 2+ characters: a bare one-kanji name is too
 *      noisy to trust (a job in 津市 would match any page naming 沼津 or 焼津).
 *      The cost is that a page saying 柏西口店 does not match a 柏市 address, so
 *      that row goes to staff review. Unverified is the correct answer there —
 *      this class decides whether to RELINK, and a wrong relink is worse than a
 *      row a human glances at.
 */
class MovedTargetMatcher
{
    /** Company-form noise that differs between our records and the page. */
    private const COMPANY_NOISE = ['株式会社', '有限会社', '(株)', '（株）', '合同会社', '㈱'];

    /**
     * Does the destination page look like our job?
     *
     * @return array{same:bool,by:?string,shared:string,reason:string}
     */
    public function matches(?string $companyName, ?string $address, ?string $pageTitle): array
    {
        $page = trim((string) $pageTitle);

        // No captured title -> unverifiable. Caller must route to review and
        // must NOT relink: an old results file predates source_title.
        if ($page === '') {
            return ['same' => false, 'by' => null, 'shared' => '',
                    'reason' => 'destination page title not captured — cannot verify'];
        }

        $ourCo  = self::normalizeCompany((string) $companyName);
        $pageCo = self::normalizeCompany(self::pageCompany($page));
        if ($ourCo !== '' && $pageCo !== ''
            && (str_contains($pageCo, $ourCo) || str_contains($ourCo, $pageCo))) {
            return ['same' => true, 'by' => 'company', 'shared' => $ourCo,
                    'reason' => 'company name matches the destination page'];
        }

        foreach (self::places((string) $address) as $place) {
            if (mb_strpos($page, $place) !== false) {
                return ['same' => true, 'by' => 'location', 'shared' => $place,
                        'reason' => "destination page mentions {$place}"];
            }
        }

        // Last resort: any long shared run of characters (shopping-centre and
        // building names live in the address but match no place pattern, e.g.
        // シャポー小岩, unimoちはら台).
        $shared = self::longestCommon(self::fold((string) $address), self::fold($page));
        if (mb_strlen($shared) >= 4) {
            return ['same' => true, 'by' => 'location', 'shared' => $shared,
                    'reason' => "address and page share “{$shared}”"];
        }

        return ['same' => false, 'by' => null, 'shared' => $shared,
                'reason' => 'neither company nor location matches the destination page'];
    }

    /**
     * Does the redirect land on the SAME listing id it started from?
     *
     * This is a stronger signal than any title comparison, and it exists
     * because baitoru and shigotoin redirect for opposite reasons.
     *
     *   baitoru    entry/form/job160917922/ -> entry/160917922?obo_tab_kbn=
     *              Same listing, new URL shape. Measured 2026-10-23: every one
     *              of our 693 legacy links redirects, and the id is carried
     *              through unchanged.
     *   shigotoin  offer/121273633 -> offer/121403405
     *              A genuinely different offer id, which may or may not be our
     *              employer. That is the case matches() exists to judge.
     *
     * Without this, every baitoru MOVED row fails matches() for a reason that
     * says nothing about the destination: baitoru's apply page is titled
     * 「応募フォーム - バイトル」 and names no company or place at all. 693
     * same-listing rewrites would queue for staff review and bury the few rows
     * that genuinely need a human.
     *
     * Derived from the two URLs, never read from the results file: the CSV is
     * staff-uploaded, and a column that grants an automatic relink must not be
     * forgeable by editing a spreadsheet.
     *
     * Compares runs of 5+ digits because the id is the only long number in
     * either URL — path layout is not hardcoded, so this holds for any host.
     * Tracking parameters (?pname=link_job_entry_pc_detail_all_1) carry no such
     * run and cannot create a false match.
     *
     * @return array{same:bool,id:?string,reason:string}
     */
    public function sameListingId(?string $requestedUrl, ?string $finalUrl): array
    {
        $ids = static function (?string $u): array {
            preg_match_all('/\d{5,}/', (string) $u, $m);
            return array_unique($m[0]);
        };

        $from = $ids($requestedUrl);
        $to   = $ids($finalUrl);

        if (! $from || ! $to) {
            return ['same' => false, 'id' => null,
                    'reason' => 'no listing id in one of the URLs — cannot compare'];
        }

        $shared = array_values(array_intersect($from, $to));
        if ($shared) {
            return ['same' => true, 'id' => $shared[0],
                    'reason' => "redirect keeps listing id {$shared[0]} — same posting, new URL"];
        }

        return ['same' => false, 'id' => $from[0] ?? null,
                'reason' => 'redirect changed the listing id — may be a different job'];
    }

    /**
     * Are these jobs the same posting? Used for the same-final-offer groups.
     *
     * Address is the PRIMARY signal. Company naming is unreliable here: group
     * offer/121281747 pairs 丸亀製麺イオンモール橿原店 with 株式会社トリドール
     * ホールディングス at a byte-identical address — branch versus parent, one
     * restaurant. Judging on company first calls that "different"; it is not.
     *
     * @param  array<int,array{company_name:?string,address:?string}>  $jobs
     * @return array{same:bool,shared_address:string,same_company:bool,reason:string}
     */
    public function sameGroup(array $jobs): array
    {
        if (count($jobs) < 2) {
            return ['same' => false, 'shared_address' => '', 'same_company' => false,
                    'reason' => 'single job'];
        }

        $addresses = array_map(fn ($j) => self::fold((string) ($j['address'] ?? '')), $jobs);
        $shared = array_shift($addresses);
        foreach ($addresses as $a) {
            $shared = self::longestCommon($shared, $a);
        }

        $companies = array_unique(array_map(
            fn ($j) => self::normalizeCompany((string) ($j['company_name'] ?? '')), $jobs));
        $sameCompany = count($companies) === 1 && reset($companies) !== '';

        $len = mb_strlen($shared);
        if ($len >= 8) {
            return ['same' => true, 'shared_address' => $shared, 'same_company' => $sameCompany,
                    'reason' => 'same address'];
        }
        if ($sameCompany && $len >= 4) {
            return ['same' => true, 'shared_address' => $shared, 'same_company' => true,
                    'reason' => 'same company and overlapping address'];
        }
        if ($sameCompany) {
            return ['same' => true, 'shared_address' => $shared, 'same_company' => true,
                    'reason' => 'same company'];
        }

        return ['same' => false, 'shared_address' => $shared, 'same_company' => false,
                'reason' => 'different company and different address'];
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /** The page writes "COMPANY/CODE rest of title"; take the company part. */
    public static function pageCompany(string $title): string
    {
        $first = explode(' ', trim($title))[0] ?? '';
        return trim(explode('/', $first)[0] ?? '');
    }

    public static function normalizeCompany(string $s): string
    {
        $s = str_replace(self::COMPANY_NOISE, '', $s);
        return (string) preg_replace('/[\s　]+/u', '', $s);
    }

    /** Full-width digits and dashes folded, whitespace removed. */
    public static function fold(string $s): string
    {
        $s = strtr($s, [
            '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
            '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
            'ー' => '-', '－' => '-', '　' => '',
        ]);
        return (string) preg_replace('/\s+/u', '', $s);
    }

    /**
     * Place names from an address, with and without their 市区町村 suffix.
     * {1,6} not {2,6}: 柏市 and 堺市 are a single character.
     */
    public static function places(string $address): array
    {
        $a = self::fold($address);
        $out = [];

        if (preg_match_all('/([^\s都道府県市区町村郡]{1,6})([市区町村])/u', $a, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $out[] = $hit[1];
                $out[] = $hit[1] . $hit[2];
            }
        }
        if (preg_match_all('/([^\s]{2,4}?)[都道府県]/u', $a, $m)) {
            foreach ($m[1] as $hit) {
                $out[] = $hit;
            }
        }
        // Malls and buildings are strong location evidence.
        if (preg_match_all('/(イオンモール[^\s0-9]{0,6}|[^\s0-9]{2,8}(?:モール|プラザ|ビル|店))/u', $a, $m)) {
            foreach ($m[1] as $hit) {
                $out[] = $hit;
            }
        }

        // Longest first so the most specific name wins, and drop 1-char noise
        // unless it is a real single-kanji city (kept above as name+suffix too).
        $out = array_values(array_unique(array_filter($out, fn ($p) => mb_strlen($p) >= 2)));
        usort($out, fn ($x, $y) => mb_strlen($y) <=> mb_strlen($x));

        return $out;
    }

    /** Longest common substring. Inputs here are short (addresses/titles). */
    public static function longestCommon(string $a, string $b): string
    {
        $best = '';
        $len = mb_strlen($a);
        for ($i = 0; $i < $len; $i++) {
            $j = $i + mb_strlen($best) + 1;
            while ($j <= $len) {
                $candidate = mb_substr($a, $i, $j - $i);
                if (mb_strpos($b, $candidate) !== false) {
                    $best = $candidate;
                    $j++;
                } else {
                    break;
                }
            }
        }
        return $best;
    }

    /** Stable id for a same-final-offer group, matching JobDeduplicator's scheme. */
    public static function groupHash(string $finalUrl): string
    {
        return md5('finaloffer|' . trim($finalUrl));
    }
}
