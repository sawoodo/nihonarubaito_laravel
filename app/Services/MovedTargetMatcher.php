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

        // pageCompany() assumes the page writes "COMPANY/CODE rest of title",
        // which shigotoin does. sftworks writes the employer code FIRST:
        //   nwph_NWP205株式会社日本ワークプレイス NWP205の派遣社員の求人情報
        // so the extracted head was 'nwph_NWP205株式会社日本ワークプレイス' and
        // neither string contained the other — a miss on token order alone
        // (job 155158, 2026-10-30). Comparing our company against the WHOLE
        // normalised title is order-independent and strictly more permissive:
        // the head is a substring of the title, so anything that matched above
        // still matches here.
        if ($ourCo !== '' && mb_strlen($ourCo) >= 3
            && str_contains(self::normalizeCompany($page), $ourCo)) {
            return ['same' => true, 'by' => 'company', 'shared' => $ourCo,
                    'reason' => 'destination page title names our company'];
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
     * sftworks only: does the destination page's own printed id match the id in
     * the link we would store?
     *
     * sftworks rotates work_ids. When the stored ?work_id= stops opening an
     * entry form, the request lands on that listing's /detail/ page, whose
     * <title> ends with the listing's CURRENT id — 「… ｜シフトワークス(W015799846)」.
     * Relinking to that id is the same listing on a new id, which is what
     * MOVED means. Verified 2026-10-30: all 15 held rows resolved this way and
     * all 15 new ids opened a 応募入力 form.
     *
     * WHY THIS IS HOST-SCOPED, and must stay so. shigotoin also prints the
     * destination's id in its title (【121487695】) next to a final_url of
     * /offer/121487695, so a host-agnostic "title id equals url id" rule would
     * auto-accept EVERY shigotoin redirect — including the three measured on
     * 2026-10-30 that landed on a different employer, and the eight measured on
     * 2026-10-22. On shigotoin the id agreeing with the title proves only that
     * the page is self-consistent, never that the job is ours.
     *
     * The ownership evidence here is the redirect itself: the scan followed OUR
     * stored link to reach that title. That is the same trust sameListingId()
     * already places in the scan, and it is why this is a check on the id, not
     * on the words in the title.
     *
     * LIMIT: it rests on 15 observations of one host on one day. If a sftworks
     * row is ever found where the detail page is a different employer, this
     * must go back to requiring a company or place match as well.
     *
     * @return array{same:bool,id:?string,reason:string}
     */
    public function sameSftworksListing(?string $finalUrl, ?string $pageTitle): array
    {
        $final = (string) $finalUrl;
        if (! str_contains($final, 'sftworks.jp')) {
            return ['same' => false, 'id' => null,
                    'reason' => 'not a sftworks URL — rule does not apply'];
        }
        if (! preg_match('/[?&]work_id=([A-Z]\d{6,})/', $final, $u)) {
            return ['same' => false, 'id' => null,
                    'reason' => 'no work_id in the destination URL'];
        }
        if (! preg_match('/[(（]([A-Z]\d{6,})[)）]/u', (string) $pageTitle, $t)) {
            return ['same' => false, 'id' => null,
                    'reason' => 'destination page title prints no listing id'];
        }
        if ($u[1] !== $t[1]) {
            return ['same' => false, 'id' => $u[1],
                    'reason' => "URL id {$u[1]} is not the id the page prints ({$t[1]})"];
        }

        return ['same' => true, 'id' => $u[1],
                'reason' => "sftworks listing {$u[1]} — the page we were "
                          . 'redirected to prints this id as its own'];
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

        // THIRD BUG, found 2026-10-30: 府 is a prefecture suffix AND an
        // ordinary character in city names — 利府, 府中, 甲府, 別府, 防府. Two
        // separate faults followed from treating it only as a suffix:
        //
        //   a) the city-name class excluded 府, so 利府町 could never yield
        //      利府, and 府中市 yielded only the nonsense 中市;
        //   b) ([^\s]{2,4}?)[都道府県] matched the 府 INSIDE 利府, so
        //      宮城県宮城郡利府町利府字… produced the junk names 宮城郡利 and
        //      町利 — and 利府, which the destination page did name, never
        //      appeared at all (job 158220).
        //
        // The prefecture is only ever at the START of an address, so it is
        // taken from there and removed; after that 府 is just a character.
        $rest = $a;
        if (str_starts_with($a, '北海道')) {
            $out[] = '北海道';
            $rest = mb_substr($a, 3);
        } elseif (preg_match('/^(.{2,3}?)([都府県])/u', $a, $m)) {
            $out[] = $m[1];
            $rest = mb_substr($a, mb_strlen($m[1]) + 1);
        }

        if (preg_match_all('/([^\s市区町村郡]{1,6})([市区町村])/u', $rest, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $out[] = $hit[1];
                $out[] = $hit[1] . $hit[2];
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
