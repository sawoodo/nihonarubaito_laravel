#!/usr/bin/env php
<?php

/**
 * matcher-target-tests.php -- tests for MovedTargetMatcher's relink decisions.
 *
 * Plain PHP: it requires the class directly and never boots Laravel, so it runs
 * with no database and no framework in the way (same pattern as
 * scripts/matcher-parity.php).
 *
 *   php scripts/matcher-target-tests.php [--corpus addresses.tsv]
 *
 * Three parts:
 *   1. the place names a 府 city must yield (利府, 府中, 甲府, 別府, 防府)
 *   2. the three rows the page refused on 2026-10-30, and a control it accepted
 *   3. PARITY over a corpus of real addresses: places() must return a SUPERSET
 *      of what it returned before, so no row that matched can stop matching
 */

require __DIR__ . '/../app/Services/MovedTargetMatcher.php';

use App\Services\MovedTargetMatcher;

$m = new MovedTargetMatcher();
$pass = $fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        printf("ok   %s%s\n", $label, $detail !== '' ? "  ({$detail})" : '');
    } else {
        $fail++;
        printf("FAIL %s%s\n", $label, $detail !== '' ? "  ({$detail})" : '');
    }
}

// ── 1. 府 is a city character, not only a prefecture suffix ───────────────
echo "── places(): a locality containing 府 ──────────────────────────────\n";
$placeCases = [
    ['宮城県宮城郡利府町利府字新神明前75番地1', '利府', 'ホテルルートイン利府'],
    ['東京都府中市宮西町2-24',                 '府中', 'セブン-イレブン府中宮西町店'],
    ['山梨県甲府市丸の内1-1-8',                 '甲府', 'ファミリーマート甲府丸の内店'],
    ['大分県別府市北浜2-9-1',                   '別府', 'ローソン別府北浜店'],
    ['山口県防府市栄町1-5-1',                   '防府', 'スーパー防府栄町店'],
    // prefectures that END in 府 must still be stripped, not treated as cities
    ['大阪府大阪市中央区難波3-1',               '大阪', 'タイムズサービス 大阪市中央区難波'],
    ['京都府京都市南区久世高田町',              '京都', '丸亀製麺イオンモール京都桂川店'],
    // and the control that already worked
    ['宮城県名取市堀内字北竹345-1',             '名取', 'ホテルルートイン名取岩沼インター'],
];
foreach ($placeCases as [$addr, $want, $title]) {
    $places = MovedTargetMatcher::places($addr);
    check("places('{$addr}') yields '{$want}'", in_array($want, $places, true),
          implode(' | ', array_slice($places, 0, 5)));
    $hit = false;
    foreach ($places as $p) {
        if (mb_strpos($title, $p) !== false) { $hit = true; break; }
    }
    check("  and matches the page title", $hit, mb_substr($title, 0, 30));
}

// ── 2. the rows the page refused, and one it accepted ─────────────────────
echo "\n── the three WRONG_TARGET rows of 2026-10-30 ───────────────────────\n";
$rows = [
    ['155158', '株式会社日本ワークプレイス NWP205', '茨城県稲敷郡美浦村',
     'https://sftworks.jp/entry/input?work_id=W015911313',
     'nwph_NWP205株式会社日本ワークプレイス NWP205の派遣社員の求人情報｜シフトワークス(W015911313)',
     true, 'company named later in the title'],
    ['158220', 'ルートインジャパン株式会社', '宮城県宮城郡利府町利府字新神明前75番地1',
     'https://sftworks.jp/entry/input?work_id=W015913648',
     'ホテルルートイン利府のアルバイト・パートの求人情報｜シフトワークス(W015913648)',
     true, 'place 利府'],
    ['159998', '日本ピザハット株式会社', '茨城県つくばみらい市小絹703',
     'https://sftworks.jp/entry/input?work_id=W015916737',
     'ピザハット谷和原店のアルバイト・パートの求人情報｜シフトワークス(W015916737)',
     true, 'sftworks listing id only — no company or place match'],
    ['157512', 'しゃぶしゃぶどん亭　つくば研究学園店', '茨城県つくば市研究学園4-2-10',
     'https://sftworks.jp/entry/input?work_id=W015799846',
     'しゃぶしゃぶどん亭 つくば研究学園店 (１２２６)のアルバイト・パートの求人情報｜シフトワークス(W015799846)',
     true, 'control: already accepted by company before this change'],
];
foreach ($rows as [$jobNo, $co, $addr, $final, $title, $want, $why]) {
    $byTitle = $m->matches($co, $addr, $title);
    $bySft   = $m->sameSftworksListing($final, $title);
    $got = $byTitle['same'] || $bySft['same'];
    $by  = $byTitle['same'] ? ('title/' . $byTitle['by']) : ($bySft['same'] ? 'sftworks_id' : 'none');
    check("job {$jobNo} accepted ({$why})", $got === $want, "by={$by}");
}

// ── the host scope of the sftworks rule ───────────────────────────────────
echo "\n── sameSftworksListing() must not fire for shigotoin ───────────────\n";
// shigotoin prints the destination's own id too, so a host-agnostic rule would
// auto-accept the wrong-target rows measured on 2026-10-30.
$shig = $m->sameSftworksListing(
    'https://shigotoin.com/offer/121487695',
    '株式会社BREXA Next ≪月収24万円・派遣社員≫クレーン・フォークリフト【121487695】の求人情報 | シゴトin');
check('shigotoin redirect is NOT accepted by the sftworks rule', ! $shig['same'],
      $shig['reason']);
$wrongId = $m->sameSftworksListing(
    'https://sftworks.jp/entry/input?work_id=W015911313',
    'よその求人｜シフトワークス(W099999999)');
