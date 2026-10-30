<?php

namespace App\Services;

use App\Models\Job;
use Illuminate\Support\Facades\DB;

/**
 * Is this job one we already have?
 *
 * A line-for-line port of the rules in scripts/dedupe-plan.py and
 * scripts/translator-dup-check.py, which were tuned over four review passes
 * against the whole published table and then applied as batch
 * dd-20261027-072837 (221 merges, verified). The Python is the reference
 * implementation; scripts/matcher-parity.php proves this agrees with it on
 * every pair the planner compared. If you change a rule here, change it there
 * and re-run the parity test — a silent divergence means the admin screen and
 * the planner disagree about what a duplicate is.
 *
 * Why this exists: JobDeduplicator matches an exact apply_link (statuses 2,3)
 * or company + prefecture + the first 30 characters of the title (1,2,3). The
 * sources rotate their listing URLs, agencies post one vacancy as several ads,
 * and a job retired last week is invisible to it — so on 24–26 Oct 2026, 41 of
 * 157 new jobs were repeats it did not catch.
 *
 * All normalisers are pure and take no database.
 */
class JobMatcher
{
    /** Role similarity needed when the two jobs are at the same place. */
    public const TITLE_MIN = 0.80;

    /** Higher bar when the two jobs point at DIFFERENT source listings. */
    public const TITLE_SAME_LISTING_DIFFERS = 0.90;

    /** Lower bar when the source itself says it is one listing. */
    public const SAME_LISTING_ROLE_MIN = 0.50;

    /** A job retired within this many days should be revived, not re-translated. */
    public const REVIVE_WINDOW_DAYS = 60;

    /** Live statuses worth matching against: pending, published, quota full. */
    public const LIVE_STATUSES = [2, 3, 6];

    /** Retired statuses, matched only inside the revive window. */
    public const RETIRED_STATUSES = [4, 5];

    /** Every column the matcher and the card need from a candidate row. */
    private const CANDIDATE_COLUMNS = [
        'id', 'job_no', 'title', 'company_name', 'address', 'station', 'wage',
        'job_category_id', 'prefecture_id', 'area_id', 'apply_link',
        'job_status_id', 'date', 'delete_at', 'updated_at', 'Expire_Date',
    ];

    private const TRACKING = '/^(utm_|fbclid|gclid|src$|ref$|aff|vc_|sid$)/i';

    private const CORP = '/(株式会社|有限会社|合同会社|\(株\)|\(有\)|㈱|㈲)/u';

    private const STATION = '/([一-龯々ぁ-んァ-ヶー・]{1,12}駅)/u';

    private const DASHES = ['‐', '‑', '‒', '–', '—', '―', '−', 'ー', '－', '─'];

    private const KANJI_NUM = [
        '一' => 1, '二' => 2, '三' => 3, '四' => 4, '五' => 5,
        '六' => 6, '七' => 7, '八' => 8, '九' => 9,
    ];

    private const TITLE_STOP = [
        'part', 'time', 'job', 'jobs', 'at', 'in', 'the',
        'a', 'an', 'and', 'for', 'of', 'station',
    ];

    private const STATUS_LABEL = [
        1 => 'Draft', 2 => 'Pending', 3 => 'Published',
        4 => 'Expired', 5 => 'Trashed', 6 => 'Quota full',
    ];

    // ── normalisation ───────────────────────────────────────────────────

    private static function nfkc(?string $s): string
    {
        return \Normalizer::normalize((string) $s, \Normalizer::FORM_KC) ?: (string) $s;
    }

    /**
     * Python urllib.parse.quote_plus: space becomes '+', '~' stays literal.
     * PHP's urlencode agrees except that it percent-encodes '~'.
     */
    private static function quotePlus(string $s): string
    {
        return str_replace('%7E', '~', urlencode($s));
    }

