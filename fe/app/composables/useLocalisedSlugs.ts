import type { LocalisedSlugs } from '~/types/api'

/**
 * Registers this record's slug in every locale with the router.
 *
 * Without it, `switchLocalePath` and the `hreflang` alternates reuse whatever
 * slug is in the current URL. The two languages never share a slug, so the
 * language switcher on every detail page pointed at a URL that does not exist.
 */
export function useLocalisedSlugs(slugs: MaybeRefOrGetter<LocalisedSlugs | undefined>) {
  const setI18nParams = useSetI18nParams()

  watchEffect(() => {
    const value = toValue(slugs)

    if (!value) {
      return
    }

    setI18nParams(Object.fromEntries(
      Object.entries(value).map(([locale, slug]) => [locale, { slug }]),
    ))
  })
}
