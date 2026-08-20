import type { Envelope, Media, MenuItem } from '~/types/api'

export interface SiteSettings {
  hotline?: string
  email?: string
  address?: string
  hero?: { title?: string, subtitle?: string }
  social?: Record<string, string>
  /** Null when the admin has not uploaded one; SiteLogo falls back to the bundled mark. */
  logo?: Media | null
  /** Ảnh nền hero trang chủ, cũng dùng làm poster khi có `heroVideo`. */
  heroImage?: Media | null
  /** URL video nền hero. Là địa chỉ ngoài chứ không phải media id — xem Setting::URL_KEYS. */
  heroVideo?: string | null
}

/**
 * Site chrome — settings plus every menu — fetched once per locale and shared
 * by the utility bar, the header, the footer and any page that needs it.
 */
export function useSiteData() {
  const api = useApi()
  const { locale } = useI18n()

  return useAsyncData(
    `site:chrome:${locale.value}`,
    async () => {
      const [settings, header, footer, utility] = await Promise.all([
        api<Envelope<SiteSettings>>('/settings'),
        api<Envelope<MenuItem[]>>('/menus/header'),
        api<Envelope<MenuItem[]>>('/menus/footer'),
        api<Envelope<MenuItem[]>>('/menus/utility'),
      ])

      return {
        settings: settings.data,
        header: header.data,
        footer: footer.data,
        utility: utility.data,
      }
    },
    { watch: [locale] },
  )
}
