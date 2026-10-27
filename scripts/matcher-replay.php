#!/usr/bin/env php
<?php

/**
 * matcher-replay.php -- Gate 3 of docs/specs/import-duplicate-check.md.
 *
 * Replays App\Services\JobMatcher over every job created in the last N days:
 * how many would have shown a card at the moment they were imported, and how
 * that compares with /check-translator-duplicates over the same window.
 *
 * READ-ONLY and offline. It reads the same two TSV exports that
 * /check-translator-duplicates uses, so the two numbers are measured on one
 * snapshot and are directly comparable. No database, no Laravel.
 *
 * It mirrors JobMatcher::findMatches()'s candidate selection in memory:
 *   1. same normalised apply link or source listing id, ANY status
 *   2. same prefecture and category, live (2,3,6) or retired (4,5) within 60 days
 * and then, as at the import moment, considers only jobs that already existed
 * when the replayed job was created -- a job cannot duplicate one created after
 * it. Production scans the whole jobs table for (1); pool.tsv stops at jobs
 * created within ~400 days, so a listing last seen longer ago than that is the
 * one thing this replay can miss.
 *
 *   php scripts/matcher-replay.php --recent R.tsv --pool P.tsv [--unique-csv C.csv]
 */

require __DIR__ . '/../app/Services/JobMatcher.php';

use App\Services\JobMatcher as M;

// NOTE: long options declared with '::' (optional value) must be passed as
// --unique-csv=PATH. With a space, getopt silently returns nothing and the
// whole control section is skipped without an error.
$opts = getopt('', ['recent:', 'pool:', 'unique-csv::', 'samples::', 'job::']);
if (! isset($opts['recent'], $opts['pool'])) {
    fwrite(STDERR, "usage: matcher-replay.php --recent R.tsv --pool P.tsv [--unique-csv C.csv]\n");
    exit(2);
}
$sampleSeed = (int) ($opts['samples'] ?? 1027);

const LIVE = [2, 3, 6];
const RETIRED = [4, 5];
const STATUS_LABEL = [1 => 'Draft', 2 => 'Pending', 3 => 'Published',
    4 => 'Expired', 5 => 'Trashed', 6 => 'Quota full'];

/** @return array<int,array<string,string>> */
function read_tsv(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        fwrite(STDERR, "cannot read {$path}\n");
        exit(2);
    }
    $lines = explode("\n", str_replace("\r", '', $raw));
    $head = explode("\t", $lines[0]);
    $rows = [];
    foreach (array_slice($lines, 1) as $i => $line) {
        if ($line === '') {
            continue;
        }
        $cells = explode("\t", $line);
        if (count($cells) !== count($head)) {
            fwrite(STDERR, sprintf("%s: line %d has %d fields, expected %d — export is malformed\n",
                $path, $i + 2, count($cells), count($head)));
            exit(2);
        }
        $rows[] = array_combine($head, array_map(
            fn ($v) => $v === 'NULL' ? '' : str_replace(['\\n', '\\t', '\\r', '\\\\'], ["\n", "\t", "\r", '\\'], $v),
            $cells));
    }

    return $rows;
}

function ts(?string $v): ?int
{
    $v = trim((string) $v);
    if ($v === '' || str_starts_with($v, '0000')) {
        return null;
    }
    $t = strtotime($v);

    return $t === false ? null : $t;
}

$recent = array_map([M::class, 'prepare'], read_tsv($opts['recent']));
$pool = array_map([M::class, 'prepare'], read_tsv($opts['pool']));

printf("recent : %s  (%d jobs)\n", $opts['recent'], count($recent));
printf("pool   : %s  (%d jobs)\n\n", $opts['pool'], count($pool));

