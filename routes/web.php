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

Route::get('/', function () {
    return view('welcome');
});

// ── Phase 1: Static Pages ──
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'contactSubmit']);
Route::get('/faq', [PageController::class, 'faq'])->name('faq');
Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms-of-service', [PageController::class, 'terms'])->name('terms');

// ── Phase 2: Job Detail Pages ──
Route::get('jobs/{jobNo}/detail/{slug?}', [JobController::class, 'detail'])->name('job.detail');
Route::match(['get', 'post'], 'jobs/{jobNo}/apply-2nd-option', [JobController::class, 'applySecondary'])->name('job.apply-secondary');
Route::get('job/link-sent', [JobController::class, 'linkSent'])->name('job.link-sent');

// ── Phase 3: Listing Pages ──
Route::get('jobs', [ListingController::class, 'index'])->name('jobs.index');
Route::get('jobs/page/{page}', [ListingController::class, 'index'])->where('page', '[0-9]+');

// ── Phase 4: Search & Sitemap ──
Route::get('jobs/search', [ListingController::class, 'search'])->name('jobs.search');
Route::get('jobs/search/page/{page}', [ListingController::class, 'search'])->where('page', '[0-9]+');
Route::get('sitemap.xml', [SitemapController::class, 'xml'])->name('sitemap.xml');
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

    // Jobs
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
    Route::get('jobs/unfeaturing', [Admin\JobController::class, 'unfeaturing']);
    Route::post('jobs/unfeature', [Admin\JobController::class, 'unfeature']);
    Route::post('jobs/{status}/search', [Admin\JobController::class, 'search']);
    Route::get('jobs/{status?}/{langId?}/{userId?}', [Admin\JobController::class, 'index'])->name('admin.jobs.index');

    // Subscribers (admin only)
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

    // Blog Posts (admin only)
    Route::get('blog-posts', [Admin\BlogPostController::class, 'index'])->name('admin.blog-posts.index');
    Route::post('blog-posts/list', [Admin\BlogPostController::class, 'list'])->name('admin.blog-posts.list');
    Route::match(['get', 'post'], 'blog-posts/create', [Admin\BlogPostController::class, 'create'])->name('admin.blog-posts.create');
    Route::match(['get', 'post'], 'blog-posts/{id}/edit', [Admin\BlogPostController::class, 'edit'])->name('admin.blog-posts.edit');

    // Change Password (all backend users)
    Route::match(['get', 'post'], 'change-password', [Admin\PasswordController::class, 'edit'])->name('admin.change-password');
});

// Catch-all slug routes — MUST be at the very bottom of web.php
Route::get('{slug}/page/{page}', [ListingController::class, 'bySlug'])->where(['slug' => '[a-z0-9-]+', 'page' => '[0-9]+']);
Route::get('{slug}', [ListingController::class, 'bySlug'])->where('slug', '[a-z0-9-]+');
