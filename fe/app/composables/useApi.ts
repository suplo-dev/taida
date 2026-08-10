import type { UseFetchOptions } from 'nuxt/app'

/**
 * Raw API client. Use for imperative calls (form submits, admin mutations).
 */
export function useApi() {
  return useNuxtApp().$api
}

/**
 * SSR-aware data fetching against the API. Use in pages so the response is
 * rendered on the server and hydrated without a second request.
 *
 * The active locale and the resolved query are part of the cache key,
 * otherwise switching language or filter would replay the previous payload.
 */
export function useApiData<T>(
  url: string | (() => string),
  options: UseFetchOptions<T> = {},
) {
  const { locale } = useI18n()
  const nuxtApp = useNuxtApp()

  const key = computed(() => [
    typeof url === 'function' ? url() : url,
    locale.value,
    JSON.stringify(toValue(options.query) ?? {}),
  ].join(':'))

  return useFetch(url, {
    ...options,
    $fetch: nuxtApp.$api,
    key: () => key.value,
    watch: [key, locale, ...(options.watch === false ? [] : (options.watch ?? []))],
    getCachedData(k, nuxt) {
      return nuxt.payload.data[k] ?? nuxt.static.data[k]
    },
  } as UseFetchOptions<T>)
}
