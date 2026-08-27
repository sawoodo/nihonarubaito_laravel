<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\JobController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SubscribeController;
use Illuminate\Support\Facades\Route;

// ── Homepage & Listing Pages (cache 30 min) ──
Route::middleware('cache:1800')->group(function () {
    Route::get('/', [ListingController::class, 'index'])->name('home');
    Route::get('jobs', [ListingController::class, 'index'])->name('jobs.index');
    Route::get('jobs/page/{page}', [ListingController::class, 'index'])->where('page', '[0-9]+');
});

// ── Static Pages (cache 1 day) ──
Route::middleware('cache:86400')->group(function () {
    Route::get('/about', [PageController::class, 'about'])->name('about');
    Route::get('/faq', [PageController::class, 'faq'])->name('faq');
    Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('privacy');
    Route::get('/terms-of-service', [PageController::class, 'terms'])->name('terms');
});
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'contactSubmit']);

// ── Job Detail Pages (cache 1 hour) ──
// Numeric job IDs only (current system)
Route::get('jobs/{jobNo}/detail/{slug?}', [JobController::class, 'detail'])
    ->name('job.detail')
    ->middleware('cache:3600')
    ->where('jobNo', '[0-9]+');

// Legacy CI3 alphanumeric job IDs — permanently gone (migration 2022)
Route::get('jobs/{legacyId}/detail/{slug?}', function () {
    abort(410);
});
Route::match(['get', 'post'], 'jobs/{jobNo}/apply-2nd-option', [JobController::class, 'applySecondary'])->name('job.apply-secondary');
Route::get('job/link-sent', [JobController::class, 'linkSent'])->name('job.link-sent');

// ── Search & Sitemap ──
Route::get('jobs/search', [ListingController::class, 'search'])->name('jobs.search');
Route::get('jobs/search/page/{page}', [ListingController::class, 'search'])->where('page', '[0-9]+');
Route::get('sitemap.xml', [SitemapController::class, 'xml'])->name('sitemap.xml');
Route::get('sitemap/xml', fn() => redirect('/sitemap.xml', 301));
Route::post('jobs/areas/get', [ListingController::class, 'getAreas'])->name('jobs.areas.get');

// ── Phase 5: Account, Subscribe & Language ──
Route::match(['get', 'post'], 'account', [AccountController::class, 'index'])->name('account');
Route::match(['get', 'post'], 'account/login', [AccountController::class, 'index']);
Route::get('account/logout', [AccountController::class, 'logout'])->name('account.logout');
Route::match(['get', 'post'], 'subscribe', [SubscribeController::class, 'index'])->name('subscribe');
Route::get('lang/{langId}', [LanguageController::class, 'index'])->where('langId', '[0-9]+')->name('lang');

// ── Phase 6: Admin Panel ──
Route::get('admin/login', [Admin\AuthController::class, 'login'])->name('admin.login');
Route::post('admin/login', [Admin\AuthController::class, 'login']);