// ── index the pool the way findMatches queries it ───────────────────────
$byLink = $bySid = $byPrefCat = [];
$now = time();
foreach ($pool as $i => $j) {
    if ($j['_link'] !== '') {
        $byLink[$j['_link']][] = $i;
    }
    if ($j['_sid'] !== null) {
        $bySid[$j['_sid']][] = $i;
    }
    $status = (int) $j['job_status_id'];
    $inWindow = in_array($status, LIVE, true)
        || (in_array($status, RETIRED, true)
            && ($r = ts($j['Expire_Date'] ?: $j['updated_at'])) !== null
            && $r >= $now - M::REVIVE_WINDOW_DAYS * 86400);
    if ($inWindow) {
        $byPrefCat[$j['prefecture_id'] . '|' . $j['job_category_id']][] = $i;
    }
}

// ── replay ──────────────────────────────────────────────────────────────
$results = [];
foreach ($recent as $a) {
    $createdAt = ts($a['date']);
    $cand = [];
    $linkSet = $a['_link'] !== '' ? ($byLink[$a['_link']] ?? []) : [];
    $sidSet = $a['_sid'] !== null ? ($bySid[$a['_sid']] ?? []) : [];
    $poolSet = $byPrefCat[$a['prefecture_id'] . '|' . $a['job_category_id']] ?? [];
    foreach ([$linkSet, $sidSet, $poolSet] as $set) {
        foreach ($set as $i) {
            $cand[$i] = true;
        }
    }

    $best = null;
    $matches = [];
    foreach (array_keys($cand) as $i) {
        $b = $pool[$i];
        if ((string) $b['job_no'] === (string) $a['job_no']) {
            continue;
        }
        // At the import moment only jobs that already existed can be the original.
        $bAt = ts($b['date']);
        if ($createdAt !== null && $bAt !== null && $bAt > $createdAt) {
            continue;
        }

        $listing = M::sameListing($a, $b);
        $sameCompany = $a['_co'] !== '' && $a['_co'] === $b['_co'];
        if ($listing !== true && ! $sameCompany) {
            continue;
        }
        $v = M::judge($a, $b);
        if ($v === null) {
            continue;
        }
        if ($listing === true && ! $sameCompany) {
            $v = 'review';          // strong, not proof — never one-click
        }
        $matches[] = ['job' => $b, 'verdict' => $v,
            'reason' => M::reason($a, $b, $listing, $sameCompany)];
        if ($v === 'certain') {
            $best = 'certain';
        } elseif ($best === null) {
            $best = 'review';
        }
    }

    $results[(string) $a['job_no']] = ['job' => $a, 'verdict' => $best ?? 'none', 'matches' => $matches];
}

// ── one job in full, for a hand check ───────────────────────────────────
if (! empty($opts['job'])) {
    $jn = (string) $opts['job'];
    if (! isset($results[$jn])) {
        fwrite(STDERR, "job {$jn} is not in this window\n");
        exit(1);
    }
    $r = $results[$jn];
    printf("job %s — verdict %s, %d match(es)\n", $jn, $r['verdict'], count($r['matches']));
    printf("  new : %s\n       %s | %s | %s\n\n", $r['job']['title'],
        $r['job']['company_name'], $r['job']['_addr'] ?: '(no address key)', $r['job']['wage']);
    foreach ($r['matches'] as $m) {
        printf("  [%s] #%s %s — %s\n", $m['verdict'], $m['job']['job_no'],
            STATUS_LABEL[(int) $m['job']['job_status_id']] ?? '?', $m['reason']);
        printf("       %s\n       %s | %s | %s\n", $m['job']['title'],
            $m['job']['company_name'], $m['job']['_addr'] ?: '(no address key)', $m['job']['wage']);
    }
    exit(0);
}

// ── headline counts ─────────────────────────────────────────────────────
$counts = ['certain' => 0, 'review' => 0, 'none' => 0];
foreach ($results as $r) {
    $counts[$r['verdict']]++;
}
$n = count($results);
echo "would have shown a card at import time:\n";
printf("  CERTAIN  (blocks, needs a typed reason)  %4d   %5.1f%%\n", $counts['certain'], 100 * $counts['certain'] / max($n, 1));
printf("  REVIEW   (notice only, creation allowed) %4d   %5.1f%%\n", $counts['review'], 100 * $counts['review'] / max($n, 1));
printf("  no card                                  %4d   %5.1f%%\n", $counts['none'], 100 * $counts['none'] / max($n, 1));