    /**
     * The apply link with tracking parameters dropped, so two links that reach
     * the same listing compare equal. Returns '' for anything that cannot
     * identify a listing — empty, the literal '123' placeholder, or a value
     * with no scheme and host. Two published jobs hold such values today: one
     * a truncated chat URL, one an entire XML document pasted into the field.
     * An empty key never matches another, which is the point: a placeholder
     * must not make two unrelated jobs look like one listing.
     */
    public static function normLink(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || $url === '123') {
            return '';
        }

        $p = parse_url($url);
        if ($p === false || ($p['scheme'] ?? '') === '' || ($p['host'] ?? '') === '') {
            return '';
        }

        $pairs = [];
        foreach (explode('&', $p['query'] ?? '') as $field) {
            if ($field === '') {
                continue;
            }
            $eq = strpos($field, '=');
            $k = $eq === false ? $field : substr($field, 0, $eq);
            $v = $eq === false ? '' : substr($field, $eq + 1);
            $k = urldecode($k);
            if (preg_match(self::TRACKING, $k)) {
                continue;
            }
            $pairs[] = [$k, urldecode($v)];
        }
        usort($pairs, fn ($x, $y) => [$x[0], $x[1]] <=> [$y[0], $y[1]]);

        $query = implode('&', array_map(
            fn ($kv) => self::quotePlus($kv[0]) . '=' . self::quotePlus($kv[1]),
            $pairs
        ));

        $host = preg_replace('/^www\./', '', strtolower($p['host']));
        $path = rtrim($p['path'] ?? '', '/');

