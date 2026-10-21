<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Derives prefecture_id / area_id from a Japanese address string.
 *
 * WHY: XML-imported jobs arrive with a wrong prefecture_id/area_id often enough
 * that translators fix them by hand on the preview page. The XML always carries
 * the full Japanese address, which is authoritative. This class reads that
 * address and tells the caller what the location *should* be.
 *
 * IT NEVER WRITES. It returns a verdict; the caller decides.
 *
 * ------------------------------------------------------------------
 * Two data traps this class deliberately works around
 * ------------------------------------------------------------------
 * 1. `prefectures.japanese` is NOT trustworthy as a match key. As of
 *    2026-10-21 it holds three wrong values -- id 14 Niigata = 新潟市,
 *    id 23 Kumamoto = 熊本市 (both CITY names, not prefectures) and
 *    id 39 Wakayama = 若山 (should be 和歌山) -- plus inconsistent suffixes
 *    (愛知県/宮城県/岩手県 carry 県, the other 44 do not). Matching on that
 *    column would silently fail for at least Wakayama, Niigata and Kumamoto.
 *    So we match on a canonical list below and resolve to OUR ids via the
 *    `english` column, which is clean for all 47 rows.
 *
 * 2. We always match the FULL official name including 都/道/府/県. Without the
 *    suffix the substrings collide and produce confident wrong answers:
 *      - 京都 is a substring of 東京都  -> a Tokyo address would read as Kyoto
 *      - 神奈川 is a substring of 横浜市神奈川区 -> a Yokohama ward would read
 *        as the prefecture even for an address in another prefecture
 *    Matching 京都府 / 神奈川県 removes both.
 */
class AddressLocationResolver
{
    /** Canonical official prefecture name => `prefectures`.english */
    public const CANONICAL = [
        '北海道' => 'Hokkaido',  '青森県' => 'Aomori',    '岩手県' => 'Iwate',
        '宮城県' => 'Miyagi',    '秋田県' => 'Akita',     '山形県' => 'Yamagata',
        '福島県' => 'Fukushima', '茨城県' => 'Ibaraki',   '栃木県' => 'Tochigi',
        '群馬県' => 'Gunma',     '埼玉県' => 'Saitama',   '千葉県' => 'Chiba',
        '東京都' => 'Tokyo',     '神奈川県' => 'Kanagawa', '新潟県' => 'Niigata',
        '富山県' => 'Toyama',    '石川県' => 'Ishikawa',  '福井県' => 'Fukui',
        '山梨県' => 'Yamanashi', '長野県' => 'Nagano',    '岐阜県' => 'Gifu',
        '静岡県' => 'Shizuoka',  '愛知県' => 'Aichi',     '三重県' => 'Mie',
        '滋賀県' => 'Shiga',     '京都府' => 'Kyoto',     '大阪府' => 'Osaka',
        '兵庫県' => 'Hyogo',     '奈良県' => 'Nara',      '和歌山県' => 'Wakayama',
        '鳥取県' => 'Tottori',   '島根県' => 'Shimane',   '岡山県' => 'Okayama',
        '広島県' => 'Hiroshima', '山口県' => 'Yamaguchi', '徳島県' => 'Tokushima',
        '香川県' => 'Kagawa',    '愛媛県' => 'Ehime',     '高知県' => 'Kochi',
        '福岡県' => 'Fukuoka',   '佐賀県' => 'Saga',      '長崎県' => 'Nagasaki',
        '熊本県' => 'Kumamoto',  '大分県' => 'Oita',      '宮崎県' => 'Miyazaki',
        '鹿児島県' => 'Kagoshima', '沖縄県' => 'Okinawa',
    ];

    /**
     * Phrases that mean "this job covers more than one place". The address may
     * still name a specific ward, but it is no longer authoritative, so we
     * derive nothing and ask a human. Seen in production:
     *   東京都千代田区エリア（その他1都3県多数）
     */
    public const MULTI_LOCATION_HINTS = [
        'エリア', 'その他', '多数', '各店', '複数', '各地', 'ほか', '勤務地多数',
    ];