check('a sftworks title printing a different id is refused', ! $wrongId['same'],
      $wrongId['reason']);

// ── 3. parity: places() must never lose a name ────────────────────────────
$corpus = null;
foreach ($argv as $i => $a) {
    if ($a === '--corpus' && isset($argv[$i + 1])) {
        $corpus = $argv[$i + 1];
    }
}
if ($corpus !== null && is_readable($corpus)) {
    echo "\n── parity over real addresses ──────────────────────────────────────\n";
    // what places() returned BEFORE this change, reproduced exactly
    $old = static function (string $address): array {
        $a = MovedTargetMatcher::fold($address);
        $out = [];
        if (preg_match_all('/([^\s都道府県市区町村郡]{1,6})([市区町村])/u', $a, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $hit) { $out[] = $hit[1]; $out[] = $hit[1] . $hit[2]; }
        }
        if (preg_match_all('/([^\s]{2,4}?)[都道府県]/u', $a, $mm)) {
            foreach ($mm[1] as $hit) { $out[] = $hit; }
        }
        if (preg_match_all('/(イオンモール[^\s0-9]{0,6}|[^\s0-9]{2,8}(?:モール|プラザ|ビル|店))/u', $a, $mm)) {
            foreach ($mm[1] as $hit) { $out[] = $hit; }
        }
        return array_values(array_unique(array_filter($out, fn ($p) => mb_strlen($p) >= 2)));
    };

    // matches() as it behaved BEFORE this change: old places(), and the
    // company compared only against pageCompany()'s head.
    $oldMatches = static function (?string $co, ?string $addr, ?string $title) use ($old): bool {
        $page = trim((string) $title);
        if ($page === '') { return false; }
        $ourCo  = MovedTargetMatcher::normalizeCompany((string) $co);
        $pageCo = MovedTargetMatcher::normalizeCompany(MovedTargetMatcher::pageCompany($page));
        if ($ourCo !== '' && $pageCo !== ''
            && (str_contains($pageCo, $ourCo) || str_contains($ourCo, $pageCo))) {
            return true;
        }
        foreach ($old((string) $addr) as $p) {
            if (mb_strpos($page, $p) !== false) { return true; }
        }
        $shared = MovedTargetMatcher::longestCommon(
            MovedTargetMatcher::fold((string) $addr), MovedTargetMatcher::fold($page));
        return mb_strlen($shared) >= 4;
    };

    // company_name + address per job
    $jobs = [];
    $fh = fopen($corpus, 'r');
    fgets($fh);
    while (($line = fgets($fh)) !== false) {
        $c = explode("\t", rtrim($line, "\r\n"));
        if (count($c) >= 3) { $jobs[$c[0]] = ['co' => $c[1], 'addr' => $c[2]]; }
    }
    fclose($fh);

    // places(): informational only. The fix REMOVES junk names such as
    // 市中央区, 野区大阪, '-1-1' and 'を中心に', so a strict superset is not
    // the goal; the goal is that no real pair stops matching, tested below.
    $n = $lost = $gained = 0;
    foreach ($jobs as $j) {
        if ($j['addr'] === '' || $j['addr'] === 'NULL') { continue; }
        $n++;
        $before = $old($j['addr']);
        $after  = MovedTargetMatcher::places($j['addr']);
        if (array_diff($before, $after)) { $lost++; }
        if (array_diff($after, $before)) { $gained++; }
    }
    printf("addresses compared            : %d\n", $n);
    printf("  gained a name               : %d\n", $gained);
    printf("  dropped a name (mostly junk): %d\n", $lost);

    // THE parity test: real (company, address, destination title) triples from
    // today's five scans. A row that matched before must still match.
    $triples = 0; $flipToNo = []; $flipToSame = 0;
    $resultDir = getenv('HOME') . '/nihon-linkcheck/daily-20261030';
    foreach (glob("{$resultDir}/results-*.csv") as $path) {
        $rows = array_map(
            fn (string $l): array => str_getcsv($l, ',', '"', '\\'),
            file($path, FILE_IGNORE_NEW_LINES));
        $head = array_shift($rows);
        $iJob = array_search('job_no', $head, true);
        $iTitle = array_search('source_title', $head, true);
        if ($iJob === false || $iTitle === false) { continue; }
        foreach ($rows as $r) {
            $jobNo = $r[$iJob] ?? '';
            $title = $r[$iTitle] ?? '';
            if ($title === '' || ! isset($jobs[$jobNo])) { continue; }
            $triples++;
            $was = $oldMatches($jobs[$jobNo]['co'], $jobs[$jobNo]['addr'], $title);
            $now = $m->matches($jobs[$jobNo]['co'], $jobs[$jobNo]['addr'], $title)['same'];
            if ($was && ! $now) { $flipToNo[] = $jobNo; }
            if (! $was && $now) { $flipToSame++; }
        }
    }
    printf("\nreal (company, address, title) triples: %d\n", $triples);
    printf("  NO   -> SAME (newly verifiable)      : %d\n", $flipToSame);
    printf("  SAME -> NO   (regressions)           : %d  %s\n",
        count($flipToNo), implode(' ', array_slice($flipToNo, 0, 8)));
    check('no row that matched before stops matching', $flipToNo === [],
          count($flipToNo) . " regression(s) of {$triples}");
} else {
    echo "\n(skipping the corpus parity check — pass --corpus <addresses.tsv>)\n";
}

printf("\n%d passed, %d failed\n", $pass, $fail);
printf("%s\n", $fail === 0 ? 'TESTS PASS' : 'TESTS FAIL');
exit($fail === 0 ? 0 : 1);
