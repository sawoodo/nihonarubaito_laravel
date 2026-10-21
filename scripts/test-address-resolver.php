<?php
/**
 * Offline accuracy harness for AddressLocationResolver.
 * Reads TSVs pulled read-only from production; touches no database.
 *   php scripts/test-address-resolver.php <dataDir>
 */
require __DIR__ . '/../app/Services/AddressLocationResolver.php';
use App\Services\AddressLocationResolver;

$dir = $argv[1] ?? getenv('HOME') . '/nihon-linkcheck';

function tsv(string $f): array {
    $rows = []; $fh = fopen($f, 'r');
    $hdr = fgetcsv($fh, 0, "\t", chr(34), "");
    while (($r = fgetcsv($fh, 0, "\t", chr(34), "")) !== false) {
        if (count($r) === 1 && $r[0] === null) continue;
        $rows[] = array_combine($hdr, array_pad(array_slice($r, 0, count($hdr)), count($hdr), ''));
    }
    fclose($fh); return $rows;
}

$prefRows  = tsv("$dir/prefectures.tsv");
$areaRows  = tsv("$dir/areas.tsv");
$jobRows   = tsv("$dir/recent_jobs.tsv");

$prefIdByEnglish = [];
$prefEnglishById = [];
foreach ($prefRows as $p) {
    $prefIdByEnglish[mb_strtolower(trim($p['english']))] = (int) $p['id'];
    $prefEnglishById[(int) $p['id']] = trim($p['english']);
}
$areasByPref = []; $areaNameById = [];
foreach ($areaRows as $a) {
    $pid = (int) $a['prefecture_id']; $ja = trim($a['japanese']);
    $areaNameById[(int) $a['id']] = $ja . ' / ' . trim($a['english']);
    if ($ja !== '') $areasByPref[$pid][] = ['id' => (int) $a['id'], 'ja' => $ja];
}

$r = new AddressLocationResolver($prefIdByEnglish, $areasByPref);

// ---- self-test on the two documented substring traps ------------------
echo "=== TRAP CONTROLS (must pass or the matcher is unsound) ===\n";
$traps = [
    ['東京都新宿区西新宿2-8-1',      'Tokyo',    '京都 is a substring of 東京都'],
    ['京都府京都市下京区烏丸通',      'Kyoto',    'must not read as Tokyo'],
    ['神奈川県横浜市中区日本大通1',   'Kanagawa', 'ward 神奈川区 must not hijack'],
    ['東京都港区横浜町',             'Tokyo',    'prefix wins over stray place name'],
    ['北海道札幌市中央区北1条西2',    'Hokkaido', '道 suffix, 3-char name'],
    ['和歌山県和歌山市七番丁23',      'Wakayama', 'DB holds 若山 — canonical list must save us'],
    ['新潟県新潟市中央区',           'Niigata',  'DB holds 新潟市'],
    ['熊本県熊本市中央区',           'Kumamoto', 'DB holds 熊本市'],
];
$trapFail = 0;
foreach ($traps as [$addr, $want, $why]) {
    $got = $r->prefecture($addr);
    $gotEn = $got['id'] ? ($prefEnglishById[$got['id']] ?? '?') : '(none)';
    $ok = $gotEn === $want && $got['decisive'];
    if (!$ok) $trapFail++;
    printf("  [%s] %-28s -> %-10s (want %-10s) %s\n",
        $ok ? 'PASS' : 'FAIL', mb_strimwidth($addr, 0, 26, '…'), $gotEn, $want, $why);
}
echo $trapFail ? "  >>> $trapFail TRAP(S) FAILED\n" : "  >>> all traps pass\n";

// ---- accuracy against what the team finally saved ---------------------
echo "\n=== ACCURACY vs team-saved values (" . count($jobRows) . " recent jobs) ===\n";
$stat = ['pref_match'=>0,'pref_diff'=>0,'pref_vague'=>0,
         'area_match'=>0,'area_diff'=>0,'area_vague'=>0];
$prefDiffs = []; $areaDiffs = [];
$byPref = [];