    /** english (lowercased) => our prefecture id */
    private array $prefIdByEnglish;

    /** prefecture_id => [ ['id'=>areaId, 'ja'=>japanese], ... ] */
    private array $areasByPrefecture;

    public function __construct(array $prefIdByEnglish, array $areasByPrefecture)
    {
        $this->prefIdByEnglish   = $prefIdByEnglish;
        $this->areasByPrefecture = $areasByPrefecture;
    }

    /**
     * Build from the database. areas hang off towns, not prefectures:
     * areas.town_id -> towns.id, towns.prefecture_id. (CLAUDE.md's
     * "areas: id, prefecture_id" is wrong -- there is no such column.)
     */
    public static function fromDatabase(): self
    {
        $prefs = [];
        foreach (DB::table('prefectures')->select('id', 'english')->get() as $p) {
            $prefs[mb_strtolower(trim($p->english))] = (int) $p->id;
        }

        $areas = [];
        $rows = DB::table('areas as a')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->select('a.id', 'a.japanese', 't.prefecture_id')
            ->get();
        foreach ($rows as $r) {
            $ja = trim((string) $r->japanese);
            if ($ja === '') {
                continue;
            }
            $areas[(int) $r->prefecture_id][] = ['id' => (int) $r->id, 'ja' => $ja];
        }

        return new self($prefs, $areas);
    }

    /** Normalise width and strip noise so matching is stable. */
    public static function normalise(string $address): string
    {
        $a = $address;
        if (function_exists('mb_convert_kana')) {
            // Full-width alphanumerics -> half-width; leave kana/kanji alone.
            $a = mb_convert_kana($a, 'a');
        }
        // Parenthesised asides ("（勤務地詳細は面接時に開示）") are not location data
        // we can match on, but they can contain place names, so we keep the string
        // intact and only collapse whitespace. Removing them is handled per-field.
        return trim(preg_replace('/\s+/u', '', $a) ?? $a);
    }

    /**
     * Resolve a prefecture from the address.
     *
     * @return array{id:?int,name:?string,decisive:bool,reason:string}
     */
    public function prefecture(string $address): array
    {
        $a = self::normalise($address);
        if ($a === '') {
            return ['id' => null, 'name' => null, 'decisive' => false, 'reason' => 'empty address'];
        }

        $hits = [];
        foreach (self::CANONICAL as $ja => $en) {
            $pos = mb_strpos($a, $ja);
            if ($pos !== false) {
                $hits[$ja] = ['pos' => $pos, 'en' => $en, 'len' => mb_strlen($ja)];
            }
        }

        if (!$hits) {
            return ['id' => null, 'name' => null, 'decisive' => false,
                    'reason' => 'no prefecture name in address'];
        }

        // A name at position 0 is the address prefix and wins outright.
        $prefix = array_filter($hits, fn($h) => $h['pos'] === 0);
        if (count($prefix) === 1) {
            $ja = array_key_first($prefix);
            return $this->resolveHit($ja, $prefix[$ja]['en'], true, 'address prefix');
        }
        if (count($prefix) > 1) {
            // Only possible via nesting; take the longest (e.g. 神奈川県 over any shorter).
            uasort($prefix, fn($x, $y) => $y['len'] <=> $x['len']);
            $ja = array_key_first($prefix);
            return $this->resolveHit($ja, $prefix[$ja]['en'], true, 'longest prefix match');
        }

        // Not at the start. One hit is still usable; several is ambiguous.
        if (count($hits) === 1) {
            $ja = array_key_first($hits);
            return $this->resolveHit($ja, $hits[$ja]['en'], true, 'single match mid-address');
        }

        return ['id' => null, 'name' => null, 'decisive' => false,
                'reason' => 'several prefectures named: ' . implode(', ', array_keys($hits))];
    }

    private function resolveHit(string $ja, string $en, bool $decisive, string $reason): array
    {
        $id = $this->prefIdByEnglish[mb_strtolower($en)] ?? null;
        if ($id === null) {
            return ['id' => null, 'name' => $ja, 'decisive' => false,
                    'reason' => "'{$en}' missing from prefectures table"];
        }
        return ['id' => $id, 'name' => $ja, 'decisive' => $decisive, 'reason' => $reason];
    }

