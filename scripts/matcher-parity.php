#!/usr/bin/env php
<?php

/**
 * matcher-parity.php -- the PHP half of the JobMatcher parity test.
 *
 * Plain PHP: it requires App\Services\JobMatcher directly and never boots
 * Laravel, so it exercises the normalisers and judge() with no database and no
 * framework in the way. It reproduces exactly the pairs scripts/dedupe-plan.py
 * compares and writes the same TSV as scripts/matcher-parity.py.
 *
 *   php scripts/matcher-parity.php --published P.tsv --out pairs-php.tsv
 *   diff pairs-python.tsv pairs-php.tsv      # must be empty
 *
 * It also asserts the named cases from docs/specs/import-duplicate-check.md,
 * the ones four review passes were spent getting right. A parity diff of zero
 * with those assertions failing would mean both sides are wrong together.
 */

require __DIR__ . '/../app/Services/JobMatcher.php';

use App\Services\JobMatcher as M;

// ── arguments ───────────────────────────────────────────────────────────

$opts = getopt('', ['published:', 'out:']);
if (! isset($opts['published'], $opts['out'])) {
    fwrite(STDERR, "usage: matcher-parity.php --published P.tsv --out pairs-php.tsv\n");
    exit(2);
}

// ── reading mysql --batch output, exactly as translator-dup-check.read_tsv ──

/** @return array<int,array<string,string>> */
function read_tsv(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        fwrite(STDERR, "cannot read {$path}\n");
        exit(2);
    }
    // mysql --batch escapes \n, \t and \\ but NOT \r; a raw CR would split a row.
    $lines = explode("\n", str_replace("\r", '', $raw));
    $head = explode("\t", $lines[0]);
    $rows = [];
    foreach (array_slice($lines, 1) as $i => $line) {
        if ($line === '') {
            continue;
        }
        $cells = explode("\t", $line);
        if (count($cells) !== count($head)) {
            $n = $i + 2;
            fwrite(STDERR, sprintf("%s: line %d has %d fields, expected %d — export is malformed, nothing analysed\n",
                $path, $n, count($cells), count($head)));
            exit(2);
        }
        $rows[] = array_combine($head, array_map('unescape_cell', $cells));
    }

    return $rows;
}

function unescape_cell(string $v): string
{
    if ($v === 'NULL') {
        return '';
    }

    return str_replace(['\\n', '\\t', '\\r', '\\\\'], ["\n", "\t", "\r", '\\'], $v);
}

// ── prepare, exactly as dedupe-plan.main ────────────────────────────────

$rows = array_values(array_filter(read_tsv($opts['published']), fn ($r) => $r['job_status_id'] === '3'));
foreach ($rows as &$r) {
    $r = M::prepare($r);
}
unset($r);

$byCo = [];
foreach ($rows as $r) {
    if ($r['_co'] !== '') {
        $byCo[$r['_co']][] = $r;
    }
}

// ── the planner's buckets and pairs ─────────────────────────────────────

$pairs = [];                                     // "a\x01b" => verdict
foreach ($byCo as $js) {
    if (count($js) < 2) {
        continue;
    }

    $buckets = [];
    foreach ($js as $i => $j) {
        $keys = [];
        if (M::specific($j['_addr'])) {
            $keys['a|' . $j['_addr']] = true;
        }
        if ($j['_st'] !== '') {
            $keys['s|' . $j['_st']] = true;
        }
        $listingKey = ($j['_sid'] ?? '') !== '' ? $j['_sid'] : $j['_link'];
        if ($listingKey !== '' && $listingKey !== null) {
            $keys['l|' . $listingKey] = true;
        }
        foreach (array_keys($keys) as $k) {
            $buckets[$k][] = $i;
        }
    }

    foreach ($buckets as $members) {
        $n = count($members);
        for ($i = 0; $i < $n; $i++) {
            for ($k = $i + 1; $k < $n; $k++) {
                $x = $js[$members[$i]];
                $y = $js[$members[$k]];
                $a = (string) $x['job_no'];
                $b = (string) $y['job_no'];
                if (strcmp($a, $b) > 0) {
                    [$a, $b] = [$b, $a];
                }
                $key = $a . "\x01" . $b;
                if (isset($pairs[$key])) {
                    continue;
                }
                $pairs[$key] = M::judge($x, $y) ?? 'none';
            }
        }
    }
}

// Python sorts tuples of job_no STRINGS: compare first, then second.
$list = [];
foreach ($pairs as $key => $verdict) {
    [$a, $b] = explode("\x01", $key);
    $list[] = [$a, $b, $verdict];
}
usort($list, function ($p, $q) {
    $c = strcmp($p[0], $q[0]);

    return $c !== 0 ? $c : strcmp($p[1], $q[1]);
});

$fh = fopen($opts['out'], 'w');
fwrite($fh, "job_a\tjob_b\tverdict\n");
$counts = [];
foreach ($list as [$a, $b, $v]) {
    fwrite($fh, "{$a}\t{$b}\t{$v}\n");
    $counts[$v] = ($counts[$v] ?? 0) + 1;
}
fclose($fh);

printf("published rows : %d\n", count($rows));
printf("companies       : %d\n", count($byCo));
printf("pairs compared  : %d\n", count($list));
ksort($counts);
foreach ($counts as $v => $n) {
    printf("  %-8s %d\n", $v, $n);
}
printf("written         : %s\n", $opts['out']);

// ── the named cases from the spec ───────────────────────────────────────

$byNo = [];
foreach ($rows as $r) {
    $byNo[(string) $r['job_no']] = $r;
}