Route::prefix('admin')->middleware('admin')->group(function () {
    Route::get('/', fn() => redirect()->route('admin.jobs.index'));
    Route::get('logout', [Admin\AuthController::class, 'logout'])->name('admin.logout');

    // Analytics (admin only)
    Route::get('analytics', [Admin\AnalyticsController::class, 'dashboard'])->name('admin.analytics');
    Route::get('analytics/demand-supply', [Admin\AnalyticsController::class, 'demandSupply'])->name('admin.analytics.demand-supply');
    Route::get('analytics/demand-supply/export', [Admin\AnalyticsController::class, 'exportDemandSupplyCsv'])->name('admin.analytics.demand-supply.export');
    Route::post('analytics/upload-ga4', [Admin\AnalyticsController::class, 'uploadGa4'])->name('admin.analytics.upload-ga4');
    Route::get('analytics/employees', [Admin\AnalyticsController::class, 'employees'])->name('admin.analytics.employees');
    Route::get('analytics/expiring-jobs', [Admin\AnalyticsController::class, 'expiringJobs'])->name('admin.analytics.expiring-jobs');
    Route::post('analytics/expiring-jobs/update-date', [Admin\AnalyticsController::class, 'updateExpiringDate'])->name('admin.analytics.expiring-jobs.update-date');
    Route::post('analytics/expiring-jobs/bulk-extend', [Admin\AnalyticsController::class, 'bulkExtendExpiring'])->name('admin.analytics.expiring-jobs.bulk-extend');
    Route::post('analytics/expiring-jobs/trash', [Admin\AnalyticsController::class, 'trashExpiring'])->name('admin.analytics.expiring-jobs.trash');
    Route::get('analytics/duplicates', [Admin\AnalyticsController::class, 'duplicates'])->name('admin.analytics.duplicates');
    Route::post('analytics/duplicates/keep-and-trash', [Admin\AnalyticsController::class, 'keepAndTrash'])->name('admin.analytics.duplicates.keep-and-trash');
    Route::post('analytics/duplicates/dismiss', [Admin\AnalyticsController::class, 'dismissGroup'])->name('admin.analytics.duplicates.dismiss');
    Route::post('analytics/duplicates/bulk-dismiss', [Admin\AnalyticsController::class, 'bulkDismiss'])->name('admin.analytics.duplicates.bulk-dismiss');
    Route::post('analytics/duplicates/undismiss', [Admin\AnalyticsController::class, 'undismissGroup'])->name('admin.analytics.duplicates.undismiss');

    // Facebook Scheduled Posts
    Route::get('fb-scheduled-posts', [Admin\FbScheduledPostsController::class, 'index'])->name('admin.fb.index');
    Route::post('fb-scheduled-posts/mark-posted', [Admin\FbScheduledPostsController::class, 'markPosted'])->name('admin.fb.markPosted');
    Route::get('fb-scheduled-posts/export', [Admin\FbScheduledPostsController::class, 'export'])->name('admin.fb.export');

    // Jobs
    Route::match(['get', 'post'], 'jobs/create-from-xml', [Admin\CreateJobFromXmlController::class, 'create'])->name('admin.jobs.create-from-xml');
    Route::post('jobs/create-from-xml/upload-file', [Admin\CreateJobFromXmlController::class, 'uploadFile'])->name('admin.jobs.create-from-xml.upload');
    Route::get('jobs/create', [Admin\JobController::class, 'create'])->name('admin.jobs.create');
    Route::post('jobs/create', [Admin\JobController::class, 'create']);
    Route::get('jobs/{jobNo}/edit', [Admin\JobController::class, 'edit'])->name('admin.jobs.edit');
    Route::post('jobs/{jobNo}/edit', [Admin\JobController::class, 'edit']);
    Route::get('jobs/{jobNo}/view', [Admin\JobController::class, 'view'])->name('admin.jobs.view');
    Route::get('jobs/{jobNo}/clone', [Admin\JobController::class, 'cloneJob']);
    Route::get('jobs/{jobNo}/publish', [Admin\JobController::class, 'publish']);
    Route::get('jobs/{jobNo}/draft', [Admin\JobController::class, 'draft']);
    Route::get('jobs/{jobNo}/expire', [Admin\JobController::class, 'expire']);
    Route::get('jobs/{jobNo}/trash', [Admin\JobController::class, 'trash']);
    Route::get('jobs/{jobNo}/toggle-featured', [Admin\JobController::class, 'toggleFeatured']);
    Route::post('jobs/attach-image', [Admin\JobController::class, 'attachImage']);
    Route::post('jobs/detach-image', [Admin\JobController::class, 'detachImage']);
    Route::post('jobs/get-areas', [Admin\JobController::class, 'getAreas']);
    // CI3-compatible aliases (JS uses these URL patterns)
    Route::post('jobs/image/attach', [Admin\JobController::class, 'attachImage']);
    Route::post('jobs/image/detach', [Admin\JobController::class, 'detachImage']);
    Route::post('jobs/get_areas', [Admin\JobController::class, 'getAreas']);
    Route::get('jobs/unfeaturing', [Admin\JobController::class, 'unfeaturing']);
    Route::post('jobs/unfeature', [Admin\JobController::class, 'unfeature']);
    Route::post('jobs/{status}/search', [Admin\JobController::class, 'search']);
    Route::match(['get', 'post'], 'jobs/{status?}/{langId?}/{userId?}', [Admin\JobController::class, 'index'])->name('admin.jobs.index');

    // Subscribers (admin only)
    Route::get('subscribers/insights', [Admin\SubscriberController::class, 'insights'])->name('admin.subscribers.insights');
    Route::get('subscribers', [Admin\SubscriberController::class, 'index'])->name('admin.subscribers.index');
    Route::get('subscribers/page/{page}', [Admin\SubscriberController::class, 'index'])->where('page', '[0-9]+');
    Route::get('subscribers/{id}/detail', [Admin\SubscriberController::class, 'detail'])->name('admin.subscribers.detail');
    Route::match(['get', 'post'], 'subscribers/{id}/change-password', [Admin\SubscriberController::class, 'changePassword'])->name('admin.subscribers.change-password');

    // Users (admin only)
    Route::get('users', [Admin\UserController::class, 'index'])->name('admin.users.index');
    Route::match(['get', 'post'], 'users/create', [Admin\UserController::class, 'create'])->name('admin.users.create');
    Route::match(['get', 'post'], 'users/{id}/edit', [Admin\UserController::class, 'edit'])->name('admin.users.edit');

    // Categories (admin only)
    Route::get('categories', [Admin\CategoryController::class, 'index'])->name('admin.categories.index');
    Route::match(['get', 'post'], 'categories/create', [Admin\CategoryController::class, 'create'])->name('admin.categories.create');
    Route::match(['get', 'post'], 'categories/{id}/edit', [Admin\CategoryController::class, 'edit'])->name('admin.categories.edit');

    // Application Logs (admin only)
    Route::match(['get', 'post'], 'application-logs', [Admin\ApplicationLogController::class, 'index'])->name('admin.application-logs.index');
    Route::post('application-logs/list', [Admin\ApplicationLogController::class, 'list'])->name('admin.application-logs.list');

    // Secondary Applies (admin only)
    Route::match(['get', 'post'], 'secondary-applies', [Admin\SecondaryApplyController::class, 'index'])->name('admin.secondary-applies.index');
    Route::post('secondary-applies/list', [Admin\SecondaryApplyController::class, 'list'])->name('admin.secondary-applies.list');

    // Images
    Route::get('images', [Admin\ImageController::class, 'index'])->name('admin.images.index');
    Route::post('images/list', [Admin\ImageController::class, 'list'])->name('admin.images.list');
    Route::post('images/upload', [Admin\ImageController::class, 'upload'])->name('admin.images.upload');
    Route::post('images/{id}/update-info', [Admin\ImageController::class, 'updateInfo'])->name('admin.images.update-info');
    Route::post('images/{id}/update', [Admin\ImageController::class, 'updateInfo']);

    // Areas (admin only)
    Route::get('areas', [Admin\AreaController::class, 'index'])->name('admin.areas.index');
    Route::match(['get', 'post'], 'areas/{id}/edit', [Admin\AreaController::class, 'edit'])->name('admin.areas.edit');

    // Works (admin only)
    Route::get('works', [Admin\WorkController::class, 'index'])->name('admin.works.index');
    Route::match(['get', 'post'], 'works/create', [Admin\WorkController::class, 'create'])->name('admin.works.create');
    Route::match(['get', 'post'], 'works/{id}/edit', [Admin\WorkController::class, 'edit'])->name('admin.works.edit');

    // Work Descriptions (admin only)
    Route::get('work-descriptions', [Admin\WorkDescriptionController::class, 'index'])->name('admin.work-descriptions.index');
    Route::match(['get', 'post'], 'work-descriptions/create', [Admin\WorkDescriptionController::class, 'create'])->name('admin.work-descriptions.create');
    Route::match(['get', 'post'], 'work-descriptions/{id}/edit', [Admin\WorkDescriptionController::class, 'edit'])->name('admin.work-descriptions.edit');

    // Blog Posts (admin only)
    Route::get('blog-posts', [Admin\BlogPostController::class, 'index'])->name('admin.blog-posts.index');
    Route::post('blog-posts/list', [Admin\BlogPostController::class, 'list'])->name('admin.blog-posts.list');
    Route::match(['get', 'post'], 'blog-posts/create', [Admin\BlogPostController::class, 'create'])->name('admin.blog-posts.create');
    Route::match(['get', 'post'], 'blog-posts/{id}/edit', [Admin\BlogPostController::class, 'edit'])->name('admin.blog-posts.edit');

    // Change Password (all backend users)
    Route::match(['get', 'post'], 'change-password', [Admin\PasswordController::class, 'edit'])->name('admin.change-password');
});

