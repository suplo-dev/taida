interface SitemapEntry {
  type: 'service' | 'industry' | 'post' | 'page'
  id: number
  slugs: Partial<Record<'vi' | 'en', string>>
  updatedAt: string | null
}

/**
 * Path prefixes per content type. The two locales use different words in the
 * URL, so each entry produces one URL per locale rather than a single path
 * that @nuxtjs/sitemap could translate on its own.
 */
const PREFIXES: Record<SitemapEntry['type'], { vi: string, en: string }> = {
  service: { vi: '/dich-vu/', en: '/en/services/' },
  industry: { vi: '/nganh-nghe/', en: '/en/industries/' },
  post: { vi: '/tin-tuc/', en: '/en/insights/' },
  page: { vi: '/', en: '/en/' },
}

/** Listing and landing pages, which have no database record behind them. */
const STATIC_PAGES: { vi: string, en: string, priority: number }[] = [
  { vi: '/', en: '/en', priority: 1 },
  { vi: '/dich-vu', en: '/en/services', priority: 0.9 },
  { vi: '/nganh-nghe', en: '/en/industries', priority: 0.9 },
  { vi: '/tin-tuc', en: '/en/insights', priority: 0.8 },
]

export default defineSitemapEventHandler(async () => {
  const config = useRuntimeConfig()

  const { data } = await $fetch<{ data: SitemapEntry[] }>('/api/v1/sitemap-urls', {
    baseURL: config.apiBase,
  })

  const urls = STATIC_PAGES.flatMap(page => localisedPair(page.vi, page.en, null, page.priority))

  for (const entry of data) {
    const prefix = PREFIXES[entry.type]

    if (!prefix || !entry.slugs.vi) {
      continue
    }

    urls.push(...localisedPair(
      prefix.vi + entry.slugs.vi,
      entry.slugs.en ? prefix.en + entry.slugs.en : null,
      entry.updatedAt,
      entry.type === 'post' ? 0.6 : 0.7,
    ))
  }

  return urls
})

/**
 * Emits both locale URLs, each pointing at the other through `alternatives`
 * so Google can pair them up.
 */
function localisedPair(vi: string, en: string | null, lastmod: string | null, priority: number) {
  const alternatives = [
    { hreflang: 'vi', href: vi },
    ...(en ? [{ hreflang: 'en', href: en }] : []),
    { hreflang: 'x-default', href: vi },
  ]

  const shared = { lastmod: lastmod ?? undefined, priority, _i18nTransform: false }

  return [
    { loc: vi, ...shared, alternatives },
    ...(en ? [{ loc: en, ...shared, alternatives }] : []),
  ]
}
