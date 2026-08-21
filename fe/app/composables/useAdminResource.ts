import type { Envelope, Locale } from '~/types/api'

/** Số câu lỗi hiển thị trong toast trước khi gộp phần còn lại thành "… và N lỗi khác". */
const MAX_TOAST_ERRORS = 4

/**
 * Nhãn tiếng Việt cho các trường, dùng khi API trả về nguyên khoá dịch thay vì
 * câu hoàn chỉnh — xem `readableError`.
 */
const FIELD_LABELS: Record<string, string> = {
  body: 'Nội dung',
  cover_media_id: 'Ảnh đại diện',
  category_id: 'Danh mục',
  excerpt: 'Mô tả ngắn',
  icon: 'Biểu tượng',
  industry_ids: 'Lĩnh vực',
  key: 'Mã trang',
  meta_description: 'Meta description',
  meta_title: 'Meta title',
  name: 'Tên',
  parent_id: 'Mục cha',
  published_at: 'Thời điểm đăng',
  service_ids: 'Dịch vụ',
  slug: 'Đường dẫn',
  sort_order: 'Thứ tự',
  status: 'Trạng thái',
  tag_ids: 'Thẻ',
  title: 'Tiêu đề',
}

/** Câu mô tả cho từng rule của Laravel, dùng khi bản dịch phía API bị thiếu. */
const RULE_MESSAGES: Record<string, string> = {
  required: 'không được để trống',
  regex: 'sai định dạng',
  unique: 'đã tồn tại',
  exists: 'không tồn tại',
  max: 'vượt quá độ dài cho phép',
  min: 'chưa đủ độ dài tối thiểu',
  string: 'phải là chuỗi ký tự',
  integer: 'phải là số nguyên',
  numeric: 'phải là số',
  boolean: 'phải là true hoặc false',
  array: 'sai định dạng',
  date: 'không phải ngày hợp lệ',
  email: 'không phải email hợp lệ',
  url: 'không phải đường dẫn hợp lệ',
  image: 'phải là một ảnh',
  mimes: 'sai định dạng tệp',
  file: 'phải là một tệp',
}

/**
 * Lưới an toàn cho trường hợp API trả về nguyên khoá dịch (`validation.regex`,
 * `auth.failed`) vì thiếu file ngôn ngữ: hiển thị khoá thô cho người dùng thì
 * vô nghĩa, nên đổi thành một câu đọc được. Câu đã dịch sẵn thì giữ nguyên.
 */
function readableError(field: string, message: string | undefined): string | undefined {
  if (!message) return undefined
  if (!/^[a-z_]+\.[a-z_.]+$/.test(message)) return message

  const rule = message.split('.')[1] ?? ''
  const name = FIELD_LABELS[field.split('.').pop() ?? ''] ?? 'Giá trị'

  return `${name} ${RULE_MESSAGES[rule] ?? 'không hợp lệ'}.`
}

export const SUPPORTED_LOCALES: Locale[] = ['vi', 'en', 'zh']

/** The locale content must always be authored in, mirroring the API. */
export const PRIMARY_LOCALE: Locale = 'vi'

export const LOCALE_LABELS: Record<Locale, string> = {
  vi: 'Tiếng Việt',
  en: 'English',
  zh: '中文',
}

/**
 * Thin CRUD wrapper over an admin endpoint. Every admin screen has the same
 * shape — load a list, load one record, save it, delete it — so the pages only
 * describe their fields and let this handle the plumbing.
 */
export function useAdminResource<TRecord, TPayload extends Record<string, unknown> = Record<string, unknown>>(endpoint: string) {
  const api = useApi()
  const toast = useToast()

  const saving = ref(false)
  /** Field-level messages returned by Laravel's validator, keyed by input name. */
  const errors = ref<Record<string, string[]>>({})

  function extractErrors(error: unknown): boolean {
    const data = (error as { data?: { errors?: Record<string, string[]>, message?: string } })?.data

    if (data?.errors) {
      errors.value = data.errors

      /*
       * Ô lỗi có thể đang nằm ở tab ngôn ngữ khác hoặc ở panel bên dưới, nên
       * chỉ nói "kiểm tra lại các ô được đánh dấu" là người sửa không biết
       * nhìn vào đâu. Liệt kê luôn vài câu lỗi đầu tiên.
       */
      const messages = Object.entries(data.errors)
        .map(([field, list]) => {
          const message = readableError(field, list[0])
          if (!message) return undefined

          // Nói rõ lỗi thuộc tab ngôn ngữ nào, vì tab đang mở có thể không phải tab lỗi.
          const locale = field.match(/^translations\.([a-z]{2})\./)?.[1] as Locale | undefined
          return locale ? `${LOCALE_LABELS[locale]}: ${message}` : message
        })
        .filter((message): message is string => Boolean(message))

      toast.add({
        title: 'Kiểm tra lại các ô được đánh dấu',
        description: messages.slice(0, MAX_TOAST_ERRORS).join('\n')
          + (messages.length > MAX_TOAST_ERRORS ? `\n… và ${messages.length - MAX_TOAST_ERRORS} lỗi khác` : ''),
        color: 'error',
      })
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
    return readableError(field, errors.value[field]?.[0])
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