// ── 301 Redirects — Legacy CI3 Routes ──
Route::get('jobs/applied', fn() => redirect('/jobs', 301));
Route::get('jobs/preference', fn() => redirect('/jobs', 301));
Route::get('jobs/suggested', fn() => redirect('/jobs', 301));
Route::get('profile', fn() => redirect('/', 301));
Route::get('account/change-password', fn() => redirect('/', 301));

// ── SEO Redirects ──
// Old station URL pattern: /jobs-at-{station} → /part-time-jobs-at-{station}
Route::get('jobs-at-{station}', fn (string $station) => redirect("part-time-jobs-at-{$station}", 301))
    ->where('station', '[a-zA-Z0-9-]+');

// Bare /page without number → 410 Gone (broken pagination URLs)
Route::get('{slug}/page', fn () => abort(410))
    ->where('slug', '[a-zA-Z0-9-]+');

// Catch-all slug routes (cache 30 min) — MUST be at the very bottom of web.php
Route::middleware('cache:1800')->group(function () {
    Route::get('{slug}/page/{page}', [ListingController::class, 'bySlug'])->where(['slug' => '[a-zA-Z0-9-]+', 'page' => '[0-9]+']);
    Route::get('{slug}', [ListingController::class, 'bySlug'])->where('slug', '[a-zA-Z0-9-]+');
});
