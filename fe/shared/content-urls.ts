/**
 * Đường dẫn công khai của nội dung, dùng chung cho hai chỗ phải khớp nhau tuyệt
 * đối: nguồn sitemap (`server/api/__sitemap__/urls.ts`) và danh sách route đem
 * prerender (`nitro.hooks` trong `nuxt.config.ts`).
 *
 * Trước đây chỉ có sitemap biết bản đồ này, còn bản build thì đi dò link. Hai
 * cách cho ra hai kết quả khác nhau ngay khi một danh sách có phân trang: nút
 * chuyển trang của /tin-tuc là <button> gọi router chứ không phải thẻ <a>, nên
 * crawler dừng ở 9 bài đầu trong khi sitemap khai đủ 10 — bài thứ 10 được hứa
 * với Google ở một địa chỉ không có file nào.
 */

/** Theo đúng thứ tự khai trong `i18n.locales`; phần tử đầu là mặc định. */
export const LOCALES = ['vi', 'en', 'zh'] as const

export type Locale = typeof LOCALES[number]

export const DEFAULT_LOCALE: Locale = 'vi'

/**
 * Ngôn ngữ mà một bản ghi mượn slug khi chính nó chưa được dịch, gần nhất
 * trước. Phải khớp từng chữ với `app.locale_fallbacks` bên API: API quyết định
 * địa chỉ nào mở được, còn đây quyết định địa chỉ nào được dựng thành file và
 * khai vào sitemap. Lệch nhau là bản build đi theo một link không có trang.
 *
 * Tiếng Trung mượn tiếng Anh trước tiếng Việt, nên /zh/about-us là địa chỉ
 * tiếng Trung của trang chỉ mới có bản Việt và Anh — cùng slug với
 * /en/about-us, chỉ khác tiền tố.
 */
export const LOCALE_FALLBACKS: Record<Locale, Locale[]> = {
  vi: [],
  en: ['vi'],
  zh: ['en', 'vi'],
}

/**
 * Ngôn ngữ không có địa chỉ riêng: nó dùng đúng slug của ngôn ngữ đầu tiên
 * trong chuỗi dự phòng, và admin không mở ô cho sửa. Phải khớp với
 * `app.mirrored_slug_locales` bên API — đó là bên thật sự ghi vào CSDL.
 *
 * Tiếng Trung ở đây vì `Str::slug()` bỏ hết ký tự Hán: 质量保证 rút lại thành
 * chuỗi rỗng, không còn gì để dựng địa chỉ.
 */
export const MIRRORED_SLUG_LOCALES: Locale[] = ['zh']

/** Ngôn ngữ mà `locale` soi slug, hoặc `null` nếu nó có slug riêng. */
export function slugMirrorSource(locale: Locale): Locale | null {
  return MIRRORED_SLUG_LOCALES.includes(locale) ? (LOCALE_FALLBACKS[locale][0] ?? null) : null
}

/**
 * Slug mà một bản ghi trả lời ở một ngôn ngữ: bản dịch của chính nó nếu có,
 * không thì đi xuống chuỗi dự phòng.
 */
export function resolveSlug(
  slugs: Partial<Record<Locale, string>> | Record<string, string>,
  locale: Locale,
): string | undefined {
  for (const candidate of [locale, ...LOCALE_FALLBACKS[locale]]) {
    const slug = slugs[candidate]

    if (slug) {
      return slug
    }
  }

  return undefined
}

/**
 * Thẻ ngôn ngữ cho `hreflang`, khớp với `language` trong `i18n.locales`. Đây là
 * thứ Google đọc, không phải mã locale nội bộ: `zh` một mình không nói được là
 * giản thể hay phồn thể.
 */
/** Tên ngôn ngữ để in ra log build. Admin có bản riêng trong `useAdminResource`. */
export const LOCALE_NAMES: Record<Locale, string> = {
  vi: 'Tiếng Việt',
  en: 'English',
  zh: '中文',
}

export const HREFLANG: Record<Locale, string> = {
  vi: 'vi',
  en: 'en',
  zh: 'zh-Hans',
}

export type ContentType = 'service' | 'industry' | 'post' | 'page'

/** Khớp với enum `SiteRoute` bên API — đó là bên phát ra các khoá này. */
export type SiteRoute = 'home' | 'services' | 'industries' | 'insights' | 'search'

/**
 * Đích đến của một mục menu, đúng như API trả về. Khớp với `MenuTarget` bên API.
 */
export type MenuTarget =
  | { type: 'route', route: SiteRoute }
  | { type: 'external', url: string }
  | { type: ContentType, slug: string }

/**
 * @nuxtjs/sitemap khai `priority` là union của mười một giá trị một chữ số thập
 * phân chứ không phải `number` — giá trị khác bị chặn ngay lúc build.
 */
export type Priority = 0 | 0.1 | 0.2 | 0.3 | 0.4 | 0.5 | 0.6 | 0.7 | 0.8 | 0.9 | 1

/**
 * Trang không có bản ghi nào trong CSDL: trang chủ và các trang danh sách.
 *
 * Danh sách này từng nằm rải ở ba nơi — nguồn sitemap, danh sách gieo cho
 * prerender, và trong đầu người sửa menu. Gộp về đây vì cả ba phải nói cùng một
 * điều: đây là những địa chỉ site có mà không đi ra từ `sitemap-urls`.
 *
 * `sitemap: null` nghĩa là trang mở được nhưng không khai với Google.
 */
