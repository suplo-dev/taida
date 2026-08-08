import type { Envelope, Locale } from '~/types/api'

export const SUPPORTED_LOCALES: Locale[] = ['vi', 'en']

/** The locale content must always be authored in, mirroring the API. */
export const PRIMARY_LOCALE: Locale = 'vi'

export const LOCALE_LABELS: Record<Locale, string> = {
  vi: 'Tiếng Việt',
  en: 'English',
}

/**
 * Thin CRUD wrapper over an admin endpoint. Every admin screen has the same
 * shape — load a list, load one record, save it, delete it — so the pages only
 * describe their fields and let this handle the plumbing.
 */
export function useAdminResource<TRecord, TPayload = Record<string, unknown>>(endpoint: string) {
  const api = useApi()
  const toast = useToast()

  const saving = ref(false)
  /** Field-level messages returned by Laravel's validator, keyed by input name. */
  const errors = ref<Record<string, string[]>>({})

  function extractErrors(error: unknown): boolean {
    const data = (error as { data?: { errors?: Record<string, string[]>, message?: string } })?.data

    if (data?.errors) {
      errors.value = data.errors
      toast.add({ title: 'Kiểm tra lại các ô được đánh dấu', color: 'error' })
      return true
    }

    toast.add({ title: data?.message ?? 'Đã có lỗi xảy ra', color: 'error' })
    return false
  }

  async function list<T = TRecord[]>(query: Record<string, unknown> = {}) {
    return await api<Envelope<T>>(`/admin/${endpoint}`, { query })
  }

  async function find(id: number | string) {
    const { data } = await api<Envelope<TRecord>>(`/admin/${endpoint}/${id}`)
    return data
  }

  /** Creates when `id` is null, updates otherwise. Returns null on validation failure. */
  async function save(id: number | null, payload: TPayload): Promise<TRecord | null> {
    saving.value = true
    errors.value = {}

    try {
      const { data } = await api<Envelope<TRecord>>(
        id ? `/admin/${endpoint}/${id}` : `/admin/${endpoint}`,
        { method: id ? 'PUT' : 'POST', body: payload },
      )

      toast.add({ title: 'Đã lưu', color: 'success' })
      return data
    }
    catch (error) {
      extractErrors(error)
      return null
    }
    finally {
      saving.value = false
    }
  }

  async function remove(id: number): Promise<boolean> {
    try {
      await api(`/admin/${endpoint}/${id}`, { method: 'DELETE' })
      toast.add({ title: 'Đã xoá', color: 'success' })
      return true
    }
    catch (error) {
      extractErrors(error)
      return false
    }
  }

  /** First message for a field, e.g. `translations.vi.name`. */
  function error(field: string): string | undefined {
    return errors.value[field]?.[0]
  }

  return { saving, errors, error, list, find, save, remove }
}

/**
 * Builds an empty translations object so form inputs always have something
 * to bind to, whichever locale tab the editor opens first.
 */
export function emptyTranslations<T extends Record<string, unknown>>(fields: T): Record<Locale, T> {
  return Object.fromEntries(
    SUPPORTED_LOCALES.map(locale => [locale, { ...fields }]),
  ) as Record<Locale, T>
}
