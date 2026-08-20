import type { LocalisedSlugs } from '~/types/api'

/**
 * Registers this record's slug in every locale with the router.
 *
 * Without it, `switchLocalePath` and the `hreflang` alternates reuse whatever
 * slug is in the current URL. The languages never share a slug, so the language
 * switcher on every detail page pointed at a URL that does not exist.
 *
 * It is also where a page finds out it is a translation that does not exist
 * yet. A record with no row for the active locale still renders — the API falls
 * back to the primary one — so /zh/services/… is readable Vietnamese content at
 * a Chinese address. Useful for a reader who followed the switcher, duplicate
 * content for a search engine. Such a page is kept out of the sitemap (see
 * server/api/__sitemap__/urls.ts) and marked `noindex` here, so the two say the
 * same thing. `follow` stays on: the links out of it are worth crawling, and
 * the page starts being indexed the moment an editor writes the translation.
 */
export function useLocalisedSlugs(slugs: MaybeRefOrGetter<LocalisedSlugs | undefined>) {
  const setI18nParams = useSetI18nParams()
  const { locale } = useI18n()
  const robots = useRobotsRule()
  let registered = ''

  watch(
    [() => toValue(slugs), locale],
    ([value, active]) => {
      // `undefined` là "chưa biết" (dữ liệu chưa về), khác hẳn "đã biết là chưa
      // dịch" — đừng noindex nhầm một trang chỉ vì nó đang tải.
      if (!value) {
        return
      }

      robots.value = value[active as keyof LocalisedSlugs] ? true : 'noindex, follow'
    },
    { immediate: true },
  )

  // Tách khỏi watcher trên vì cần `flush: 'post'`: i18n phải nhận tham số sau khi
  // trang render xong, còn thẻ robots thì không đợi gì cả.
  watch(
    () => toValue(slugs),
    (value) => {
      if (!value) {
        return
      }

      const next = JSON.stringify(value)

      if (next === registered) {
        return
      }

      registered = next

      setI18nParams(Object.fromEntries(
        Object.entries(value).map(([code, slug]) => [code, { slug }]),
      ))
    },
    { immediate: true, flush: 'post' },
  )
}
