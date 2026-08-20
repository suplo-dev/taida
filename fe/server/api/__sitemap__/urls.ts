import type { ContentEntry, Locale } from '~~/shared/content-urls'
import { DEFAULT_LOCALE, HREFLANG, LOCALES, translatedPaths } from '~~/shared/content-urls'

/**
 * @nuxtjs/sitemap types `priority` as a union of the eleven values with one
 * decimal, not as `number` — anything else is rejected at build time. Naming it
 * once keeps that constraint in one place instead of at every call site.
 */
type Priority = 0 | 0.1 | 0.2 | 0.3 | 0.4 | 0.5 | 0.6 | 0.7 | 0.8 | 0.9 | 1

/** Listing and landing pages, which have no database record behind them. */
const STATIC_PAGES: { paths: Record<Locale, string>, priority: Priority }[] = [
  { paths: { vi: '/', en: '/en', zh: '/zh' }, priority: 1 },
  { paths: { vi: '/dich-vu', en: '/en/services', zh: '/zh/services' }, priority: 0.9 },
  { paths: { vi: '/nganh-nghe', en: '/en/industries', zh: '/zh/industries' }, priority: 0.9 },
  { paths: { vi: '/tin-tuc', en: '/en/insights', zh: '/zh/insights' }, priority: 0.8 },
]

export default defineSitemapEventHandler(async () => {
  const config = useRuntimeConfig()

  const { data } = await $fetch<{ data: ContentEntry[] }>('/api/v1/sitemap-urls', {
    baseURL: config.apiBase,
  })

  const urls = STATIC_PAGES.flatMap(page => localised(page.paths, null, page.priority))

  for (const entry of data) {
    if (!entry.slugs[DEFAULT_LOCALE]) {
      continue
    }

    // Chỉ ngôn ngữ nào ĐÃ CÓ bản dịch thật mới được khai. Bản chưa dịch vẫn xem
    // được trên site (nội dung rơi về tiếng Việt) nhưng mang `noindex`, nên đưa
    // nó vào đây là hai nơi nói hai điều khác nhau về cùng một trang.
    urls.push(...localised(translatedPaths(entry), entry.updatedAt, entry.type === 'post' ? 0.6 : 0.7))
  }

  return urls
})

/**
 * Emits one URL per translated locale, each pointing at all the others through
 * `alternatives` so Google can pair them up.
 */
function localised(
  paths: Partial<Record<Locale, string>>,
  lastmod: string | null,
  priority: Priority,
) {
  const available = LOCALES.filter(locale => Boolean(paths[locale]))
  const fallback = paths[DEFAULT_LOCALE]

  const alternatives = [
    ...available.map(locale => ({ hreflang: HREFLANG[locale], href: paths[locale] as string })),
    // x-default là địa chỉ dành cho người đọc không khớp ngôn ngữ nào — bản
    // tiếng Việt, vì đó là bản luôn tồn tại và đầy đủ nhất.
    ...(fallback ? [{ hreflang: 'x-default', href: fallback }] : []),
  ]

  const shared = { lastmod: lastmod ?? undefined, priority, _i18nTransform: false }

  return available.map(locale => ({ loc: paths[locale] as string, ...shared, alternatives }))
}
