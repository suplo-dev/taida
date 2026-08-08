import type { Envelope, Media, MenuItem } from '~/types/api'

export interface SiteSettings {
  hotline?: string
  email?: string
  address?: string
  hero?: { title?: string, subtitle?: string }
  social?: Record<string, string>
  /** Null when the admin has not uploaded one; SiteLogo falls back to the bundled mark. */
  logo?: Media | null
}

/**
 * Site chrome — settings plus both menus — fetched once per locale and shared
 * by the header, the footer and any page that needs it.
 */
export function useSiteData() {
  const api = useApi()
  const { locale } = useI18n()

  return useAsyncData(
    `site:chrome:${locale.value}`,
    async () => {
      const [settings, header, footer] = await Promise.all([
        api<Envelope<SiteSettings>>('/settings'),
        api<Envelope<MenuItem[]>>('/menus/header'),
        api<Envelope<MenuItem[]>>('/menus/footer'),
      ])

      return { settings: settings.data, header: header.data, footer: footer.data }
    },
    { watch: [locale] },
  )
}
