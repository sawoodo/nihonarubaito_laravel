<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use App\Services\JobMatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The duplicate card's exits: Discard, the publish check, Publish anyway, and
 * the bulk path's apply link.
 *
 * Needs MySQL (JobMatcher uses INTERVAL arithmetic) with the production schema
 * loaded into a database called nihon_match_test. Never runs anywhere else:
 * phpunit.xml sets no database, so without the override it would point at .env.
 *
 *   mysqldump --no-data <production> > schema.sql
 *   mysql -uroot -e 'CREATE DATABASE nihon_match_test CHARACTER SET utf8mb4'
 *   mysql -uroot nihon_match_test < schema.sql
 *   DB_HOST=127.0.0.1 DB_DATABASE=nihon_match_test DB_USERNAME=root DB_PASSWORD= \
 *     vendor/bin/phpunit tests/Feature/JobMatchCardTest.php
 */
class JobMatchCardTest extends TestCase
{
    use DatabaseTransactions;

    /** 164337 as it stood on production: a Clone of 151033. */
    private const COMPANY = '株式会社キャリア 新宿支店';

    private const LINK = 'https://shigotoin.com/offer/118218183';

    private const TITLE = 'Daily Payment Building Cleaning Part Time Job at Roppongi Station 六本木駅 in Tokyo 1,300円';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDatabaseName() !== 'nihon_match_test') {
            $this->markTestSkipped('Runs only against the nihon_match_test database.');
        }

        $this->withoutMix();

        // A user row is what the admin list's inner join needs; the session
        // object is what the app reads for the role.
        $id = DB::table('users')->insertGetId([
            'first_name' => 'Test', 'last_name' => 'Admin', 'email' => 'admin@example.test',
            'role_id' => User::ROLE_ADMIN,
        ]);
        $this->admin = User::find($id);
        $this->actAs($this->admin);

        DB::table('job_status')->insertOrIgnore([
            ['id' => 2, 'status' => 'Draft'], ['id' => 3, 'status' => 'Published'],
        ]);

        // Job::forEdit() inner-joins languages: without this row the edit page
        // answers "Job not found".
        DB::table('languages')->insertOrIgnore([
            'id' => 1, 'language' => 'english', 'chinese' => '', 'english' => 'English', 'vietnamese' => '',
            'japanese' => '', 'korean' => '', 'flag_path' => '',
        ]);
    }

    private function actAs(User $user): void
    {
        $this->withSession(['loggedin' => true, 'user' => $user]);
    }

    /** A job row. Defaults are the 151033 / 164337 pair; override what differs. */
    private function job(string $no, int $status, array $over = []): string
    {
        DB::table('jobs')->insert(array_merge([
            'id' => (int) $no, 'job_no' => $no, 'title' => self::TITLE, 'company_name' => self::COMPANY,
            'description' => 'x', 'job_category_id' => 4, 'prefecture_id' => 1, 'area_id' => 1,
            'station' => 'Accessible from Roppongi Station 六本木駅', 'address' => '東京都港区',
            'japanese_level' => 3, 'working_hours' => '9-5', 'working_days' => 'Mon', 'wage' => '1,300円',
            'wage_type_id' => 1, 'trans_exp_id' => 1, 'requirement' => 'x', 'img_path' => '', 'img_name' => '',
            'img_ext' => '', 'img_id' => 0, 'lang_id' => 1, 'user_id' => $this->admin->id,
            'job_status_id' => $status, 'apply_link' => self::LINK, 'img_link' => '', 'featured' => 0,
            'send_email' => 0, 'date' => now()->subDays(3), 'updated_at' => now()->subDays(3),
            'delete_at' => now()->addDays(20)->format('Y-m-d'),
        ], $over));

        return $no;
    }

    private function jobStatus(string $no): int
    {
        return (int) DB::table('jobs')->where('job_no', $no)->value('job_status_id');
    }

    /** An exact twin: pending 900002 of live 900001. */
    private function clonePair(): array
    {
        return [$this->job('900001', Job::STATUS_PUBLISHED), $this->job('900002', Job::STATUS_PENDING)];
    }

    private function bulkUpload(string $xml): \Illuminate\Testing\TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('jobs.xml', $xml);

        return $this->post('/admin/jobs/create-from-xml/upload-file', ['file' => $file], ['X-Requested-With' => 'XMLHttpRequest']);
    }

    private function bulkXml(string $linkElement): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><jobs><job>'
            . '<title><![CDATA[' . self::TITLE . ']]></title><company>' . self::COMPANY . '</company>'
            . '<description>x</description><job_category_id>4</job_category_id><prefecture_id>1</prefecture_id>'
            . '<area_id>1</area_id><station>Roppongi Station 六本木駅</station><address>東京都港区</address>'
            . '<japanese_level>3</japanese_level><working_hours>9-5</working_hours><working_days>Mon</working_days>'
            . '<wage><base>1,300円</base></wage><wage_type_id>1</wage_type_id><trans_exp_id>1</trans_exp_id>'
            . $linkElement . '</job></jobs>';
    }

    private function importCard(string $form): array
    {
        return [
            'certain' => [[
                'job_no' => '900001', 'job_status_id' => 3, 'title' => self::TITLE, 'company_name' => self::COMPANY,
                'address' => '東京都港区', 'station' => 'Roppongi', 'wage' => '1,300円', 'apply_link' => self::LINK,
                'delete_at' => '2026-11-16', 'status_label' => 'Published', 'retired_days_ago' => null,
                'reason' => 'same listing', 'verdict' => 'certain',
            ]],
            'review' => [],
            'new_link' => self::LINK,
            'delete_in_days' => 20,
            'return_to' => $form,
        ];
    }

    // ── (a) Discard ─────────────────────────────────────────────────────

    #[DataProvider('forms')]
    public function test_discard_logs_one_row_and_writes_no_job(string $form, string $url): void
    {
        $this->job('900001', Job::STATUS_PUBLISHED);
        $jobs = DB::table('jobs')->count();

        $this->post('/admin/jobs/match/discard', [
            'matched_job_no' => '900001', 'form' => $form, 'apply_link' => self::LINK,
        ])->assertRedirect($url)->assertSessionHas('success', 'Nothing was saved.');

        $this->assertSame($jobs, DB::table('jobs')->count());

        $row = DB::table('job_match_discards')->sole();
        $this->assertSame([$this->admin->id, 'admin@example.test', '900001', $form, self::LINK],
            [$row->user_id, $row->actor, $row->matched_job_no, $row->form, $row->apply_link]);
        $this->assertNotNull($row->created_at);
    }

    public static function forms(): array
    {
        return [
            'xml' => ['xml', '/admin/jobs/create-from-xml'],
            'manual' => ['manual', '/admin/jobs/create'],
        ];
    }

    public function test_discard_needs_no_admin_role(): void
    {
        $advertiser = new User(['role_id' => User::ROLE_ADVERTISER]);
        $advertiser->role_id = User::ROLE_ADVERTISER;
        $advertiser->id = 7;
        $this->actAs($advertiser);

        $this->post('/admin/jobs/match/discard', ['matched_job_no' => '900001', 'form' => 'xml'])
            ->assertRedirect('/admin/jobs/create-from-xml');

        $this->assertSame(1, DB::table('job_match_discards')->count());
    }

    public function test_discarded_form_comes_back_empty_with_no_card(): void
    {
        $this->withSession(['job_matches' => $this->importCard('xml'), '_old_input' => ['xml_data' => '<job/>']]);

        $this->post('/admin/jobs/match/discard', ['matched_job_no' => '900001', 'form' => 'xml'])
            ->assertSessionMissing('job_matches')->assertSessionMissing('_old_input');
    }

    #[DataProvider('forms')]
    public function test_import_card_renders_discard_inside_the_real_page(string $form, string $url): void
    {
        $this->withSession(['job_matches' => $this->importCard($form)]);

        $this->get($url)->assertOk()
            ->assertSee("Discard — don't save this job", false)
            ->assertSee('action="' . url('admin/jobs/match/discard') . '"', false)
            ->assertSee('Update job #900001 with this link')
            ->assertDontSee('and trash this one')
            ->assertDontSee('Publish anyway');
    }

    // ── (b) publish check ───────────────────────────────────────────────

    public function test_publish_button_is_blocked_by_a_certain_match(): void
    {
        [$live, $pending] = $this->clonePair();
        $posts = DB::table('fb_posts')->count();

        $this->get("/admin/jobs/{$pending}/publish")
            ->assertRedirect("/admin/jobs/{$pending}/edit")
            ->assertSessionHas('job_matches', fn ($c) => $c['certain'][0]['job_no'] === $live
                && $c['return_to'] === 'publish' && $c['publish_job_no'] === $pending);

        $this->assertSame(Job::STATUS_PENDING, $this->jobStatus($pending));
        $this->assertSame($posts, DB::table('fb_posts')->count());
    }

    public function test_update_and_publish_is_blocked_but_keeps_the_edit(): void
    {
        [$live, $pending] = $this->clonePair();

        $this->post("/admin/jobs/{$pending}/edit", $this->editForm(['update_and_publish' => '1', 'working_days' => 'Tue']))
            ->assertRedirect("/admin/jobs/{$pending}/edit")
            ->assertSessionHas('job_matches', fn ($c) => $c['certain'][0]['job_no'] === $live);

        $row = DB::table('jobs')->where('job_no', $pending)->first();
        $this->assertSame(Job::STATUS_PENDING, (int) $row->job_status_id);
        $this->assertSame('Tue', $row->working_days);
    }

    public function test_the_link_arriving_on_the_edit_form_is_caught(): void
    {
        $this->job('900001', Job::STATUS_PUBLISHED);
        $pending = $this->job('900002', Job::STATUS_PENDING, ['apply_link' => '']);

        $this->post("/admin/jobs/{$pending}/edit", $this->editForm(['update_and_publish' => '1', 'apply_link' => self::LINK]))
            ->assertSessionHas('job_matches');

        $this->assertSame(Job::STATUS_PENDING, $this->jobStatus($pending));
    }

    public function test_a_clone_is_caught_at_publish(): void
    {
        $live = $this->job('900001', Job::STATUS_PUBLISHED);

        $this->get("/admin/jobs/{$live}/clone")->assertRedirect('/admin/jobs');

        $clone = (string) DB::table('jobs')->orderByDesc('id')->value('job_no');
        $this->assertNotSame($live, $clone);

        $this->get("/admin/jobs/{$clone}/publish")->assertRedirect("/admin/jobs/{$clone}/edit");
        $this->assertSame(Job::STATUS_PENDING, $this->jobStatus($clone));
    }

    public function test_a_job_does_not_match_itself(): void
    {
        $pending = $this->job('900002', Job::STATUS_PENDING);

        $this->get("/admin/jobs/{$pending}/publish")
            ->assertRedirect('/admin/jobs')->assertSessionHas('success', 'Job has been published.');

        $this->assertSame(Job::STATUS_PUBLISHED, $this->jobStatus($pending));
    }

    public function test_a_review_only_match_does_not_block(): void
    {
        // 163965 and 163731: one offer id under two companies. Never certain.
        $this->job('900001', Job::STATUS_PUBLISHED, ['company_name' => '株式会社ウィルエージェンシー']);
        $pending = $this->job('900002', Job::STATUS_PENDING);

        $this->get("/admin/jobs/{$pending}/publish")->assertRedirect('/admin/jobs');

        $this->assertSame(Job::STATUS_PUBLISHED, $this->jobStatus($pending));
    }

    public function test_update_and_publish_on_a_live_job_is_not_blocked(): void
    {
        [, $other] = [$this->job('900001', Job::STATUS_PUBLISHED), $this->job('900002', Job::STATUS_PUBLISHED)];

        $this->post("/admin/jobs/{$other}/edit", $this->editForm(['update_and_publish' => '1']))
            ->assertRedirect('/admin/jobs')->assertSessionMissing('job_matches');
    }

    public function test_publish_card_renders_inside_the_real_edit_page(): void
    {
        [, $pending] = $this->clonePair();

        $edit = $this->followingRedirects()->get("/admin/jobs/{$pending}/publish")->assertOk();

        $edit->assertSee('This looks like job #900001')
            ->assertSee('Update job #900001 with this link and trash this one')
            ->assertSee('action="' . url('admin/jobs/match/relink-and-trash') . '"', false)
            ->assertSee('action="' . url("admin/jobs/{$pending}/publish-anyway") . '"', false)
            ->assertSee('Publish anyway')
            ->assertDontSee('Discard');

        // Two forms may not nest: the card must close before the edit form opens.
        $html = $edit->getContent();
        $this->assertLessThan(
            strpos($html, 'action="' . url("admin/jobs/{$pending}/edit") . '"'),
            strrpos($html, 'Publish anyway')
        );
    }

    public function test_a_job_with_no_link_is_not_offered_the_relink_button(): void
    {
        $this->job('900001', Job::STATUS_PUBLISHED, ['address' => '東京都港区六本木1-2-3']);
        $pending = $this->job('900002', Job::STATUS_PENDING, ['apply_link' => '', 'address' => '東京都港区六本木1-2-3']);

        $this->followingRedirects()->get("/admin/jobs/{$pending}/publish")->assertOk()
            ->assertDontSee('with this link and trash this one')
            ->assertSee('This job has no apply link')
            ->assertSee('Publish anyway');
    }

    public function test_relink_and_trash_merges_the_pair(): void
    {
        $live = $this->job('900001', Job::STATUS_PUBLISHED, ['apply_link' => 'https://shigotoin.com/offer/111']);
        $pending = $this->job('900002', Job::STATUS_PENDING, ['delete_at' => now()->addDays(50)->format('Y-m-d')]);
        $posted = DB::table('jobs')->where('job_no', $live)->value('date');

        $this->post('/admin/jobs/match/relink-and-trash', [
            'job_no' => $live, 'duplicate_job_no' => $pending, 'expect_status' => 3,
            'expect_link' => 'https://shigotoin.com/offer/111', 'expect_duplicate_link' => self::LINK,
        ])->assertRedirect("/admin/jobs/{$live}/edit")->assertSessionHas('success');

        $keep = DB::table('jobs')->where('job_no', $live)->first();
        $this->assertSame(self::LINK, $keep->apply_link);
        $this->assertSame(Job::STATUS_PUBLISHED, (int) $keep->job_status_id);
        $this->assertSame(now()->addDays(50)->format('Y-m-d'), substr($keep->delete_at, 0, 10), 'later review date carried over');
        $this->assertSame($posted, $keep->date, 'date posted never moves');

        $dup = DB::table('jobs')->where('job_no', $pending)->first();
        $this->assertSame(Job::STATUS_TRASHED, (int) $dup->job_status_id);
        $this->assertNotNull($dup->Expire_Date);

        $this->assertSame(1, DB::table('job_apply_link_history')
            ->where(['job_no' => $live, 'source' => 'publish-check', 'new_apply_link' => self::LINK])->count());
        $this->assertSame(1, DB::table('job_status_history')
            ->where(['job_no' => $pending, 'source' => 'dedupe', 'new_status' => 5, 'reason' => "duplicate of job {$live}"])->count());
    }

    public function test_a_trashed_copy_points_the_next_import_at_the_keeper(): void
    {
        [$live, $pending] = $this->clonePair();

        $this->post('/admin/jobs/match/relink-and-trash', [
            'job_no' => $live, 'duplicate_job_no' => $pending, 'expect_status' => 3,
            'expect_link' => self::LINK, 'expect_duplicate_link' => self::LINK,
        ]);

        $nos = array_map(fn ($m) => (string) ((array) $m['job'])['job_no'], JobMatcher::findMatches([
            'title' => self::TITLE, 'company_name' => self::COMPANY, 'address' => '東京都港区',
            'station' => 'Roppongi Station 六本木駅', 'wage' => '1,300円', 'job_category_id' => 4,
            'prefecture_id' => 1, 'apply_link' => self::LINK,
        ]));

        $this->assertSame([$live], $nos);
    }

    public function test_a_stale_card_changes_nothing(): void
    {
        [$live, $pending] = $this->clonePair();

        // Someone relinked the live job after the card was drawn.
        DB::table('jobs')->where('job_no', $live)->update(['apply_link' => 'https://shigotoin.com/offer/222']);

        $this->post('/admin/jobs/match/relink-and-trash', [
            'job_no' => $live, 'duplicate_job_no' => $pending, 'expect_status' => 3,
            'expect_link' => self::LINK, 'expect_duplicate_link' => self::LINK,
        ])->assertRedirect("/admin/jobs/{$pending}/edit")->assertSessionHas('error');

        $this->assertSame(Job::STATUS_PENDING, $this->jobStatus($pending));
        $this->assertSame('https://shigotoin.com/offer/222', DB::table('jobs')->where('job_no', $live)->value('apply_link'));
        $this->assertSame(0, DB::table('job_status_history')->where('job_no', $pending)->count());
    }

    public function test_only_a_pending_job_can_be_trashed_this_way(): void
    {
        $live = $this->job('900001', Job::STATUS_PUBLISHED, ['apply_link' => 'https://shigotoin.com/offer/111']);
        $other = $this->job('900002', Job::STATUS_PUBLISHED);

        $this->post('/admin/jobs/match/relink-and-trash', [
            'job_no' => $live, 'duplicate_job_no' => $other, 'expect_status' => 3,
            'expect_link' => 'https://shigotoin.com/offer/111', 'expect_duplicate_link' => self::LINK,
        ])->assertSessionHas('error');

        $this->assertSame(Job::STATUS_PUBLISHED, $this->jobStatus($other));
    }

    public function test_a_job_cannot_be_merged_into_itself(): void
    {
        $pending = $this->job('900002', Job::STATUS_PENDING);

        $this->post('/admin/jobs/match/relink-and-trash', [
            'job_no' => $pending, 'duplicate_job_no' => $pending, 'expect_status' => 2,
            'expect_link' => self::LINK, 'expect_duplicate_link' => self::LINK,
        ])->assertSessionHas('error');

        $this->assertSame(Job::STATUS_PENDING, $this->jobStatus($pending));
    }

    public function test_relink_and_trash_is_refused_to_an_advertiser(): void
    {
        [$live, $pending] = $this->clonePair();

        $advertiser = new User;
        $advertiser->role_id = User::ROLE_ADVERTISER;
        $this->actAs($advertiser);

        $this->post('/admin/jobs/match/relink-and-trash', [
            'job_no' => $live, 'duplicate_job_no' => $pending, 'expect_status' => 3,
            'expect_link' => self::LINK, 'expect_duplicate_link' => self::LINK,
        ])->assertRedirect('/admin/jobs');

        $this->assertSame(Job::STATUS_PENDING, $this->jobStatus($pending));
    }

    public function test_publish_anyway_needs_ten_characters(): void
    {
        [, $pending] = $this->clonePair();

        $this->post("/admin/jobs/{$pending}/publish-anyway", ['publish_reason' => ' too short '])
            ->assertRedirect("/admin/jobs/{$pending}/edit")
            ->assertSessionHasErrors('publish_reason')
            ->assertSessionHas('job_matches');

        $this->assertSame(Job::STATUS_PENDING, $this->jobStatus($pending));
        $this->assertSame(0, DB::table('job_status_history')->where('job_no', $pending)->count());
    }

    public function test_publish_anyway_publishes_and_logs_the_reason(): void
    {
        [$live, $pending] = $this->clonePair();
        $posts = DB::table('fb_posts')->count();

        $this->post("/admin/jobs/{$pending}/publish-anyway", ['publish_reason' => 'second shift at this shop'])
            ->assertRedirect('/admin/jobs')->assertSessionHas('success', 'Job has been published.');

        $this->assertSame(Job::STATUS_PUBLISHED, $this->jobStatus($pending));
        $this->assertSame($posts + 1, DB::table('fb_posts')->count());

        $row = DB::table('job_status_history')->where('job_no', $pending)->sole();
        $this->assertSame(
            [2, 3, 'publish-check', 'admin@example.test', "published despite match with {$live}: second shift at this shop"],
            [(int) $row->old_status, (int) $row->new_status, $row->source, $row->actor, $row->reason]
        );
    }

    public function test_publish_anyway_on_a_job_already_live_does_nothing(): void
    {
        $job = $this->job('900001', Job::STATUS_PUBLISHED);
        $posts = DB::table('fb_posts')->count();

        $this->post("/admin/jobs/{$job}/publish-anyway", ['publish_reason' => 'a double click on the button'])
            ->assertRedirect('/admin/jobs')->assertSessionHas('error');

        $this->assertSame($posts, DB::table('fb_posts')->count());
    }

    public function test_publish_anyway_is_admin_only(): void
    {
        [, $pending] = $this->clonePair();

        $manager = new User;
        $manager->role_id = User::ROLE_MANAGER;
        $this->actAs($manager);

        $this->post("/admin/jobs/{$pending}/publish-anyway", ['publish_reason' => 'a manager should not reach this'])
            ->assertRedirect('/admin/jobs');

        $this->assertSame(Job::STATUS_PENDING, $this->jobStatus($pending));
    }

    // ── (b) the dropzone's apply link ───────────────────────────────────

    #[DataProvider('linkElements')]
    public function test_bulk_upload_stores_the_feed_link(string $element): void
    {
        $this->bulkUpload($this->bulkXml($element))->assertOk()->assertJsonPath('success', true);

        $row = DB::table('jobs')->orderByDesc('id')->first();
        $this->assertSame(self::LINK, $row->apply_link);
        $this->assertSame(Job::STATUS_PENDING, (int) $row->job_status_id);
    }

    public static function linkElements(): array
    {
        return [
            'apply_url, what the feed sends' => ['<apply_url>' . self::LINK . '</apply_url>'],
            'apply_link, hand-written' => ['<apply_link> ' . self::LINK . ' </apply_link>'],
        ];
    }

    public function test_bulk_upload_sees_a_certain_match_through_the_feed_link(): void
    {
        // Different address and role: only the listing id can connect them.
        $this->job('900001', Job::STATUS_PUBLISHED, [
            'address' => '東京都港区六本木1-2-3', 'station' => '', 'title' => 'Office Building Cleaning Staff Job',
        ]);

        $this->bulkUpload(str_replace('Daily Payment ', '', $this->bulkXml('<apply_url>' . self::LINK . '</apply_url>')))
            ->assertOk()->assertJsonPath('success', true);

        $row = DB::table('jobs')->orderByDesc('id')->first();
        $this->assertSame(Job::STATUS_DRAFT, (int) $row->job_status_id);
        $this->assertStringStartsWith('[SAME AS 900001] ', $row->title);
    }

    public function test_a_same_as_marker_never_blocks_a_later_import(): void
    {
        $live = $this->job('900001', Job::STATUS_PUBLISHED);
        $this->job('900002', Job::STATUS_DRAFT, ['title' => '[SAME AS 900001] ' . self::TITLE]);

        $matches = JobMatcher::findMatches([
            'title' => self::TITLE, 'company_name' => self::COMPANY, 'address' => '東京都港区',
            'station' => 'Roppongi Station 六本木駅', 'wage' => '1,300円', 'job_category_id' => 4,
            'prefecture_id' => 1, 'apply_link' => self::LINK,
        ]);

        $this->assertSame([$live], array_map(fn ($m) => (string) ((array) $m['job'])['job_no'], $matches));
    }

    /** Everything the edit form requires, from a row built by job(). */
    private function editForm(array $over = []): array
    {
        return array_merge([
            'title' => self::TITLE, 'company_name' => self::COMPANY, 'description' => 'x', 'job_category_id' => 4,
            'prefecture_id' => 1, 'area_id' => 1, 'station' => 'Accessible from Roppongi Station 六本木駅',
            'address' => '東京都港区', 'japanese_level' => 3, 'working_hours' => '9-5', 'working_days' => 'Mon',
            'wage' => '1,300円', 'wage_type_id' => 1, 'trans_exp_id' => 1, 'requirement' => 'x',
            'apply_link' => self::LINK, 'delete_at' => 20,
        ], $over);
    }
}