$failures = [];

/** @param string $want 'certain', 'review' or 'none' */
function expect_pair(array $byNo, string $a, string $b, string $want, string $label, array &$failures): void
{
    if (! isset($byNo[$a], $byNo[$b])) {
        $failures[] = "{$label}: job {$a} or {$b} not in the export";

        return;
    }
    $got = M::judge($byNo[$a], $byNo[$b]) ?? 'none';
    $mark = $got === $want ? 'PASS' : 'FAIL';
    printf("  %-4s %-46s %s %s (want %s)\n", $mark, $label, "{$a}/{$b}", $got, $want);
    if ($got !== $want) {
        $failures[] = "{$label}: {$a}/{$b} gave '{$got}', expected '{$want}'";
    }
}

echo "\nnamed cases:\n";
expect_pair($byNo, '162280', '163986', 'certain', 'Dimple izakaya pair', $failures);
expect_pair($byNo, '141386', '158167', 'certain', 'FamilyMart kanji 丁目 pair', $failures);

// Hotel Emion: six real vacancies at one address and wage, distinct offer ids.
$emion = array_values(array_filter($rows, fn ($r) => str_contains($r['company_name'], 'エミオン')
    || str_contains($r['address'], 'ホテルエミオン')));
$emionCertain = [];
for ($i = 0; $i < count($emion); $i++) {
    for ($k = $i + 1; $k < count($emion); $k++) {
        if (M::judge($emion[$i], $emion[$k]) === 'certain') {
            $emionCertain[] = $emion[$i]['job_no'] . '/' . $emion[$k]['job_no'];
        }
    }
}
printf("  %-4s %-46s %d of %d jobs, %d certain pairs\n",
    $emionCertain ? 'FAIL' : 'PASS', 'Hotel Emion: no pair may be certain',
    count($emion), count($emion), count($emionCertain));
if ($emionCertain) {
    $failures[] = 'Hotel Emion: certain pairs ' . implode(', ', $emionCertain);
}

// Higashi-Osaka: 荒本新町 and 菱江 share 荒本駅 but are different towns.
$arahon = array_values(array_filter($rows, fn ($r) => str_contains($r['address'], '東大阪市荒本新町')));
$hishie = array_values(array_filter($rows, fn ($r) => str_contains($r['address'], '東大阪市菱江')));
$crossTown = [];
foreach ($arahon as $x) {
    foreach ($hishie as $y) {
        if (M::judge($x, $y) === 'certain') {
            $crossTown[] = $x['job_no'] . '/' . $y['job_no'];
        }
    }
}
printf("  %-4s %-46s %d x %d jobs, %d certain pairs\n",
    $crossTown ? 'FAIL' : 'PASS', 'Higashi-Osaka 荒本新町 vs 菱江: never certain',
    count($arahon), count($hishie), count($crossTown));
if ($crossTown) {
    $failures[] = 'Higashi-Osaka cross-town certain pairs: ' . implode(', ', $crossTown);
}

// Dimple: one agency office address, many unrelated placements. The pair the
// spec names is 'Family Restaurant Cashier' against 'Shrimp Cracker Shop Sales
// Staff' -- same company, same office address, different jobs entirely.
$dimple = array_values(array_filter($rows, fn ($r) => str_contains($r['company_name'], 'ディンプル')));
$dimpleNamed = [];
foreach ($dimple as $x) {
    if (! str_contains($x['title'], 'Family Restaurant') || ! str_contains($x['title'], 'Cashier')) {
        continue;
    }
    foreach ($dimple as $y) {
        if (! str_contains($y['title'], 'Cracker')) {
            continue;
        }
        if (M::judge($x, $y) === 'certain') {
            $dimpleNamed[] = $x['job_no'] . '/' . $y['job_no'];
        }
    }
}
printf("  %-4s %-46s %d pairs checked, %d certain\n",
    $dimpleNamed ? 'FAIL' : 'PASS', 'Dimple: restaurant cashier vs cracker shop',
    count($dimple), count($dimpleNamed));
if ($dimpleNamed) {
    $failures[] = 'Dimple named pair certain: ' . implode(', ', $dimpleNamed);
}

// And the general form of that mistake: across the whole agency, no pair of
// DIFFERENT source listings may be certain on a weak role match. The lower
// SAME_LISTING_ROLE_MIN bar is deliberate and only applies when the source
// itself says the two jobs are one listing ('Daily Payment Cafe Kitchen Staff'
// and 'Cafe Kitchen Staff' on one offer id are the same job).
$dimpleBad = [];
foreach ($dimple as $x) {
    foreach ($dimple as $y) {
        if (strcmp((string) $x['job_no'], (string) $y['job_no']) >= 0) {
            continue;
        }
        if (M::judge($x, $y) !== 'certain' || M::sameListing($x, $y) === true) {
            continue;
        }
        if (M::titleSim($x['title'], $y['title']) < M::TITLE_MIN) {
            $dimpleBad[] = $x['job_no'] . '/' . $y['job_no'];
        }
    }
}
printf("  %-4s %-46s %d jobs, %d weak-role certain across listings\n",
    $dimpleBad ? 'FAIL' : 'PASS', 'Dimple office: no cross-listing weak-role certain',
    count($dimple), count($dimpleBad));
if ($dimpleBad) {
    $failures[] = 'Dimple cross-listing weak-role certain: ' . implode(', ', $dimpleBad);
}

if ($failures) {
    echo "\nFAILED:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

echo "\nall named cases pass.\n";
