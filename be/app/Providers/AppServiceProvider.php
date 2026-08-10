<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Industry;
use App\Models\IndustryTranslation;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\Service;
use App\Models\ServiceTranslation;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\TagTranslation;
use App\Models\User;
use App\Observers\ContentObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Models whose changes invalidate the public read cache. Translations are
     * listed too — editing only the text still changes what the site renders.
     * So is Media: cached responses embed image URLs and alt text, so deleting
     * a picture would otherwise leave a broken image up for the rest of the
     * TTL.
     *
     * @var list<class-string<Model>>
     */
    private const CACHED_CONTENT_MODELS = [
        Service::class, ServiceTranslation::class,
        Industry::class, IndustryTranslation::class,
        Post::class, PostTranslation::class,
        Category::class, CategoryTranslation::class,
        Tag::class, TagTranslation::class,
        Page::class, PageTranslation::class,
        MenuItem::class, MenuItemTranslation::class,
        Setting::class, Media::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // utf8mb4 uses 4 bytes per character; shared-hosting MySQL often caps
        // index keys at 1000 bytes, so varchar(255) indexed columns fail.
        Schema::defaultStringLength(191);

        foreach (self::CACHED_CONTENT_MODELS as $model) {
            $model::observe(ContentObserver::class);
        }

        // Editors write content; only admins change how the site is wired up
        // (navigation, settings) or remove records for good.
        Gate::define('manage-site', fn (User $user): bool => $user->isAdmin());
    }
}