        return '//' . $host . $path . ($query !== '' ? '?' . $query : '');
    }

    /**
     * "site:id" for a URL that identifies ONE source listing, else null.
     * This is the strongest key we have: it survives the URL rewrites that
     * defeat an exact apply_link match.
     */
    public static function sourceId(?string $url): ?string
    {
        $u = (string) $url;

        if (preg_match('~shigotoin\.com/.*?offer/(\d+)~', $u, $m)) {
            return 'shigotoin:' . $m[1];
        }
        if (preg_match('~baitoru\.com/.*?(?:job|entry/)(\d{5,})~', $u, $m)) {
            return 'baitoru:' . $m[1];
        }
        if (preg_match('~family\.co\.jp/.*?new_entry/(\d+)~', $u, $m)) {
            return 'familymart:' . $m[1];
        }

        return null;
    }

    /** 北栄一丁目13-17 -> 北栄1丁目13-17, before the shared normaliser. */
    public static function kanjiChome(?string $addr): string
    {
        return preg_replace_callback(
            '/([一二三四五六七八九]?十?[一二三四五六七八九]?)丁目/u',
            function (array $m): string {
                $t = $m[1];
                if ($t === '') {
                    return $m[0];
                }
                if ($t === '十') {
                    $n = 10;
                } elseif (mb_substr($t, 0, 1) === '十') {
                    $n = 10 + self::KANJI_NUM[mb_substr($t, 1, 1)];
                } elseif (mb_strpos($t, '十') !== false) {
                    $n = self::KANJI_NUM[mb_substr($t, 0, 1)] * 10
                        + (mb_strlen($t) === 3 ? self::KANJI_NUM[mb_substr($t, 2, 1)] : 0);
                } else {
                    $n = self::KANJI_NUM[$t];
                }

                return $n . '丁目';
            },
            (string) $addr
        );
    }

    /**
     * The address down to its street number, building names dropped, so
     * 東京都新宿区西新宿1丁目5番1号（小田急新宿店内） and 西新宿1-5-1 compare equal.
     */
    public static function normAddr(?string $a): string
    {
        $a = self::nfkc($a);
        $a = preg_replace('/\s+/u', '', $a);
        $a = preg_replace('/^〒?\d{3}-?\d{4}/u', '', $a);
        $a = preg_replace('/^日本/u', '', $a);
        $a = str_replace(self::DASHES, '-', $a);
        $a = preg_replace('/(\d)丁目/u', '$1-', $a);
        $a = preg_replace('/(\d)番地?/u', '$1-', $a);
        $a = preg_replace('/(\d)号/u', '$1', $a);
        $a = trim(preg_replace('/-+/u', '-', $a), '-');

        return preg_match('/^(.*?\d+(?:-\d+)*)/u', $a, $m) ? $m[1] : $a;
    }

    /** The company without its corporate form or punctuation, lowercased. */
    public static function normCompany(?string $c): string
    {
        $c = preg_replace(self::CORP, '', self::nfkc($c));

        return mb_strtolower(preg_replace('/[\s・\/／()（）「」【】\-]/u', '', $c), 'UTF-8');
    }

    /** The 〜駅 token inside an otherwise English station string, or ''. */
    public static function stationKey(?string $s): string
    {
        return preg_match(self::STATION, self::nfkc($s), $m) ? $m[1] : '';
    }

    /**
     * The STARTING wage. '1,150円〜1,500円' and '1,150円' are one job's wage
     * written two ways; joining every digit made them differ as 11501500/1150.
     */
    public static function wageKey(?string $w): string
    {
        $w = str_replace(',', '', self::nfkc($w));

        return preg_match('/\d{3,}/u', $w, $m) ? $m[0] : '';
    }

    /** The role, not the place: titles are '<role> at <place>'. */
    public static function rolePart(?string $t): string
    {
        $t = self::nfkc($t);
        $i = mb_strrpos(mb_strtolower($t, 'UTF-8'), ' at ');

        return ($i !== false && $i > 0) ? mb_substr($t, 0, $i) : $t;
    }

    /** @return array<int,string> the role's distinctive words, deduplicated */
    public static function titleTokens(?string $t): array
    {
        $t = str_replace('&', ' and ', mb_strtolower(self::rolePart($t), 'UTF-8'));
        $t = preg_replace('/[\d,]+円/u', ' ', $t);
        $t = preg_replace('/[^a-z0-9\s]/u', ' ', $t);

        $words = preg_split('/\s+/u', trim($t), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_diff($words, self::TITLE_STOP)));
    }

    /** Word-set Jaccard similarity of two roles, 0.0 – 1.0. */
    public static function titleSim(?string $a, ?string $b): float
    {
        $ta = self::titleTokens($a);
        $tb = self::titleTokens($b);
        if (! $ta || ! $tb) {
            return 0.0;
        }

        $inter = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));

        return $union === 0 ? 0.0 : $inter / $union;
    }

    /** An address precise enough to name one building, not just a town. */
    public static function specific(string $addr): bool
    {
        return preg_match('/\d/u', $addr) === 1 && mb_strlen($addr) >= 6;
    }

    /** true/false when both links identify a source listing, else null. */
    public static function sameListing(array $a, array $b): ?bool
    {
        if ($a['_link'] !== '' && $a['_link'] === $b['_link']) {
            return true;
        }
        if ($a['_sid'] !== null && $b['_sid'] !== null) {
            return $a['_sid'] === $b['_sid'];
        }

        return null;
    }

    /**
     * 'certain', 'review' or null for two jobs of one company.
     *
     * The caller supplies the company gate; this decides place, role and wage.
     * The role check applies on the ADDRESS path too, because for a staffing
     * agency the stored address is the agency office, so one address covers
     * many unrelated placements — in the first dedupe plan that mistake
     * accounted for 182 of 389 proposed trashes.
     */
    public static function judge(array $a, array $b): ?string
    {
        if ($a['_cat'] !== $b['_cat']) {
            return null;
        }

        $listing = self::sameListing($a, $b);

        if ($listing === true) {
            // A different wage on one link usually means a translator pasted
            // the wrong link — two real jobs. Very different roles on one
            // listing usually mean one copy was mistranslated: hold those.
            if ($a['_wage'] !== '' && $a['_wage'] === $b['_wage']
                && self::titleSim($a['title'], $b['title']) >= self::SAME_LISTING_ROLE_MIN) {
                return 'certain';
            }

            return 'review';
        }

        $sa = self::specific($a['_addr']);
        $sb = self::specific($b['_addr']);
        if ($sa && $sb && $a['_addr'] !== $b['_addr']) {
            return null;                      // two shops of one agency
        }

        // Without street numbers the same station is the same place only if
        // the town-level addresses agree too (one may be a shorter form of the
        // other): 東大阪市荒本新町 and 東大阪市菱江 share 荒本駅, different towns.
        $townOk = str_starts_with($a['_addr'], $b['_addr'])
            || str_starts_with($b['_addr'], $a['_addr']);
        $samePlace = ($sa && $sb)
            || (! $sa && ! $sb && $a['_st'] !== '' && $a['_st'] === $b['_st'] && $townOk);

        if (! $samePlace) {
            return ($a['_st'] !== '' && $a['_st'] === $b['_st']) ? 'review' : null;
        }

        $sameWage = $a['_wage'] !== '' && $a['_wage'] === $b['_wage'];
        $need = $listing === false ? self::TITLE_SAME_LISTING_DIFFERS : self::TITLE_MIN;

        if ($sameWage && self::titleSim($a['title'], $b['title']) >= $need) {
            return 'certain';
        }

        return 'review';
    }

    /**
     * Add the derived keys judge() reads. Accepts an admin/XML job array or a
     * database row; missing keys are treated as empty.
     *
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    public static function prepare(array $row): array
    {
        $get = fn (string $k) => (string) ($row[$k] ?? '');

        $row['title'] = $get('title');
        $row['_co'] = self::normCompany($get('company_name'));
        $row['_addr'] = self::normAddr(self::kanjiChome($get('address')));
        $row['_link'] = self::normLink($get('apply_link'));
        $row['_sid'] = self::sourceId($get('apply_link'));
        $row['_st'] = self::stationKey($get('station'));
        $row['_wage'] = self::wageKey($get('wage'));
        $row['_cat'] = $get('job_category_id');

        return $row;
    }

    // ── matching against the database ───────────────────────────────────

    /**
     * Every job that is plausibly this one, certain matches first.
     *
     * @param  array<string,mixed>  $new  an unsaved job (createJobArray output
     *                                    or a validated admin form)
     * @return array<int,array<string,mixed>>
     */
    public static function findMatches(array $new): array
    {
        $a = self::prepare($new);
        $self = (string) ($new['job_no'] ?? '');

        $out = [];
        foreach (self::resolveDedupeTrashed(self::candidateRows($a)) as $row) {
            $b = self::prepare((array) $row);
            if ($self !== '' && (string) $b['job_no'] === $self) {
                continue;
            }

            // A status-1 row is a bulk "[SAME AS N]" marker, not a job. Its
            // link reaches it through the listing-id query, which is any
            // status, and JobMatchApplier::relink() refuses a draft -- so it
            // would block creation behind a button that then says no. Job N
            // holds the same link and is matched in its own right.
            if ((int) $b['job_status_id'] === Job::STATUS_DRAFT) {
                continue;
            }

            $listing = self::sameListing($a, $b);
            $sameCompany = $a['_co'] !== '' && $a['_co'] === $b['_co'];

            // The company gate the planner applies — except when the source
            // itself says it is one listing, which is stronger evidence than
            // our own company string (translators mistype it, agencies use
            // several spellings).
            if ($listing !== true && ! $sameCompany) {
                continue;
            }

            $verdict = self::judge($a, $b);
            if ($verdict === null) {
                continue;
            }

            // One offer id under two company names is strong evidence but not
            // proof: offer 118120821 is stored on two unrelated employers
            // because a wrong link was pasted onto one of them. Relinking the
            // wrong job with one click is the failure to avoid, so this is
            // always 'review' — the translator looks and decides.
            if ($listing === true && ! $sameCompany) {
                $verdict = 'review';
            }

            $status = (int) $b['job_status_id'];
            $retired = in_array($status, self::RETIRED_STATUSES, true)
                ? self::daysAgo($b['Expire_Date'] ?? null, $b['updated_at'] ?? null)
                : null;

            // A match on a job retired longer ago than the revive window can
            // only ever be information. JobMatchApplier::relink() refuses to
            // revive it -- reviving a page gone for a year is a resurrection,
            // not a relink -- so a 'certain' card would block creation behind
            // a button that then says no. Creating the new job is the right
            // outcome, and 'review' allows it. Only the listing-id candidate
            // set reaches back this far; the prefecture+category set is
            // already bounded by the window.
            if ($retired !== null && $retired > self::REVIVE_WINDOW_DAYS) {
                $verdict = 'review';
            }

            $out[] = [
                'job' => $row,
                'verdict' => $verdict,
                'reason' => self::reason($a, $b, $listing, $sameCompany),
                'status_label' => self::STATUS_LABEL[$status] ?? "Status {$status}",
                'retired_days_ago' => $retired,
            ];
        }

        // certain before review, live before retired, newest first
        $rank = fn (array $m) => [
            $m['verdict'] === 'certain' ? 0 : 1,
            $m['retired_days_ago'] === null ? 0 : 1,
            -(int) (((array) $m['job'])['id'] ?? 0),
        ];
        usort($out, fn (array $x, array $y) => $rank($x) <=> $rank($y));

        return $out;
    }

    /**
     * Swap a candidate that our OWN dedupe batch trashed for the job it was
     * merged into.
     *
     * On 2026-10-27 batch dd-20261027-072837 trashed 221 published copies,
     * each with a job_status_history row {source: 'dedupe', reason: 'duplicate
     * of job K'}. Those copies still hold the apply link and address that
     * matched, so a fresh import of the same vacancy matches the COPY.
     * Offering "Update job <copy>" would revive it -- recreating by hand the
     * duplicate the merge removed, and leaving keeper and copy both live with
     * the same link.
     *
     * So the card points at K instead. If K is gone, or K is itself retired
     * beyond the revive window, the candidate is dropped: there is nothing
     * useful to offer and a dead end is worse than no card.
     *
     * @param  array<int,object>  $rows
     * @return array<int,object>
     */
    private static function resolveDedupeTrashed(array $rows): array
    {
        $trashedNos = [];
        foreach ($rows as $row) {
            if ((int) $row->job_status_id === Job::STATUS_TRASHED) {
                $trashedNos[] = (string) $row->job_no;
            }
        }
        if (! $trashedNos) {
            return $rows;
        }

        // Latest dedupe row per trashed copy. A status-5 job trashed by hand
        // or by the link check has no 'dedupe' row and is left alone.
        $keeperOf = [];
        $history = DB::table('job_status_history')
            ->whereIn('job_no', $trashedNos)
            ->where('source', 'dedupe')
            ->where('new_status', Job::STATUS_TRASHED)
            ->orderBy('id')
            ->get(['job_no', 'reason']);

        foreach ($history as $h) {
            if (preg_match('/duplicate of job (\\d+)/', (string) $h->reason, $m)) {
                $keeperOf[(string) $h->job_no] = $m[1];        // last row wins
            }
        }
        if (! $keeperOf) {
            return $rows;
        }

        $keepers = DB::table('jobs')
            ->whereIn('job_no', array_values(array_unique($keeperOf)))
            ->get(self::CANDIDATE_COLUMNS)
            ->keyBy('job_no');

        $out = [];
        foreach ($rows as $row) {
            $no = (string) $row->job_no;
            if (! isset($keeperOf[$no])) {
                $out[$row->id] = $row;
                continue;
            }

            $keeper = $keepers[$keeperOf[$no]] ?? null;
            if (! $keeper) {
                continue;                        // keeper gone — nothing to offer
            }
            if (in_array((int) $keeper->job_status_id, self::RETIRED_STATUSES, true)) {
                $days = self::daysAgo($keeper->Expire_Date ?? null, $keeper->updated_at ?? null);
                if ($days === null || $days > self::REVIVE_WINDOW_DAYS) {
                    continue;
                }
            }
            $out[$keeper->id] = $keeper;         // keyed by id, so one keeper once
        }

        return array_values($out);
    }

    /** Why these two matched, in the words the card shows. */
    public static function reason(array $a, array $b, ?bool $listing, bool $sameCompany): string
    {
        if ($listing === true) {
            return $sameCompany
                ? 'same listing'
                : 'same listing, company differs — check';
        }
        if (self::specific($a['_addr']) && $a['_addr'] === $b['_addr']) {
            return 'same shop + role';
        }

        return 'same station + role';
    }

    private static function daysAgo(?string $expireDate, ?string $updatedAt): ?int
    {
        $v = $expireDate ?: $updatedAt;
        if (! $v || str_starts_with($v, '0000')) {
            return null;
        }
        $ts = strtotime($v);

        return $ts === false ? null : (int) floor((time() - $ts) / 86400);
    }

    /**
     * Two queries, judged in PHP.
     *
     * 1. Anything carrying this listing's id — ANY status, including long-dead
     *    jobs, plus jobs whose apply link used to be this one. A leading
     *    wildcard cannot use an index, so this is a full scan: measured at
     *    0.12s over 116,316 rows on production, which is why there is no
     *    generated column here.
     * 2. The same prefecture and category, live or retired within the revive
     *    window. Served by idx_status_pref_category; the largest bucket on
     *    production is Tokyo x Restaurant at 642 rows.
     *
     * @return array<int,object>
     */
    private static function candidateRows(array $a): array
    {
        $cols = self::CANDIDATE_COLUMNS;

        $rows = [];

        $idFragment = $a['_sid'] !== null ? explode(':', $a['_sid'])[1] : null;
        $rawLink = trim((string) ($a['apply_link'] ?? ''));

        if ($idFragment !== null || $rawLink !== '') {
            $q = DB::table('jobs')->select($cols);
            $q->where(function ($w) use ($idFragment, $rawLink) {
                if ($rawLink !== '') {
                    $w->orWhere('apply_link', $rawLink);
                }
                if ($idFragment !== null) {
                    $w->orWhere('apply_link', 'like', '%' . $idFragment . '%');
                }
            });
            foreach ($q->get() as $row) {
                $rows[$row->id] = $row;
            }

            if ($idFragment !== null) {
                $jobNos = DB::table('job_apply_link_history')
                    ->where('old_apply_link', 'like', '%' . $idFragment . '%')
                    ->orWhere('new_apply_link', 'like', '%' . $idFragment . '%')
                    ->distinct()
                    ->pluck('job_no')
                    ->all();

                if ($jobNos) {
                    foreach (DB::table('jobs')->select($cols)->whereIn('job_no', $jobNos)->get() as $row) {
                        $rows[$row->id] = $row;
                    }
                }
            }
        }

        $prefectureId = (int) ($a['prefecture_id'] ?? 0);
        $categoryId = (int) ($a['job_category_id'] ?? 0);

        if ($prefectureId > 0 && $categoryId > 0) {
            $pool = DB::table('jobs')
                ->select($cols)
                ->where('lang_id', 1)
                ->where('prefecture_id', $prefectureId)
                ->where('job_category_id', $categoryId)
                ->where(function ($w) {
                    $w->whereIn('job_status_id', self::LIVE_STATUSES)
                        ->orWhere(function ($r) {
                            $r->whereIn('job_status_id', self::RETIRED_STATUSES)
                                ->whereRaw('COALESCE(`Expire_Date`, `updated_at`) >= NOW() - INTERVAL '
                                    . (int) self::REVIVE_WINDOW_DAYS . ' DAY');
                        });
                })
                ->get();

            foreach ($pool as $row) {
                $rows[$row->id] = $row;
            }
        }

        return array_values($rows);
    }
}
