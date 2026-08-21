import type { Locale } from '~~/shared/content-urls'
import type { ContactQr, Envelope, Media, MenuItem, MenuLink } from '~/types/api'
import { menuHref } from '~~/shared/content-urls'

export interface SiteSettings {
  hotline?: string
  email?: string
  address?: string
  hero?: { title?: string, subtitle?: string }
  /**
   * Chỉ những mạng đang bật và có địa chỉ — endpoint công khai đã lọc sẵn, nên
   * chân trang cứ vẽ hết những gì nhận được.
   */
  social?: Record<string, string>
  /** Mã QR liên hệ (Zalo, WeChat…) đang bật, theo thứ tự biên tập viên xếp. */
  contactQr?: ContactQr[]
  /** Null when the admin has not uploaded one; SiteLogo falls back to the bundled mark. */
  logo?: Media | null
  /** Ảnh nền hero trang chủ, cũng dùng làm poster khi có `heroVideo`. */
  heroImage?: Media | null
  /** URL video nền hero. Là địa chỉ ngoài chứ không phải media id — xem Setting::URL_KEYS. */
  heroVideo?: string | null
}

/**
 * Đổi đích đến mà API trả về thành đường dẫn, một lần cho cả site.
 *
 * Làm ở đây thay vì trong từng template: năm chỗ render menu thì năm chỗ phải
 * nhớ xử lý mục không có đích, và chỗ nào quên sẽ render ra một thẻ <a> trỏ vào
 * hư không — thứ mà bản build tĩnh đi theo rồi dừng lại.
 */
function resolveLinks(items: MenuItem[], locale: Locale): MenuLink[] {
  const links: MenuLink[] = []

  for (const item of items) {
    const href = menuHref(item.target, locale)

    if (href !== null) {
      links.push({ ...item, href, children: resolveLinks(item.children ?? [], locale) })
    }
  }

  return links
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

      const links = (items: MenuItem[]) => resolveLinks(items, locale.value as Locale)

      return {
        settings: settings.data,
        header: links(header.data),
        footer: links(footer.data),
        utility: links(utility.data),
      }
    },
    { watch: [locale] },
  )
}
