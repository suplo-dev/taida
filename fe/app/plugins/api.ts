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
