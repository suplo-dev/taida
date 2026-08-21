import type { ContentEntry, Locale, Priority } from '~~/shared/content-urls'
import { DEFAULT_LOCALE, HREFLANG, LOCALES, STATIC_ROUTES, translatedPaths } from '~~/shared/content-urls'

export default defineSitemapEventHandler(async () => {
  const config = useRuntimeConfig()

  const { data } = await $fetch<{ data: ContentEntry[] }>('/api/v1/sitemap-urls', {
    baseURL: config.apiBase,
  })

  const urls = STATIC_ROUTES
    .filter(route => route.sitemap !== null)
    .flatMap(route => localised(route.paths, null, route.sitemap as Priority))

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
