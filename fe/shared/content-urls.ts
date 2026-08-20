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
 * Thẻ ngôn ngữ cho `hreflang`, khớp với `language` trong `i18n.locales`. Đây là
 * thứ Google đọc, không phải mã locale nội bộ: `zh` một mình không nói được là
 * giản thể hay phồn thể.
 */
export const HREFLANG: Record<Locale, string> = {
  vi: 'vi',
  en: 'en',
  zh: 'zh-Hans',
}

export type ContentType = 'service' | 'industry' | 'post' | 'page'

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
 * dưới /zh hiển thị bản ghi chưa dịch bằng tên và slug tiếng Việt, và link đó
 * phải mở được khi tải thẳng chứ không chỉ khi điều hướng trong trình duyệt.
 */
export function allPaths(entry: ContentEntry): string[] {
  const fallback = entry.slugs[DEFAULT_LOCALE]

  if (!fallback) {
    return []
  }

  return LOCALES.map(locale => PREFIXES[entry.type][locale] + (entry.slugs[locale] ?? fallback))
}