export const STATIC_ROUTES: { key: SiteRoute, paths: Record<Locale, string>, sitemap: Priority | null }[] = [
  { key: 'home', paths: { vi: '/', en: '/en', zh: '/zh' }, sitemap: 1 },
  { key: 'services', paths: { vi: '/dich-vu', en: '/en/services', zh: '/zh/services' }, sitemap: 0.9 },
  { key: 'industries', paths: { vi: '/nganh-nghe', en: '/en/industries', zh: '/zh/industries' }, sitemap: 0.9 },
  { key: 'insights', paths: { vi: '/tin-tuc', en: '/en/insights', zh: '/zh/insights' }, sitemap: 0.8 },
  // Trang tìm kiếm: người đọc tới được, nhưng một trang kết quả rỗng thì không
  // có gì để Google đọc.
  { key: 'search', paths: { vi: '/tim-kiem', en: '/en/search', zh: '/zh/search' }, sitemap: null },
]

const ROUTE_PATHS = new Map(STATIC_ROUTES.map(route => [route.key, route.paths]))

/**
 * Địa chỉ của một mục menu, ở ngôn ngữ đang hiển thị.
 *
 * API đặt tên đích đến — "trang danh sách tin tức", "bản ghi dịch vụ có slug
 * này" — còn đây dựng ra đường dẫn, vì đây là phía biết /dich-vu khác
 * /en/services. Trước kia URL được gõ tay và lưu sẵn cho từng ngôn ngữ, nên mỗi
 * mục menu là một cơ hội quên tiền tố `/zh`; mà bản build tĩnh đi theo chính
 * những link đó, nên một ký tự sai là hỏng cả lần publish.
 *
 * `null` nghĩa là mục chưa chọn đích. API đã loại chúng khỏi menu công khai;
 * đây là lưới thứ hai, để không bao giờ render ra một thẻ <a href="undefined">.
 */
export function menuHref(target: MenuTarget | null | undefined, locale: Locale): string | null {
  if (!target) {
    return null
  }

  if (target.type === 'external') {
    return target.url ?? null
  }

  if (target.type === 'route') {
    return ROUTE_PATHS.get(target.route)?.[locale] ?? null
  }

  return PREFIXES[target.type][locale] + target.slug
}

/**
 * Tiền tố đường dẫn theo loại nội dung. Mỗi ngôn ngữ dùng từ khác nhau trong
 * URL, nên mỗi bản ghi sinh ra một địa chỉ cho mỗi ngôn ngữ chứ không phải một
 * đường dẫn chung để @nuxtjs/sitemap tự dịch.
 */
export const PREFIXES: Record<ContentType, Record<Locale, string>> = {
  service: { vi: '/dich-vu/', en: '/en/services/', zh: '/zh/services/' },
  industry: { vi: '/nganh-nghe/', en: '/en/industries/', zh: '/zh/industries/' },
  post: { vi: '/tin-tuc/', en: '/en/insights/', zh: '/zh/insights/' },
  page: { vi: '/', en: '/en/', zh: '/zh/' },
}

export interface ContentEntry {
  type: ContentType
  id: number
  slugs: Partial<Record<Locale, string>>
  updatedAt: string | null
}

/**
 * Địa chỉ của một bản ghi ở TỪNG ngôn ngữ đã có bản dịch thật.
 *
 * Đây là tập dùng cho sitemap: bản chưa dịch vẫn xem được nhưng mang `noindex`,
 * nên khai nó vào sitemap là tự mâu thuẫn với chính mình.
 */
export function translatedPaths(entry: ContentEntry): Partial<Record<Locale, string>> {
  const paths: Partial<Record<Locale, string>> = {}

  for (const locale of LOCALES) {
    const slug = entry.slugs[locale]

    if (slug) {
      paths[locale] = PREFIXES[entry.type][locale] + slug
    }
  }

  return paths
}

/**
 * Mọi địa chỉ mà site THẬT SỰ liên kết tới, kể cả bản chưa dịch: trang danh sách
 * dưới /zh hiển thị bản ghi chưa dịch bằng tên và slug mượn được (tiếng Anh, hết
 * mới tới tiếng Việt), và link đó phải mở được khi tải thẳng chứ không chỉ khi
 * điều hướng trong trình duyệt.
 */
export function allPaths(entry: ContentEntry): string[] {
  return Object.values(resolvedPaths(entry))
}

/**
 * Như `allPaths` nhưng giữ lại ngôn ngữ của từng địa chỉ, để chỗ nào cần biết
 * "cùng bản ghi này ở tiếng Trung là URL nào" thì không phải đoán theo thứ tự.
 */
export function resolvedPaths(entry: ContentEntry): Partial<Record<Locale, string>> {
  const paths: Partial<Record<Locale, string>> = {}

  for (const locale of LOCALES) {
    const slug = resolveSlug(entry.slugs, locale)

    if (slug) {
      paths[locale] = PREFIXES[entry.type][locale] + slug
    }
  }

  return paths
}
