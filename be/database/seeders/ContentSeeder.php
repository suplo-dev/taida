<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\MenuLocation;
use App\Models\Category;
use App\Models\Industry;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Bilingual reference content: the four ATIC service pillars, the eight
 * industry groups, editorial posts, static pages, menus and site settings.
 *
 * Written by hand rather than faked so the frontend can be built against
 * text that is representative of what the client will publish.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->firstOrFail();

        $this->seedServices();
        $this->seedIndustries();
        $this->linkIndustriesToServices();
        $this->seedPosts($author);
        $this->seedPages();
        $this->seedMenus();
        $this->seedSettings();
    }

    /**
     * @return array<int, array{icon: string, children: list<array<string, array<string, string>>>, vi: array<string, string>, en: array<string, string>}>
     */
    private function servicePillars(): array
    {
        return [
            [
                'icon' => 'shield-check',
                'vi' => [
                    'name' => 'Đảm bảo chất lượng',
                    'slug' => 'dam-bao-chat-luong',
                    'excerpt' => 'Đánh giá hệ thống và chuỗi cung ứng để doanh nghiệp kiểm soát rủi ro chất lượng từ gốc.',
                ],
                'en' => [
                    'name' => 'Assurance',
                    'slug' => 'assurance',
                    'excerpt' => 'System and supply-chain assessments that let you control quality risk at its source.',
                ],
                'children' => [
                    [
                        'vi' => ['name' => 'Đánh giá nhà cung cấp', 'slug' => 'danh-gia-nha-cung-cap'],
                        'en' => ['name' => 'Supplier Audits', 'slug' => 'supplier-audits'],
                    ],
                    [
                        'vi' => ['name' => 'Đánh giá hệ thống quản lý', 'slug' => 'danh-gia-he-thong-quan-ly'],
                        'en' => ['name' => 'Management System Audits', 'slug' => 'management-system-audits'],
                    ],
                ],
            ],
            [
                'icon' => 'flask-conical',
                'vi' => [
                    'name' => 'Thử nghiệm',
                    'slug' => 'thu-nghiem',
                    'excerpt' => 'Phòng thử nghiệm được công nhận, kiểm tra sản phẩm theo tiêu chuẩn quốc gia và quốc tế.',
                ],
                'en' => [
                    'name' => 'Testing',
                    'slug' => 'testing',
                    'excerpt' => 'Accredited laboratories testing products against national and international standards.',
                ],
                'children' => [
                    [
                        'vi' => ['name' => 'Thử nghiệm vật liệu', 'slug' => 'thu-nghiem-vat-lieu'],
                        'en' => ['name' => 'Materials Testing', 'slug' => 'materials-testing'],
                    ],
                    [
                        'vi' => ['name' => 'Thử nghiệm an toàn điện', 'slug' => 'thu-nghiem-an-toan-dien'],
                        'en' => ['name' => 'Electrical Safety Testing', 'slug' => 'electrical-safety-testing'],
                    ],
                ],
            ],
            [
                'icon' => 'search',
                'vi' => [
                    'name' => 'Giám định',
                    'slug' => 'giam-dinh',
                    'excerpt' => 'Giám định hàng hoá, công trình và quy trình tại hiện trường, ở mọi giai đoạn của dự án.',
                ],
                'en' => [
                    'name' => 'Inspection',
                    'slug' => 'inspection',
                    'excerpt' => 'On-site inspection of goods, works and processes at every stage of a project.',
                ],
                'children' => [
                    [
                        'vi' => ['name' => 'Giám định hàng hoá', 'slug' => 'giam-dinh-hang-hoa'],
                        'en' => ['name' => 'Cargo Inspection', 'slug' => 'cargo-inspection'],
                    ],
                    [
                        'vi' => ['name' => 'Giám định công trình', 'slug' => 'giam-dinh-cong-trinh'],
                        'en' => ['name' => 'Site Inspection', 'slug' => 'site-inspection'],
                    ],
                ],
            ],
            [
                'icon' => 'badge-check',
                'vi' => [
                    'name' => 'Chứng nhận',
                    'slug' => 'chung-nhan',
                    'excerpt' => 'Cấp chứng nhận hợp chuẩn, hợp quy giúp sản phẩm và hệ thống được thị trường chấp nhận.',
                ],
                'en' => [
                    'name' => 'Certification',
                    'slug' => 'certification',
                    'excerpt' => 'Product and system certification that earns your business market acceptance.',
                ],
                'children' => [
                    [
                        'vi' => ['name' => 'Chứng nhận sản phẩm', 'slug' => 'chung-nhan-san-pham'],
                        'en' => ['name' => 'Product Certification', 'slug' => 'product-certification'],
                    ],
                    [
                        'vi' => ['name' => 'Chứng nhận ISO', 'slug' => 'chung-nhan-iso'],
                        'en' => ['name' => 'ISO Certification', 'slug' => 'iso-certification'],
                    ],
                ],
            ],
        ];
    }

    private function seedServices(): void
    {
        foreach ($this->servicePillars() as $order => $pillar) {
            $service = Service::create([
                'icon' => $pillar['icon'],
                'sort_order' => $order,
                'is_featured' => true,
                'status' => ContentStatus::Published,
                'published_at' => now(),
            ]);

            foreach (['vi', 'en'] as $locale) {
                $service->translations()->create([
                    'locale' => $locale,
                    'name' => $pillar[$locale]['name'],
                    'slug' => $pillar[$locale]['slug'],
                    'excerpt' => $pillar[$locale]['excerpt'],
                    'body' => "<p>{$pillar[$locale]['excerpt']}</p>",
                    'meta_title' => $pillar[$locale]['name'],
                    'meta_description' => $pillar[$locale]['excerpt'],
                ]);
            }

            foreach ($pillar['children'] as $childOrder => $child) {
                $sub = Service::create([
                    'parent_id' => $service->id,
                    'sort_order' => $childOrder,
                    'status' => ContentStatus::Published,
                    'published_at' => now(),
                ]);

                foreach (['vi', 'en'] as $locale) {
                    $sub->translations()->create([
                        'locale' => $locale,
                        'name' => $child[$locale]['name'],
                        'slug' => $child[$locale]['slug'],
                        'body' => "<p>{$child[$locale]['name']}</p>",
                    ]);
                }
            }
        }
    }

    /**
     * @return list<array{vi: array{name: string, slug: string}, en: array{name: string, slug: string}, icon: string}>
     */
    private function industryGroups(): array
    {
        return [
            ['icon' => 'flask-round', 'vi' => ['name' => 'Hoá chất', 'slug' => 'hoa-chat'], 'en' => ['name' => 'Chemicals', 'slug' => 'chemicals']],
            ['icon' => 'hard-hat', 'vi' => ['name' => 'Xây dựng & Kỹ thuật', 'slug' => 'xay-dung-ky-thuat'], 'en' => ['name' => 'Construction & Engineering', 'slug' => 'construction-engineering']],
            ['icon' => 'fuel', 'vi' => ['name' => 'Năng lượng & Hàng hoá', 'slug' => 'nang-luong-hang-hoa'], 'en' => ['name' => 'Energy & Commodities', 'slug' => 'energy-commodities']],
            ['icon' => 'utensils', 'vi' => ['name' => 'Thực phẩm & Y tế', 'slug' => 'thuc-pham-y-te'], 'en' => ['name' => 'Food & Healthcare', 'slug' => 'food-healthcare']],
            ['icon' => 'bed-double', 'vi' => ['name' => 'Khách sạn & Du lịch', 'slug' => 'khach-san-du-lich'], 'en' => ['name' => 'Hospitality & Tourism', 'slug' => 'hospitality-tourism']],
            ['icon' => 'landmark', 'vi' => ['name' => 'Chính phủ & Thương mại', 'slug' => 'chinh-phu-thuong-mai'], 'en' => ['name' => 'Government & Trade', 'slug' => 'government-trade']],
            ['icon' => 'truck', 'vi' => ['name' => 'Giao thông vận tải', 'slug' => 'giao-thong-van-tai'], 'en' => ['name' => 'Transportation', 'slug' => 'transportation']],
            ['icon' => 'shopping-bag', 'vi' => ['name' => 'Sản phẩm & Bán lẻ', 'slug' => 'san-pham-ban-le'], 'en' => ['name' => 'Products & Retail', 'slug' => 'products-retail']],
        ];
    }

    private function seedIndustries(): void
    {
        foreach ($this->industryGroups() as $order => $group) {
            $industry = Industry::create([
                'icon' => $group['icon'],
                'sort_order' => $order,
                'is_featured' => true,
                'status' => ContentStatus::Published,
                'published_at' => now(),
            ]);

            $excerpt = [
                'vi' => "Giải pháp đảm bảo chất lượng dành riêng cho ngành {$group['vi']['name']}.",
                'en' => "Quality assurance solutions tailored to the {$group['en']['name']} sector.",
            ];

            foreach (['vi', 'en'] as $locale) {
                $industry->translations()->create([
                    'locale' => $locale,
                    'name' => $group[$locale]['name'],
                    'slug' => $group[$locale]['slug'],
                    'excerpt' => $excerpt[$locale],
                    'body' => "<p>{$excerpt[$locale]}</p>",
                    'meta_title' => $group[$locale]['name'],
                    'meta_description' => $excerpt[$locale],
                ]);
            }
        }
    }

    /** Every industry is served by all four pillars. */
    private function linkIndustriesToServices(): void
    {
        $pillarIds = Service::query()->roots()->orderBy('sort_order')->pluck('id');

        Industry::query()->each(function (Industry $industry) use ($pillarIds): void {
            $industry->services()->sync(
                $pillarIds->mapWithKeys(fn (int $id, int $index) => [$id => ['sort_order' => $index]])->all()
            );
        });
    }

    private function seedPosts(User $author): void
    {
        $categories = collect([
            ['vi' => ['name' => 'Tin công ty', 'slug' => 'tin-cong-ty'], 'en' => ['name' => 'Company News', 'slug' => 'company-news']],
            ['vi' => ['name' => 'Quy định & Tiêu chuẩn', 'slug' => 'quy-dinh-tieu-chuan'], 'en' => ['name' => 'Regulatory Updates', 'slug' => 'regulatory-updates']],
        ])->map(function (array $data, int $order): Category {
            $category = Category::create(['sort_order' => $order]);

            foreach (['vi', 'en'] as $locale) {
                $category->translations()->create([
                    'locale' => $locale,
                    'name' => $data[$locale]['name'],
                    'slug' => $data[$locale]['slug'],
                ]);
            }

            return $category;
        });

        Post::factory()
            ->count(10)
            ->sequence(fn ($sequence) => [
                'category_id' => $categories[$sequence->index % $categories->count()]->id,
                'author_id' => $author->id,
                'is_featured' => $sequence->index < 3,
            ])
            ->create();
    }

    private function seedPages(): void
    {
        $pages = [
            [
                'key' => 'about-us',
                'vi' => ['title' => 'Về chúng tôi', 'slug' => 've-chung-toi'],
                'en' => ['title' => 'About Us', 'slug' => 'about-us'],
            ],
            [
                'key' => 'privacy-policy',
                'vi' => ['title' => 'Chính sách bảo mật', 'slug' => 'chinh-sach-bao-mat'],
                'en' => ['title' => 'Privacy Policy', 'slug' => 'privacy-policy'],
            ],
        ];

        foreach ($pages as $data) {
            $page = Page::create(['key' => $data['key'], 'status' => ContentStatus::Published]);

            foreach (['vi', 'en'] as $locale) {
                $page->translations()->create([
                    'locale' => $locale,
                    'title' => $data[$locale]['title'],
                    'slug' => $data[$locale]['slug'],
                    'body' => "<p>{$data[$locale]['title']}</p>",
                ]);
            }
        }
    }

    private function seedMenus(): void
    {
        $header = [
            ['vi' => ['Ngành nghề', '/nganh-nghe'], 'en' => ['Industries', '/en/industries']],
            ['vi' => ['Dịch vụ', '/dich-vu'], 'en' => ['Services', '/en/services']],
            ['vi' => ['Tin tức', '/tin-tuc'], 'en' => ['Insights', '/en/insights']],
            ['vi' => ['Về chúng tôi', '/ve-chung-toi'], 'en' => ['About Us', '/en/about-us']],
        ];

        $footer = [
            ['vi' => ['Chính sách bảo mật', '/chinh-sach-bao-mat'], 'en' => ['Privacy Policy', '/en/privacy-policy']],
        ];

        foreach ([MenuLocation::Header->value => $header, MenuLocation::Footer->value => $footer] as $location => $items) {
            foreach ($items as $order => $item) {
                $menuItem = MenuItem::create(['location' => $location, 'sort_order' => $order]);

                foreach (['vi', 'en'] as $locale) {
                    $menuItem->translations()->create([
                        'locale' => $locale,
                        'label' => $item[$locale][0],
                        'url' => $item[$locale][1],
                    ]);
                }
            }
        }
    }

    private function seedSettings(): void
    {
        $settings = [
            'hotline' => '1900 1234',
            'email' => 'contact@taida.vn',
            'address' => [
                'vi' => 'Tầng 12, Toà nhà ABC, Quận 1, TP. Hồ Chí Minh',
                'en' => '12th Floor, ABC Tower, District 1, Ho Chi Minh City',
            ],
            'hero' => [
                'vi' => ['title' => 'Chất lượng toàn diện. Đảm bảo.', 'subtitle' => 'Chúng tôi mang đến giải pháp đảm bảo chất lượng toàn diện với sự chính xác, nhanh chóng và tận tâm.'],
                'en' => ['title' => 'Total Quality. Assured.', 'subtitle' => 'We deliver total quality assurance with precision, pace and passion.'],
            ],
            'social' => [
                'linkedin' => 'https://www.linkedin.com/',
                'facebook' => 'https://www.facebook.com/',
                'youtube' => 'https://www.youtube.com/',
            ],
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