// certain, broken down by what the existing job's status was
$byStatus = [];
foreach ($results as $r) {
    if ($r['verdict'] !== 'certain') {
        continue;
    }
    foreach ($r['matches'] as $m) {
        if ($m['verdict'] === 'certain') {
            $s = (int) $m['job']['job_status_id'];
            $byStatus[STATUS_LABEL[$s] ?? $s] = ($byStatus[STATUS_LABEL[$s] ?? $s] ?? 0) + 1;
            break;
        }
    }
}
echo "\nthe job each CERTAIN card points at:\n";
arsort($byStatus);
foreach ($byStatus as $label => $c) {
    printf("  %-12s %4d\n", $label, $c);
}

$byReason = [];
foreach ($results as $r) {
    foreach ($r['matches'] as $m) {
        if ($m['verdict'] === $r['verdict']) {
            $byReason[$r['verdict'] . ' / ' . $m['reason']] = ($byReason[$r['verdict'] . ' / ' . $m['reason']] ?? 0) + 1;
            break;
        }
    }
}
echo "\nmatched on:\n";
arsort($byReason);
foreach ($byReason as $label => $c) {
    printf("  %-46s %4d\n", $label, $c);
}

// ── samples ─────────────────────────────────────────────────────────────
function show(array $r, array $m): void
{
    $a = $r['job'];
    $b = $m['job'];
    printf("  %s  ->  %s   [%s, %s]\n", $a['job_no'], $b['job_no'],
        STATUS_LABEL[(int) $b['job_status_id']] ?? '?', $m['reason']);
    printf("     new : %s\n", mb_substr($a['title'], 0, 86));
    printf("     have: %s\n", mb_substr($b['title'], 0, 86));
    printf("     %s | %s | %s\n", $a['company_name'], $a['_addr'] ?: '(no address key)', $a['wage']);
}

mt_srand($sampleSeed);
$certainJobs = array_keys(array_filter($results, fn ($r) => $r['verdict'] === 'certain'));
shuffle($certainJobs);
echo "\n10 random CERTAIN cases:\n";
foreach (array_slice($certainJobs, 0, 10) as $jn) {
    $r = $results[$jn];
    foreach ($r['matches'] as $m) {
        if ($m['verdict'] === 'certain') {
            show($r, $m);
            break;
        }
    }
}

$reviewJobs = array_keys(array_filter($results, fn ($r) => $r['verdict'] === 'review'));
shuffle($reviewJobs);
echo "\n5 REVIEW-only cases:\n";
foreach (array_slice($reviewJobs, 0, 5) as $jn) {
    $r = $results[$jn];
    show($r, $r['matches'][0]);
}

// ── control: jobs the old check called UNIQUE must get no CERTAIN card ──
if (! empty($opts['unique-csv'])) {
    $fh = fopen($opts['unique-csv'], 'r');
    $head = fgetcsv($fh);
    // The report CSV is written with a BOM for Excel; strip it or the first
    // column's key becomes "\xEF\xBB\xBFjob_no" and every lookup misses.
    if (isset($head[0])) {
        $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
    }
    $uniq = [];
    while (($row = fgetcsv($fh)) !== false) {
        $r = array_combine($head, $row);
        if (($r['verdict'] ?? '') === 'UNIQUE') {
            $uniq[] = $r['job_no'];
        }
    }
    fclose($fh);

    // newest first = highest job_no among the UNIQUE rows
    usort($uniq, fn ($x, $y) => (int) $y <=> (int) $x);
    $check = array_slice($uniq, 0, 10);

    echo "\ncontrol — the 10 newest jobs /check-translator-duplicates called UNIQUE:\n";
    $bad = 0;
    foreach ($check as $jn) {
        $v = $results[$jn]['verdict'] ?? '(not in window)';
        if ($v === 'certain') {
            $bad++;
        }
        printf("  %-9s %s\n", $jn, $v);
    }
    printf("  => %d of %d got a CERTAIN card%s\n", $bad, count($check),
        $bad === 0 ? ' — as expected' : '  *** LOOK AT THESE ***');
}
