<?php

namespace App\Support;

/**
 * The MySQL REGEXP that decides whether a job's station field names a station
 * from config/stations.php. Needs MySQL 8's ICU engine for the lookbehinds.
 */
class StationPattern
{
    /**
     * Before an English name: no letter (Sannomiya must not match Omiya) and no
     * hyphen (Shin-Osaka must not match Osaka). The hyphens include the two
     * other characters the translators' text uses.
     */
    private const NOT_AFTER_ENGLISH = '(?<![a-z‐－-])';

    /**
     * The same stations written with a space, which the rule above can't see:
     * the data has "Minami Urawa" and "Tobu Utsunomiya" as well as the hyphens.
     */
    private const NOT_AFTER_WORDS = [
        'shin', 'nishi', 'higashi', 'minami', 'kita', 'naka', 'chuo', 'musashi',
        'tobu', 'seibu', 'keisei', 'sakaisuji', 'sakasuji', 'ue',
    ];

    /** Before a kanji name: no kanji or kana, so 西大宮駅 does not match 大宮駅. */
    private const NOT_AFTER_KANJI = '(?<![一-龥ぁ-んァ-ヶー々])';

    public static function for(array $station): string
    {
        $english = implode('|', array_map(fn ($name) => preg_quote($name, '/'), $station['english']));
        $kanji = implode('|', array_map(fn ($name) => preg_quote($name, '/'), $station['kanji']));

        $notAfterWords = implode('', array_map(fn ($word) => "(?<!{$word} )", self::NOT_AFTER_WORDS));

        return self::NOT_AFTER_ENGLISH.$notAfterWords."(?:{$english}) station"
            .'|'.self::NOT_AFTER_KANJI."(?:{$kanji})";
    }
}
