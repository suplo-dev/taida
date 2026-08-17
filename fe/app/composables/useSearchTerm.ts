/**
 * The term behind every search field on the page. The header carries one and
 * the search page carries another, and on /tim-kiem they are visible at the
 * same time — editing one while the other still showed the previous term made
 * them look like two unrelated searches. Sharing the state makes them two
 * views of the same field.
 */
export function useSearchTerm() {
  const route = useRoute()

  const term = useState<string>('site:search-term', () => (route.query.q as string) ?? '')

  /**
   * `?q=` stays the source of truth once a search has been submitted: a shared
   * link or a back step refills both fields, and navigating away from the
   * results clears them rather than leaving a stale term in the header.
   */
  watch(() => route.fullPath, () => {
    term.value = (route.query.q as string) ?? ''
  })

  return term
}
