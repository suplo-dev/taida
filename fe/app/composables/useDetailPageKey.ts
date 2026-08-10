/**
 * Remount detail pages when the URL path changes.
 *
 * Vue reuses the same `[slug].vue` instance when navigating between records
 * (e.g. two services). Without a page key the setup block does not re-run,
 * stale data lingers, and `useLocalisedSlugs` + `strictSeo` can spin forever
 * updating i18n head tags.
 */
export function useDetailPageKey() {
  definePageMeta({
    key: route => route.path,
  })
}