    /**
     * Resolve an area within a prefecture. Longest `areas.japanese` contained in
     * the address wins -- that is what makes 横浜市中区 beat a bare 中区, and
     * 〇〇郡〇〇町 beat 〇〇町.
     *
     * @return array{id:?int,name:?string,decisive:bool,reason:string}
     */
    public function area(string $address, ?int $prefectureId): array
    {
        if ($prefectureId === null) {
            return ['id' => null, 'name' => null, 'decisive' => false, 'reason' => 'no prefecture'];
        }
        $candidates = $this->areasByPrefecture[$prefectureId] ?? [];
        if (!$candidates) {
            return ['id' => null, 'name' => null, 'decisive' => false,
                    'reason' => 'prefecture has no areas'];
        }

        $a = self::normalise($address);
        $best = [];
        $bestLen = 0;
        foreach ($candidates as $c) {
            $ja = self::normalise($c['ja']);
            if ($ja === '' || mb_strpos($a, $ja) === false) {
                continue;
            }
            $len = mb_strlen($ja);
            if ($len > $bestLen) {
                $bestLen = $len;
                $best = [$c];
            } elseif ($len === $bestLen) {
                $best[] = $c;
            }
        }

        if (!$best) {
            return ['id' => null, 'name' => null, 'decisive' => false,
                    'reason' => 'no area name in address'];
        }

        // Equal-length matches pointing at the same area are fine; genuinely
        // different areas of the same length are a tie we must not guess at.
        $ids = array_unique(array_column($best, 'id'));
        if (count($ids) > 1) {
            return ['id' => null, 'name' => null, 'decisive' => false,
                    'reason' => 'tie between areas: ' . implode(', ', array_column($best, 'ja'))];
        }

        return ['id' => $best[0]['id'], 'name' => $best[0]['ja'],
                'decisive' => true, 'reason' => "matched {$best[0]['ja']}"];
    }

    /**
     * Full verdict for one job.
     *
     * @return array{
     *   prefecture: array{id:?int,name:?string,decisive:bool,reason:string},
     *   area:       array{id:?int,name:?string,decisive:bool,reason:string}
     * }
     */
    /**
     * Feeds write a secondary coverage area after a slash:
     *   兵庫県加古郡稲美町/AGT明石エリア
     *   茨城県潮来市/AGT神栖エリア
     * The part before the slash is a complete, authoritative address. Judging
     * the whole string would trip the multi-location guard on the *suffix* and
     * throw away a perfectly good answer, so we evaluate the first segment when
     * it names a prefecture on its own.
     */
    public function primarySegment(string $address): string
    {
        $parts = preg_split('#[/／]#u', $address) ?: [$address];
        $first = trim($parts[0]);
        if (count($parts) > 1 && $first !== '') {
            foreach (self::CANONICAL as $ja => $_en) {
                if (mb_strpos(self::normalise($first), $ja) !== false) {
                    return $first;
                }
            }
        }
        return $address;
    }

    public function resolve(string $address): array
    {
        $address = $this->primarySegment($address);
        $p = $this->prefecture($address);
        $a = $this->area($address, $p['decisive'] ? $p['id'] : null);

        // A multi-location posting names a place but does not mean it. Keep the
        // derived values visible for the reviewer, but never treat them as
        // decisive -- the caller must fall back to the XML and flag for review.
        if ($hint = $this->multiLocationHint($address)) {
            $p['decisive'] = false;
            $p['reason'] = "multi-location address ('{$hint}') — not decisive";
            $a['decisive'] = false;
            $a['reason'] = "multi-location address ('{$hint}') — not decisive";
        }

        return ['prefecture' => $p, 'area' => $a];
    }

    /** First multi-location phrase present in the address, or null. */
    public function multiLocationHint(string $address): ?string
    {
        $a = self::normalise($address);
        foreach (self::MULTI_LOCATION_HINTS as $h) {
            if (mb_strpos($a, $h) !== false) {
                return $h;
            }
        }
        return null;
    }
}
