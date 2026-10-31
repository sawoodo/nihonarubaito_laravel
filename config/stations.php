<?php

// The 40 station pages in the sitemap. Hand-written: a page lists a job only if
// its station field names this station, in this prefecture. Slugs not listed
// here keep the plain text search.
//
//   english      spellings as they appear before " Station". See App\Support\StationPattern
//                for what may not come before them. A spelling that starts with
//                a company (Osaka-Namba) is listed because the hyphen would
//                otherwise reject it.
//   kanji        full names as they appear in the station field, with 駅.
//   prefectures  ids. Two only where one name is two real places.
//   areas        optional, for the "Explore Nearby" block: area page slug => name,
//                from the areas holding 3+ of the station's jobs (2026-10-31).
//                Check the URL first: Kashiwa, Oyama and Gifu have no working
//                area page (the first two redirect to another prefecture).
//   nearby       optional, up to 5 station slugs, nearest first. A station with
//                no list shows no block, so the rollout goes page by page.
//
// A different station with a similar name (Shin-Osaka, Nishi-Funabashi,
// Tobu-Utsunomiya) stays out on purpose: it has its own place in the listing.

return [
    'namba' => [
        'english' => ['Namba', 'Nanba', 'Osaka-Namba', 'Nankai-Namba', 'Kintetsu-Namba', 'JR-Namba'],
        'kanji' => ['難波駅', 'なんば駅', '大阪難波駅', '近鉄難波駅', '南海難波駅', '南海なんば駅', '地下鉄なんば駅'],
        'prefectures' => [3],
        'areas' => ['chuo-ku-osaka' => 'Chuo-ku Osaka', 'naniwa-ku-osaka-shi' => 'Naniwa-ku Osaka'],
        'nearby' => ['shinsaibashi', 'hommachi', 'yodoyabashi', 'tennoji', 'umeda'],
    ],
    'umeda' => [
        'english' => ['Umeda', 'Osaka-Umeda', 'Hankyu-Umeda', 'Hanshin-Umeda'],
        'kanji' => ['梅田駅', '大阪梅田駅', '阪急梅田駅', '阪神梅田駅', '地下鉄梅田駅'],
        'prefectures' => [3],
        'areas' => ['kita-ku-osaka' => 'Kita-ku Osaka'],
        'nearby' => ['osaka', 'yodoyabashi', 'hommachi', 'kyobashi', 'shinsaibashi'],
    ],
    'tennoji' => [
        'english' => ['Tennoji', 'Tennouji', 'JR-Tennoji'],
        'kanji' => ['天王寺駅', '地下鉄天王寺駅'],
        'prefectures' => [3],
        'areas' => ['osaka-tennoji-ku' => 'Tennoji-ku Osaka', 'abeno-ku-osaka' => 'Abeno-ku Osaka'],
        'nearby' => ['namba', 'shinsaibashi', 'kyobashi', 'hommachi', 'osaka'],
    ],
    'osaka' => [
        'english' => ['Osaka', 'JR-Osaka'],
        'kanji' => ['大阪駅'],
        'prefectures' => [3],
        'areas' => ['kita-ku-osaka' => 'Kita-ku Osaka'],
        'nearby' => ['umeda', 'yodoyabashi', 'kyobashi', 'hommachi', 'tennoji'],
    ],
    'shinsaibashi' => [
        'english' => ['Shinsaibashi'],
        'kanji' => ['心斎橋駅', '地下鉄心斎橋駅'],
        'prefectures' => [3],
        'areas' => ['chuo-ku-osaka' => 'Chuo-ku Osaka'],
        'nearby' => ['namba', 'hommachi', 'yodoyabashi', 'umeda', 'tennoji'],
    ],
    'tokyo' => [
        'english' => ['Tokyo'],
        'kanji' => ['東京駅'],
        'prefectures' => [1],
    ],
    'shinjuku' => [
        'english' => ['Shinjuku'],
        'kanji' => ['新宿駅', '京王新宿駅', '小田急新宿駅', '都営新宿駅'],
        'prefectures' => [1],
    ],
    'ikebukuro' => [
        'english' => ['Ikebukuro'],
        'kanji' => ['池袋駅'],
        'prefectures' => [1],
    ],
    'shibuya' => [
        'english' => ['Shibuya'],
        'kanji' => ['渋谷駅', '東急渋谷駅', '京王渋谷駅'],
        'prefectures' => [1],
    ],
    'kyoto' => [
        'english' => ['Kyoto', 'JR-Kyoto'],
        'kanji' => ['京都駅'],
        'prefectures' => [13],
    ],
    'kyoto-kawaramachi' => [
        'english' => ['Kyoto-Kawaramachi', 'Kyoto Kawaramachi', 'Kyotokawaramachi', 'Kawaramachi'],
        'kanji' => ['京都河原町駅', '河原町駅', '阪急河原町駅', '阪急京都河原町駅'],
        'prefectures' => [13],
    ],
    'hakata' => [
        'english' => ['Hakata'],
        'kanji' => ['博多駅'],
        'prefectures' => [9],
    ],
    'tenjin' => [
        'english' => ['Tenjin', 'Nishitetsu Fukuoka', 'Nishitetsu-Fukuoka', 'Nishitetsu Fukuoka (Tenjin)', 'Fukuoka (Tenjin)'],
        'kanji' => ['天神駅', '西鉄福岡駅', '西鉄福岡天神駅', '西鉄福岡（天神）駅', '西鉄福岡(天神)駅'],
        'prefectures' => [9],
    ],
    'nagoya' => [
        'english' => ['Nagoya', 'Meitetsu-Nagoya', 'Kintetsu-Nagoya', 'JR-Nagoya'],
        'kanji' => ['名古屋駅', '名鉄名古屋駅', '近鉄名古屋駅'],
        'prefectures' => [4],
    ],
    'toyohashi' => [
        'english' => ['Toyohashi'],
        'kanji' => ['豊橋駅'],
        'prefectures' => [4],
    ],
    'sakae' => [
        'english' => ['Sakae'],
        'kanji' => ['栄駅'],
        'prefectures' => [4],
    ],
    'utsunomiya' => [
        'english' => ['Utsunomiya', 'JR-Utsunomiya'],
        'kanji' => ['宇都宮駅'],
        'prefectures' => [19],
    ],
    'sendai' => [
        'english' => ['Sendai', 'JR-Sendai'],
        'kanji' => ['仙台駅'],
        'prefectures' => [15],
    ],
    'hirose-dori' => [
        'english' => ['Hirose-dori', 'Hirosedori', 'Hirose dori'],
        'kanji' => ['広瀬通駅'],
        'prefectures' => [15],
    ],
    'omiya' => [
        'english' => ['Omiya', 'JR-Omiya'],
        'kanji' => ['大宮駅'],
        'prefectures' => [5],
    ],
    'kawaguchi' => [
        'english' => ['Kawaguchi'],
        'kanji' => ['川口駅'],
        'prefectures' => [5],
    ],
    'kawasaki' => [
        'english' => ['Kawasaki', 'Keikyu-Kawasaki', 'JR-Kawasaki'],
        'kanji' => ['川崎駅', '京急川崎駅'],
        'prefectures' => [2],
    ],
    'yokohama' => [
        'english' => ['Yokohama', 'JR-Yokohama'],
        'kanji' => ['横浜駅'],
        'prefectures' => [2],
    ],
    'sannomiya' => [
        'english' => ['Sannomiya', 'San-nomiya', 'Kobe-Sannomiya', 'Hankyu-Sannomiya', 'Hanshin-Sannomiya', 'JR-Sannomiya'],
        'kanji' => ['三宮駅', '三ノ宮駅', '三の宮駅', '神戸三宮駅', '阪急三宮駅', '阪神三宮駅', '阪急神戸三宮駅', '阪神神戸三宮駅', '地下鉄三宮駅'],
        'prefectures' => [7],
    ],
    'himeji' => [
        'english' => ['Himeji', 'Sanyo-Himeji', 'JR-Himeji'],
        'kanji' => ['姫路駅', '山陽姫路駅'],
        'prefectures' => [7],
    ],
    'funabashi' => [
        'english' => ['Funabashi', 'JR-Funabashi'],
        'kanji' => ['船橋駅'],
        'prefectures' => [6],
    ],
    'matsudo' => [
        'english' => ['Matsudo'],
        'kanji' => ['松戸駅'],
        'prefectures' => [6],
    ],
    'kashiwa' => [
        'english' => ['Kashiwa'],
        'kanji' => ['柏駅'],
        'prefectures' => [6],
    ],
    'maebashi' => [
        'english' => ['Maebashi'],
        'kanji' => ['前橋駅'],
        'prefectures' => [18],
    ],
    'gifu' => [
        'english' => ['Gifu', 'Meitetsu-Gifu', 'JR-Gifu'],
        'kanji' => ['岐阜駅', '名鉄岐阜駅'],
        'prefectures' => [17],
    ],
    'hamamatsu' => [
        'english' => ['Hamamatsu'],
        'kanji' => ['浜松駅'],
        'prefectures' => [10],
    ],
    'shizuoka' => [
        'english' => ['Shizuoka'],
        'kanji' => ['静岡駅'],
        'prefectures' => [10],
    ],
    'hatchobori' => [
        'english' => ['Hatchobori', 'Hachobori', 'Hacchobori'],
        'kanji' => ['八丁堀駅', '八丁堀電停'],
        'prefectures' => [1, 12],
    ],
    'kanayama' => [
        'english' => ['Kanayama'],
        'kanji' => ['金山駅'],
        'prefectures' => [4],
    ],
    'yodoyabashi' => [
        'english' => ['Yodoyabashi'],
        'kanji' => ['淀屋橋駅'],
        'prefectures' => [3],
        'areas' => ['chuo-ku-osaka' => 'Chuo-ku Osaka'],
        'nearby' => ['hommachi', 'umeda', 'kyobashi', 'shinsaibashi', 'osaka'],
    ],
    'hommachi' => [
        'english' => ['Hommachi', 'Honmachi'],
        'kanji' => ['本町駅'],
        'prefectures' => [3],
        'areas' => ['chuo-ku-osaka' => 'Chuo-ku Osaka', 'nishi-ku-osaka' => 'Nishi-ku Osaka'],
        'nearby' => ['yodoyabashi', 'shinsaibashi', 'namba', 'umeda', 'tennoji'],
    ],
    'urawa' => [
        'english' => ['Urawa'],
        'kanji' => ['浦和駅'],
        'prefectures' => [5],
    ],
    'akihabara' => [
        'english' => ['Akihabara'],
        'kanji' => ['秋葉原駅'],
        'prefectures' => [1],
    ],
    'kyobashi' => [
        'english' => ['Kyobashi'],
        'kanji' => ['京橋駅'],
        'prefectures' => [3, 1],
        'areas' => ['miyakojima-ku-osaka' => 'Miyakojima-ku Osaka', 'chuo-ku' => 'Chuo-ku Tokyo'],
        'nearby' => ['osaka', 'umeda', 'tennoji', 'tokyo', 'hatchobori'],
    ],
    'oyama' => [
        'english' => ['Oyama'],
        'kanji' => ['小山駅'],
        'prefectures' => [19],
    ],
];
