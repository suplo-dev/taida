import type { MenuTarget, SiteRoute } from '~~/shared/content-urls'

/*
 * Đích đến của menu được định nghĩa cùng chỗ với bản đồ đường dẫn, vì hai thứ
 * đó phải khớp nhau; ở đây chỉ tái xuất để phần còn lại của app dùng một cửa.
 */
export type { MenuTarget, SiteRoute }

/** Giá trị của cột `target_type`, khớp với enum `MenuTarget` bên API. */
export type MenuTargetType = MenuTarget['type']

export type Locale = 'vi' | 'en' | 'zh'

export type ContentStatus = 'draft' | 'published'

export interface AdminUser {
  id: number
  name: string
  email: string
  role: 'admin' | 'editor'
}

export interface PingResponse {
  ok: boolean
  locale: Locale
  app: string
}

export interface Media {
  id: number
  url: string
  thumbUrl: string | null
  alt: string | null
  width: number | null
  height: number | null
}

/** Mã QR liên hệ trong cấu hình site — nhiều mã, mỗi mã một nhãn. */
export interface ContactQr {
  label: string
  media: Media
}

/** Envelope Laravel API Resources always respond with. */
export interface Envelope<T> {
  data: T
}

export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

/* -------------------------------------------------------------------------- */
/* Public site                                                                 */
/* -------------------------------------------------------------------------- */

export interface Service {
  id: number
  slug: string
  name: string
  excerpt: string | null
  icon: string | null
  isFeatured: boolean
  cover: Media | null
  children?: Service[]
}

export interface Industry extends Omit<Service, 'children'> {
  children?: Industry[]
}

/** A record's slug in each locale, so the language switcher can build the other one's URL. */
export type LocalisedSlugs = Record<string, string>

export interface SeoMeta {
  title: string | null
  description: string | null
}

export interface ServiceDetail extends Service {
  slugs: LocalisedSlugs
  body: string | null
  parent: Service | null
  industries: Industry[]
  meta: SeoMeta
}

export interface IndustryDetail extends Industry {
  slugs: LocalisedSlugs
  body: string | null
  parent: Industry | null
  services: Service[]
  meta: SeoMeta
}

export interface Category {
  id: number
  slug: string
  name: string
  description: string | null
  postCount?: number
}

export interface Tag {
  id: number
  slug: string
  name: string
}

export interface Post {
  id: number
  slug: string
  title: string
  excerpt: string | null
  isFeatured: boolean
  publishedAt: string | null
  cover: Media | null
  category: Category | null
}

export interface PostDetail extends Post {
  slugs: LocalisedSlugs
  body: string | null
  author: { name: string } | null
  tags: Tag[]
  meta: SeoMeta
}

export interface Page {
  id: number
  key: string
  slug: string
  slugs: LocalisedSlugs
  title: string
  body: string | null
  cover: Media | null
  meta: SeoMeta
}

export interface MenuItem {
  id: number
  label: string
  /**
   * Đích đến, không phải địa chỉ: API đặt tên nơi cần tới, `menuHref` dựng ra
   * đường dẫn cho ngôn ngữ đang hiển thị. `null` là mục chưa chọn đích — API đã
   * lọc chúng khỏi menu công khai, kiểu vẫn cho phép để bên nhận không quên.
   */
  target: MenuTarget | null
  opensInNewTab: boolean
  children: MenuItem[]
}

/** Mục menu đã có đường dẫn, do `useSiteData` dựng — xem `resolveLinks`. */
export interface MenuLink extends Omit<MenuItem, 'children'> {
  href: string
  children: MenuLink[]
}

export interface SearchResults {
  services: Service[]
  industries: Industry[]
  posts: Post[]
}

/* -------------------------------------------------------------------------- */
/* Admin — carries every locale at once                                        */
/* -------------------------------------------------------------------------- */

export type Translations<T> = Partial<Record<Locale, Partial<T>>>

export interface ContentTranslation {
  name: string
  slug: string
  excerpt: string | null
  body: string | null
  meta_title: string | null
  meta_description: string | null
}

export interface PostTranslation extends Omit<ContentTranslation, 'name'> {
  title: string
}

export interface AdminService {
  id: number
  parent_id: number | null
  cover_media_id: number | null
  cover: Media | null
  icon: string | null
  sort_order: number
  is_featured: boolean
  status: ContentStatus
  published_at: string | null
  industry_ids?: number[]
  children?: AdminService[]
  translations: Translations<ContentTranslation>
}

export interface AdminIndustry extends Omit<AdminService, 'industry_ids' | 'children'> {
  service_ids?: number[]
  children?: AdminIndustry[]
}

export interface AdminPost {
  id: number
  category_id: number | null
  cover_media_id: number | null
  cover: Media | null
  author: string | null
  is_featured: boolean
  status: ContentStatus
  published_at: string | null
  tag_ids?: number[]
  translations: Translations<PostTranslation>
}

export interface AdminCategory {
  id: number
  sort_order: number
  posts_count?: number
  translations: Translations<{ name: string, slug: string, description: string | null }>
}

export interface AdminTag {
  id: number
  posts_count?: number
  translations: Translations<{ name: string, slug: string }>
}

export interface AdminPage {
  id: number
  key: string
  cover_media_id: number | null
  cover: Media | null
  status: ContentStatus
  translations: Translations<{
    title: string
    slug: string
    body: string | null
    meta_title: string | null
    meta_description: string | null
  }>
}

export interface AdminMenuItem {
  id: number
  sort_order: number
  opens_in_new_tab: boolean
  target_type: MenuTargetType
  target_route: SiteRoute | null
  target_id: number | null
  external_url: string | null
  children: AdminMenuItem[]
  translations: Translations<{ label: string }>
}

/** Một lựa chọn trong bộ chọn đích ở màn Menu — xem `/admin/menu-targets`. */
export interface MenuTargetOption {
  type: MenuTargetType
  route: SiteRoute | null
  id: number | null
  label: string
  published: boolean
}