foreach ($jobRows as $j) {
    $savedP = (int) $j['prefecture_id'];
    $savedA = (int) $j['area_id'];
    $v = $r->resolve($j['address']);
    $p = $v['prefecture']; $a = $v['area'];

    $pk = $prefEnglishById[$savedP] ?? "id$savedP";
    $byPref[$pk] ??= ['n'=>0,'ok'=>0,'diff'=>0,'vague'=>0];
    $byPref[$pk]['n']++;

    if (!$p['decisive'] || $p['id'] === null) {
        $stat['pref_vague']++; $byPref[$pk]['vague']++;
    } elseif ($p['id'] === $savedP) {
        $stat['pref_match']++; $byPref[$pk]['ok']++;
    } else {
        $stat['pref_diff']++; $byPref[$pk]['diff']++;
        $prefDiffs[] = [$j['job_no'], $j['job_status_id'], $j['address'],
            $prefEnglishById[$savedP] ?? "id$savedP",
            $prefEnglishById[$p['id']] ?? "id{$p['id']}", $p['reason']];
    }

    if (!$a['decisive'] || $a['id'] === null) {
        $stat['area_vague']++;
    } elseif ($a['id'] === $savedA) {
        $stat['area_match']++;
    } else {
        $stat['area_diff']++;
        $areaDiffs[] = [$j['job_no'], $j['job_status_id'], $j['address'],
            $areaNameById[$savedA] ?? "id$savedA",
            $areaNameById[$a['id']] ?? "id{$a['id']}", $a['reason']];
    }
}
$n = max(1, count($jobRows));
printf("  prefecture : match %3d (%5.1f%%)  differs %3d (%4.1f%%)  vague %3d (%4.1f%%)\n",
    $stat['pref_match'], 100*$stat['pref_match']/$n,
    $stat['pref_diff'],  100*$stat['pref_diff']/$n,
    $stat['pref_vague'], 100*$stat['pref_vague']/$n);
printf("  area       : match %3d (%5.1f%%)  differs %3d (%4.1f%%)  vague %3d (%4.1f%%)\n",
    $stat['area_match'], 100*$stat['area_match']/$n,
    $stat['area_diff'],  100*$stat['area_diff']/$n,
    $stat['area_vague'], 100*$stat['area_vague']/$n);
printf("  decisive-only prefecture accuracy: %5.1f%%  (%d of %d)\n",
    $stat['pref_match']+$stat['pref_diff'] ? 100*$stat['pref_match']/($stat['pref_match']+$stat['pref_diff']) : 0,
    $stat['pref_match'], $stat['pref_match']+$stat['pref_diff']);
printf("  decisive-only area accuracy      : %5.1f%%  (%d of %d)\n",
    $stat['area_match']+$stat['area_diff'] ? 100*$stat['area_match']/($stat['area_match']+$stat['area_diff']) : 0,
    $stat['area_match'], $stat['area_match']+$stat['area_diff']);

echo "\n--- per saved-prefecture (n>=3) ---\n";
uasort($byPref, fn($x,$y)=>$y['n']<=>$x['n']);
foreach ($byPref as $k=>$s) {
    if ($s['n'] < 3) continue;
    printf("  %-12s n=%-4d ok=%-4d differs=%-4d vague=%d\n", $k, $s['n'], $s['ok'], $s['diff'], $s['vague']);
}

echo "\n=== PREFECTURE DISAGREEMENTS (" . count($prefDiffs) . ") ===\n";
foreach ($prefDiffs as $d) {
    printf("  job %-8s st%-2s saved=%-11s derived=%-11s  %s\n", $d[0],$d[1],$d[3],$d[4],$d[5]);
    printf("      %s\n", $d[2]);
}
echo "\n=== AREA DISAGREEMENTS (first 25 of " . count($areaDiffs) . ") ===\n";
foreach (array_slice($areaDiffs,0,25) as $d) {
    printf("  job %-8s st%-2s\n      saved   : %s\n      derived : %s\n      address : %s\n", $d[0],$d[1],$d[3],$d[4],$d[2]);
}
