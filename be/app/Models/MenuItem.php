<?php

namespace App\Models;

use App\Enums\MenuLocation;
use App\Enums\MenuTarget;
use App\Enums\SiteRoute;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RendersPublicOutput;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model implements RendersPublicOutput
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, HasTranslations;

    /**
     * `url` không còn ở đây: đích đến là một bản ghi, còn địa chỉ được dựng lại
     * lúc render từ slug của bản ghi đó và ngôn ngữ đang hiển thị. Chỉ nhãn là
     * thứ thật sự khác nhau giữa các ngôn ngữ.
     *
     * @var list<string>
     */
    protected array $translatable = ['label'];

    protected $fillable = [
        'location', 'parent_id', 'sort_order', 'opens_in_new_tab',
        'target_type', 'target_route', 'target_id', 'external_url',
    ];

    /** Bản ghi mà `target_id` trỏ tới, do `loadTargets()` điền. */
    private ?Model $resolvedTarget = null;

    /** Phân biệt "chưa tra" với "tra rồi, không có" — hai thứ đều là null. */
    private bool $targetResolved = false;

    /**
     * Navigation is on every page of the site, always.
     */
    public function isPubliclyVisible(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public function publiclyRenderedAttributes(): array
    {
        return [
            'location', 'parent_id', 'sort_order', 'opens_in_new_tab',
            'target_type', 'target_route', 'target_id', 'external_url',
        ];
    }

    protected function casts(): array
    {
        return [
            'location' => MenuLocation::class,
            'target_type' => MenuTarget::class,
            'target_route' => SiteRoute::class,
            'opens_in_new_tab' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * The record this item points at, or null when it points somewhere that is
     * not a record — or at one that has since been deleted.
     *
     * Not an Eloquent relation: `target_id` spans four tables, and a `morphTo`
     * would store the class name in the row, which then goes stale the day a
     * model moves namespace. The lookup is by id on the model this enum names.
     *
     * Resolves itself if nobody primed it, so a caller that forgets
     * `loadTargets()` gets a slow menu rather than an empty one.
     */
    public function target(): ?Model
    {
        if (! $this->targetResolved) {
            static::loadTargets([$this]);
        }

        return $this->resolvedTarget;
    }

    /**
     * Resolves the targets of a whole menu with one query per kind, instead of
     * one per item: a header with a dozen entries is a dozen round trips
     * otherwise, on every page of the site.
     *
     * @param  iterable<int, self>  $items
     */
    public static function loadTargets(iterable $items): void
    {
        /** @var array<string, list<self>> $byType */
        $byType = [];

        foreach ($items as $item) {
            $children = $item->relationLoaded('children') ? $item->children->all() : [];

            foreach ([$item, ...$children] as $node) {
                $node->targetResolved = true;

                // `?->` chứ không phải `->`: menu nằm trên mọi trang, nên một
                // cột đọc hụt phải thành "mục không có đích" — thứ đã được xử
                // lý sẵn ở dưới — chứ không phải 500 cho cả site.
                if ($node->target_type?->isContent() && $node->target_id !== null) {
                    $byType[$node->target_type->value][] = $node;
                }
            }
        }

        foreach ($byType as $type => $nodes) {
            /** @var class-string<Model> $model */
            $model = MenuTarget::from($type)->model();

            $records = $model::query()
                // Chuyển một trang về nháp phải làm mục menu biến mất, chứ
                // không phải để lại một link tới trang 404.
                ->published()
                ->whereIn('id', array_map(fn (self $node): int => (int) $node->target_id, $nodes))
                ->withAllTranslations()
                ->get()
                ->keyBy('id');

            foreach ($nodes as $node) {
                $node->resolvedTarget = $records->get($node->target_id);
            }
        }
    }
}
