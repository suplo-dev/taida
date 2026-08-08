<?php

use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\IndustryController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\SitemapController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/ping', fn () => response()->json([
        'ok' => true,
        'locale' => app()->getLocale(),
        'app' => config('app.name'),
    ]))->name('ping');

    /*
     * Public content. Every response is cached per locale and invalidated as
     * soon as an editor saves anything; see App\Support\ContentCache.
     */
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/{slug}', [ServiceController::class, 'show'])->name('services.show');

    Route::get('/industries', [IndustryController::class, 'index'])->name('industries.index');
    Route::get('/industries/{slug}', [IndustryController::class, 'show'])->name('industries.show');

    Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('/posts/{slug}', [PostController::class, 'show'])->name('posts.show');
    Route::get('/posts/{slug}/related', [PostController::class, 'related'])->name('posts.related');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');
    Route::get('/menus/{location}', [MenuController::class, 'show'])->name('menus.show');
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::get('/search', SearchController::class)->name('search');
    Route::get('/sitemap-urls', SitemapController::class)->name('sitemap-urls');

    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

        /*
         * Admin API. Content is editable by any signed-in user; wiring the
         * site up (menus, settings) and deleting records needs an admin.
         */
        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::get('/services', [Admin\ServiceController::class, 'index'])->name('services.index');
            Route::post('/services', [Admin\ServiceController::class, 'store'])->name('services.store');
            Route::put('/services/reorder', [Admin\ServiceController::class, 'reorder'])->name('services.reorder');
            Route::get('/services/{service}', [Admin\ServiceController::class, 'show'])->name('services.show');
            Route::put('/services/{service}', [Admin\ServiceController::class, 'update'])->name('services.update');

            Route::get('/industries', [Admin\IndustryController::class, 'index'])->name('industries.index');
            Route::post('/industries', [Admin\IndustryController::class, 'store'])->name('industries.store');
            Route::put('/industries/reorder', [Admin\IndustryController::class, 'reorder'])->name('industries.reorder');
            Route::get('/industries/{industry}', [Admin\IndustryController::class, 'show'])->name('industries.show');
            Route::put('/industries/{industry}', [Admin\IndustryController::class, 'update'])->name('industries.update');

            Route::apiResource('posts', Admin\PostController::class)->except('destroy');
            Route::apiResource('categories', Admin\CategoryController::class)->except('destroy');
            Route::apiResource('tags', Admin\TagController::class)->only(['index', 'store', 'update']);
            Route::apiResource('pages', Admin\PageController::class)->except('destroy');

            Route::get('/media', [Admin\MediaController::class, 'index'])->name('media.index');
            Route::post('/media', [Admin\MediaController::class, 'store'])->name('media.store');

            Route::middleware('can:manage-site')->group(function (): void {
                Route::get('/menus/{location}', [Admin\MenuController::class, 'show'])->name('menus.show');
                Route::put('/menus/{location}', [Admin\MenuController::class, 'update'])->name('menus.update');

                Route::get('/settings', [Admin\SettingController::class, 'index'])->name('settings.index');
                Route::put('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');

                Route::delete('/services/{service}', [Admin\ServiceController::class, 'destroy'])->name('services.destroy');
                Route::delete('/industries/{industry}', [Admin\IndustryController::class, 'destroy'])->name('industries.destroy');
                Route::delete('/posts/{post}', [Admin\PostController::class, 'destroy'])->name('posts.destroy');
                Route::delete('/categories/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');
                Route::delete('/tags/{tag}', [Admin\TagController::class, 'destroy'])->name('tags.destroy');
                Route::delete('/pages/{page}', [Admin\PageController::class, 'destroy'])->name('pages.destroy');
                Route::delete('/media/{medium}', [Admin\MediaController::class, 'destroy'])->name('media.destroy');
            });
        });
    });
});
