<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Support\StationPattern;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Which jobs a station page lists. Station strings are taken from production.
 *
 * Needs MySQL 8 with the production schema in nihon_match_test: see the header
 * of JobMatchCardTest for the setup.
 */
class StationPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDatabaseName() !== 'nihon_match_test') {
            $this->markTestSkipped('Runs only against the nihon_match_test database.');
        }

        $this->withoutMix();

        // Job::search() inner-joins all of these.
        DB::table('categories')->insertOrIgnore(['id' => 2, 'english' => 'Restaurant', 'japanese' => '', 'chinese' => '', 'vietnamese' => '', 'korean' => '']);
        DB::table('trans_exp_payments')->insertOrIgnore(['id' => 1, 'english' => 'Paid', 'japanese' => '', 'chinese' => '', 'vietnamese' => '', 'korean' => '']);
        DB::table('wage_types')->insertOrIgnore(['id' => 1, 'english' => 'Hourly', 'japanese' => '', 'chinese' => '', 'vietnamese' => '', 'korean' => '']);
        foreach ([1 => 'Tokyo', 3 => 'Osaka', 5 => 'Saitama', 7 => 'Hyogo', 12 => 'Hiroshima', 13 => 'Kyoto', 15 => 'Miyagi', 19 => 'Tochigi'] as $id => $name) {
            DB::table('prefectures')->insertOrIgnore(['id' => $id, 'english' => $name, 'japanese' => $name, 'chinese' => '', 'vietnamese' => '', 'korean' => '', 'region_id' => 1]);
        }
        DB::table('areas')->insertOrIgnore(['id' => 1, 'town_id' => 1, 'english' => 'Test Area', 'japanese' => '', 'chinese' => '', 'vietnamese' => '', 'korean' => '']);
    }

    private function job(int $no, string $station, int $prefectureId, array $over = []): void
    {
        DB::table('jobs')->insert(array_merge([
            'id' => $no, 'job_no' => (string) $no, 'title' => "Job {$no}", 'company_name' => 'Company',
            'description' => 'x', 'job_category_id' => 2, 'prefecture_id' => $prefectureId, 'area_id' => 1,
            'station' => $station, 'address' => 'x', 'japanese_level' => 3, 'working_hours' => '9-5',
            'working_days' => 'Mon', 'wage' => '1,300円', 'wage_type_id' => 1, 'trans_exp_id' => 1,
            'requirement' => 'x', 'img_path' => '', 'img_name' => '', 'img_ext' => '', 'img_id' => 0,
            'lang_id' => 1, 'user_id' => 1, 'job_status_id' => Job::STATUS_PUBLISHED, 'apply_link' => 'https://example.test/'.$no,
            'img_link' => '', 'featured' => 0, 'send_email' => 0, 'date' => now(), 'updated_at' => now(),
            'delete_at' => now()->addDays(20)->format('Y-m-d'),
        ], $over));
    }

    private function stationMatches(string $slug, string $station): bool
    {
        return (bool) DB::selectOne('SELECT ? REGEXP ? AS hit', [$station, StationPattern::for(config('stations')[$slug])])->hit;
    }

    public static function stationStrings(): array
    {
        return [
            'plain' => ['namba', '3 minutes walk from Namba Station 難波駅から徒歩3分', true],
            'company with a hyphen' => ['namba', '4 minutes walk from Osaka-Namba Station 大阪難波駅から徒歩4分', true],
            'company with a space' => ['umeda', '5 minutes walk from Hankyu Umeda Station', true],
            'kanji only' => ['namba', 'なんば駅25番出口から徒歩約4分', true],
            'full-width JR' => ['namba', 'ＪＲ難波駅から徒歩6分', true],
            'line name is not the station' => ['namba', '5 minutes walk from Higashi-Ikoma Station 東生駒駅 (Kintetsu Namba and Nara Line 近鉄難波・奈良線)', false],

            'Omiya inside Sannomiya' => ['omiya', '3 minutes from Sannomiya Station 三宮駅から3分', false],
            'Omiya inside Utsunomiya' => ['omiya', '5 minutes from Utsunomiya Station 宇都宮駅から5分', false],
            'Oyama inside Toyama' => ['oyama', '2 minutes from Dentetsu Toyama Station 電鉄富山駅', false],
            'Oyama inside Musashi-Koyama' => ['oyama', 'Musashi-Koyama Station 武蔵小山駅', false],
            'Shin-Osaka' => ['osaka', '7 minutes from Shin-Osaka Station 新大阪駅から徒歩7分', false],
            'Nishi-Funabashi' => ['funabashi', 'Nishi-Funabashi Station 西船橋駅', false],
            'Minami with a space' => ['urawa', 'Minami Urawa Station', false],
            'Tobu with a space' => ['utsunomiya', '5 minutes from Tobu Utsunomiya Station', false],
            'kanji neighbour' => ['omiya', 'Accessible from 西大宮駅', false],
            'Sakaisuji-Honmachi' => ['hommachi', 'Sakaisuji-Honmachi Station 堺筋本町駅から徒歩2分', false],

            'Honmachi spelling' => ['hommachi', '5 minutes from Honmachi Station 本町駅から徒歩5分', true],
            'Hachobori spelling' => ['hatchobori', '5 minutes walk from Hachobori Station', true],
            'Hacchobori spelling' => ['hatchobori', 'Hacchobori Station (Tokyo Metro Hibiya Line)', true],
            'tram stop' => ['hatchobori', '5 minutes walk from 八丁堀電停', true],
            'hyphen kept' => ['hirose-dori', '4 minutes walk from Hirose-dori Station', true],
            'hyphen dropped' => ['hirose-dori', '3 minutes walk from Hirosedori Station', true],
            'Kawaramachi' => ['kyoto-kawaramachi', 'Kyoto-Kawaramachi Station directly connected', true],
            'Tenjin in brackets' => ['tenjin', 'Nishitetsu Fukuoka (Tenjin) Station', true],
            'Tenjin-Minami stays out' => ['tenjin', '7 minutes walk from Tenjin-Minami Station 天神南駅から徒歩7分', false],
        ];
    }

    #[DataProvider('stationStrings')]
    public function test_a_station_field_matches_only_its_own_station(string $slug, string $station, bool $expected): void
    {
        $this->assertSame($expected, $this->stationMatches($slug, $station), "{$slug} / {$station}");
    }

    /**
     * Today's behaviour, not a wish list. The ones that don't match were left on
     * purpose: none of them appeared for these 40 stations in the 5,860 published
     * station fields on 2026-10-31.
     */
    public static function writtenVariations(): array
    {
        return [
            'all caps' => ['namba', 'NAMBA STATION', 3, true],
            'all lowercase' => ['namba', 'namba station', 3, true],
            'two spaces' => ['namba', 'Namba  Station', 3, false],
            'full-width space' => ['namba', 'Namba　Station', 3, false],
            'macron Tennoji' => ['tennoji', 'Tennōji Station', 3, false],
            'macron Omiya' => ['omiya', 'Ōmiya Station', 5, false],
            'macron Kyoto' => ['kyoto', 'Kyōto Station', 13, false],
            'macron Osaka' => ['osaka', 'Ōsaka Station', 3, false],
            'Sta.' => ['namba', 'Namba Sta.', 3, false],
            'Stn' => ['namba', 'Namba Stn', 3, false],
        ];
    }

    // Through the column, not a literal: its collation is what the page compares against.
    #[DataProvider('writtenVariations')]
    public function test_how_a_written_variation_of_the_name_is_treated(string $slug, string $station, int $prefectureId, bool $expected): void
    {
        $this->job(910051, $station, $prefectureId);

        $this->assertSame(
            $expected,
            Job::where('jobs.id', 910051)->atStation(config('stations')[$slug])->exists(),
            "{$slug} / {$station}"
        );
    }

    public function test_the_config_covers_every_sitemap_station_with_valid_patterns(): void
    {
        $sitemap = (new \ReflectionClass(\App\Http\Controllers\SitemapController::class))->getConstant('STATION_SLUGS');

        $this->assertEqualsCanonicalizing($sitemap, array_keys(config('stations')));

        foreach (config('stations') as $slug => $station) {
            // An invalid regex throws here rather than on the live page.
            DB::selectOne('SELECT ? REGEXP ? AS hit', ['x', StationPattern::for($station)]);
            $this->assertNotEmpty($station['prefectures'], $slug);
        }
    }

    public function test_the_omiya_page_lists_omiya_only(): void
    {
        $this->job(910001, '5 minutes from Omiya Station 大宮駅から徒歩5分', 5);
        $this->job(910002, '3 minutes from Sannomiya Station 三宮駅から3分', 7);
        $this->job(910003, '5 minutes from Utsunomiya Station 宇都宮駅から5分', 19);
        $this->job(910004, '5 minutes from Omiya Station 大宮駅 (Hankyu Kyoto Line)', 13);
        $this->job(910005, 'Ikebukuro Station', 5, ['description' => 'Near Omiya Station.']);

        $page = $this->get('/part-time-jobs-at-omiya-station');

        $page->assertOk();
        $page->assertSee('/jobs/910001/detail', false);
        foreach ([910002, 910003, 910004, 910005] as $no) {
            $page->assertDontSee("/jobs/{$no}/detail", false);
        }
        $page->assertSee('1 active listings', false);
    }

    public function test_hirose_dori_and_kawaramachi_are_no_longer_empty_or_noindexed(): void
    {
        $this->job(910011, '4 minutes walk from Hirose-dori Station 広瀬通駅から徒歩4分', 15);
        $this->job(910012, '2 minutes walk from Kyoto-Kawaramachi Station 京都河原町駅から徒歩2分', 13);

        foreach (['hirose-dori' => 910011, 'kyoto-kawaramachi' => 910012] as $slug => $no) {
            $page = $this->get("/part-time-jobs-at-{$slug}-station");
            $page->assertOk();
            $page->assertSee("/jobs/{$no}/detail", false);
            $page->assertViewHas('noindex', false);
        }
    }

    public function test_kyobashi_and_hatchobori_keep_both_prefectures(): void
    {
        $this->job(910021, 'Kyobashi Station 京橋駅', 1);
        $this->job(910022, 'Kyobashi Station 京橋駅', 3);
        $this->job(910023, 'Hatchobori Station 八丁堀駅', 1);
        $this->job(910024, 'Hatchobori Tram Stop 八丁堀電停', 12);

        $this->get('/part-time-jobs-at-kyobashi-station')
            ->assertSee('/jobs/910021/detail', false)->assertSee('/jobs/910022/detail', false);
        $this->get('/part-time-jobs-at-hatchobori-station')
            ->assertSee('/jobs/910023/detail', false)->assertSee('/jobs/910024/detail', false);
    }

    public function test_a_station_not_in_the_config_stays_a_text_search(): void
    {
        $this->job(910031, 'Asakusa Station 浅草駅', 1);
        $this->job(910032, 'Ueno Station', 1, ['description' => 'Near Asakusa Station.']);

        $this->get('/part-time-jobs-at-asakusa-station')
            ->assertSee('/jobs/910031/detail', false)
            ->assertSee('/jobs/910032/detail', false);
    }

    public function test_an_unpublished_job_stays_off_the_page(): void
    {
        $this->job(910041, 'Omiya Station 大宮駅', 5, ['job_status_id' => Job::STATUS_PENDING]);

        $this->get('/part-time-jobs-at-omiya-station')->assertDontSee('/jobs/910041/detail', false);
    }
}
