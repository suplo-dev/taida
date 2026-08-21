<?php

use App\Enums\MenuTarget;
use App\Enums\SiteRoute;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu items point at a destination instead of storing a typed-in URL.
 *
 * The old shape kept one URL per locale, written by hand in the admin. Every
 * one of them was a chance to forget the `/zh` prefix or to paste a slug that
 * does not exist, and the cost was not a broken link on one page: the static
 * build crawls its own menu, so a single wrong character failed the publish for
 * all three languages at once. It happened — seven links at once, plus four
 * more that only escaped because the header drops its first two items.
 *
 * URLs are now derived at render time from the record and the active locale, so
 * they cannot drift when an editor renames a slug or adds a translation.
 */
return new class extends Migration
{
    /** Paths the old data used, mapped to the route they meant. */
    private const ROUTE_PATHS = [
        '/' => SiteRoute::Home,
        '/en' => SiteRoute::Home,
        '/zh' => SiteRoute::Home,
        '/dich-vu' => SiteRoute::Services,
        '/en/services' => SiteRoute::Services,
        '/zh/services' => SiteRoute::Services,
        '/nganh-nghe' => SiteRoute::Industries,
        '/en/industries' => SiteRoute::Industries,
        '/zh/industries' => SiteRoute::Industries,
        '/tin-tuc' => SiteRoute::Insights,
        '/en/insights' => SiteRoute::Insights,
        '/zh/insights' => SiteRoute::Insights,
        '/tim-kiem' => SiteRoute::Search,
        '/en/search' => SiteRoute::Search,
        '/zh/search' => SiteRoute::Search,
    ];

    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('target_type', 16)->default(MenuTarget::Route->value)->after('location');
            // Chỉ một trong ba cột dưới có giá trị, tuỳ `target_type`.
            $table->string('target_route', 32)->nullable()->after('target_type');
            // Cố ý không đặt khoá ngoại: cột này trỏ tới bốn bảng khác nhau. Bản
            // ghi bị xoá thì mục menu tự ẩn khỏi site (xem MenuController).
            $table->unsignedBigInteger('target_id')->nullable()->after('target_route');
            $table->string('external_url', 2048)->nullable()->after('target_id');
        });

        $this->migrateUrls();

        Schema::table('menu_item_translations', function (Blueprint $table): void {
            $table->dropColumn('url');
        });
    }

    public function down(): void
    {
        Schema::table('menu_item_translations', function (Blueprint $table): void {
            $table->string('url')->nullable();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn(['target_type', 'target_route', 'target_id', 'external_url']);
        });
    }

    /**
     * Reads each item's old URLs and works out what they meant.
     *
     * Deliberately literal: an exact path match, nothing fuzzy. A URL that does
     * not match any real address today did not work before this migration
     * either, and guessing at what the editor meant would put a link the site
     * never had in front of visitors. Those items keep their labels and are
     * left without a destination — the admin flags them, and the site leaves
     * them out until someone picks one.
     */
    private function migrateUrls(): void
    {
        $slugs = $this->slugIndex();
        $unmapped = [];

        foreach (DB::table('menu_item_translations')->whereNotNull('url')->get() as $translation) {
            $url = rtrim(strtok($translation->url, '?#') ?: '', '/') ?: '/';
            $update = $this->resolve($url, $slugs);

            if ($update === null) {
                $unmapped[] = "#{$translation->menu_item_id} [{$translation->locale}] {$translation->url}";

                continue;
            }

            DB::table('menu_items')->where('id', $translation->menu_item_id)->update($update);
        }

        foreach (array_unique($unmapped) as $line) {
            // `error_log` chứ không phải `Log`: đây là thông tin người chạy
            // migration phải đọc ngay, không phải thứ để tìm lại trong file log.
            error_log("[menu] không đoán được đích đến, mục để trống: {$line}");
        }
    }

    /**
     * @param  array<string, array{type: string, id: int}>  $slugs
     * @return array<string, mixed>|null
     */
    private function resolve(string $url, array $slugs): ?array
    {
        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $url) === 1) {
            return ['target_type' => MenuTarget::External->value, 'external_url' => $url];
        }

        if (isset(self::ROUTE_PATHS[$url])) {
            return ['target_type' => MenuTarget::Route->value, 'target_route' => self::ROUTE_PATHS[$url]->value];
        }

        if (isset($slugs[$url])) {
            return ['target_type' => $slugs[$url]['type'], 'target_id' => $slugs[$url]['id']];
        }

        return null;
    }

    /**
     * Every address the site answers at today, mapped back to its record.
     *
     * Built from the translations themselves rather than from the fallback
     * rules: this only has to recognise URLs an editor could have pasted, and
     * those came from addresses that existed at the time.
     *
     * @return array<string, array{type: string, id: int}>
     */
    private function slugIndex(): array
    {
        $prefixes = [
            MenuTarget::Page->value => ['table' => 'page_translations', 'key' => 'page_id', 'paths' => ['vi' => '/', 'en' => '/en/', 'zh' => '/zh/']],
            MenuTarget::Service->value => ['table' => 'service_translations', 'key' => 'service_id', 'paths' => ['vi' => '/dich-vu/', 'en' => '/en/services/', 'zh' => '/zh/services/']],
            MenuTarget::Industry->value => ['table' => 'industry_translations', 'key' => 'industry_id', 'paths' => ['vi' => '/nganh-nghe/', 'en' => '/en/industries/', 'zh' => '/zh/industries/']],
            MenuTarget::Post->value => ['table' => 'post_translations', 'key' => 'post_id', 'paths' => ['vi' => '/tin-tuc/', 'en' => '/en/insights/', 'zh' => '/zh/insights/']],
        ];

        $index = [];

        foreach ($prefixes as $type => $config) {
            foreach (DB::table($config['table'])->whereNotNull('slug')->get() as $row) {
                $prefix = $config['paths'][$row->locale] ?? null;

                if ($prefix !== null) {
                    $index[rtrim($prefix.$row->slug, '/')] = ['type' => $type, 'id' => (int) $row->{$config['key']}];
                }
            }
        }

        return $index;
    }
};
