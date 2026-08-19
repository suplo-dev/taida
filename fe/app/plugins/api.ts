import type { $Fetch } from 'ofetch'

/**
 * A `$fetch` instance pointed at the Laravel API.
 *
 * It carries the Sanctum session cookie, mirrors the active i18n locale onto
 * every request, and replays the XSRF cookie as a header so state-changing
 * admin calls pass Laravel's CSRF check.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig()

  const api = $fetch.create({
    baseURL: `${import.meta.server ? config.apiBase : config.public.apiBase}/api/v1`,
    credentials: 'include',
    retry: false,
    headers: { Accept: 'application/json' },
    /**
     * Lúc sinh tĩnh thì thử lại, còn trong trình duyệt thì không.
     *
     * `pnpm generate` dựng 90 trang, mỗi trang gọi API vài lần, tất cả trong
     * khoảng một phút vào một shared hosting ở đầu kia Thái Bình Dương. Một cú
     * rớt kết nối trong ngần đó lượt gọi là chuyện sẽ xảy ra, và nó đã xảy ra:
     * bản deploy 19/08 mất `/menus/utility` đúng một lần khi dựng trang chủ, thế
     * là `useSiteData` (dùng `Promise.all`) mất sạch cả settings lẫn ba menu, và
     * trang chủ lên hosting với hero tiếng Anh mặc định, không menu, không
     * hotline. Không có gì báo: lỗi bị `useAsyncData` giữ lại trong `_errors`
     * nên `prerender.failOnError` không nổ, và nitro báo "Total errors: 0".
     *
     * Ở đây `retry` là số lần thử thêm và nó giảm dần qua mỗi lần, nên đặt trong
     * `create` là an toàn — đừng chuyển vào `onRequest`: ofetch chạy lại
     * `onRequest` cho từng lượt thử lại, gán lại ở đó là vòng lặp vô tận.
     *
     * Chỉ áp cho phía server, nơi duy nhất chỉ có lệnh đọc. Trong trình duyệt
     * giữ `retry: false` để một lần bấm Lưu của admin không thành hai lần ghi.
     */
    ...(import.meta.server ? { retry: 3, retryDelay: 500 } : {}),
    onRequest({ options }) {
      const locale = (nuxtApp.$i18n as { locale?: { value: string } } | undefined)?.locale?.value
      if (locale) {
        options.query = { locale, ...options.query }
      }

      if (import.meta.client) {
        const xsrf = useCookie('XSRF-TOKEN', { readonly: true }).value
        if (xsrf) {
          options.headers.set('X-XSRF-TOKEN', xsrf)
        }
      }
    },
  })

  return { provide: { api: api as $Fetch } }
})
