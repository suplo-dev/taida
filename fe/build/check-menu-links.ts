import type { ContentEntry, Locale, MenuTarget } from '../shared/content-urls'
import { LOCALE_NAMES, LOCALES, menuHref, resolvedPaths, STATIC_ROUTES } from '../shared/content-urls'

/**
 * Chặn bản build ngay khi có link menu trỏ tới địa chỉ không tồn tại.
 *
 * Từ khi menu lưu đích đến thay vì URL gõ tay, đây không còn là chỗ bắt lỗi
 * chính tả — API đã không cho chọn một bản ghi không tồn tại, và tự bỏ mục trỏ
 * tới bản ghi đã xoá hoặc đang là nháp. Nó ở lại làm một bất biến: hai phía
 * dựng địa chỉ theo hai bản đồ riêng (`app.locale_fallbacks` bên API,
 * `LOCALE_FALLBACKS` bên này), và ngày chúng lệch nhau thì mọi link menu đều
 * sai cùng lúc. Rẻ, và đáng ra phải luôn xanh.
 *
 * Soát ở `prerender:routes` vì đó là lúc đã biết đủ tập route sắp dựng mà vẫn
 * còn trước bước crawl — thứ vừa tốn vài phút vừa báo lỗi bằng vài nghìn dòng
 * "Linked from". Soát bằng một danh sách địa chỉ chép riêng thì chính danh sách
 * đó sẽ lệch, và một checker lệch còn tệ hơn không có.
 */

/** Khớp với `MenuLocation` bên API. */
const LOCATIONS = ['header', 'footer', 'utility'] as const

interface MenuItem {
  label: string
  target: MenuTarget | null
  children?: MenuItem[]
}

interface Problem {
  locale: Locale
  location: string
  label: string
  url: string
  suggestion?: string
}

/**
 * `fetch` của Node chứ không phải `ofetch`: file này được nạp từ nuxt.config,
 * nơi không có `$fetch` của runtime, và thêm một phụ thuộc chỉ để gọi hai
 * endpoint JSON là không đáng.
 */
async function get<T>(apiBase: string, path: string): Promise<T> {
  const response = await fetch(new URL(path, apiBase))

  if (!response.ok) {
    throw new Error(`API trả về ${response.status} cho ${path} — không soát được link menu.`)
  }

  return await response.json() as T
}

/**
 * Link ra ngoài site tĩnh: mạng xã hội, `mailto:`, `tel:`, neo trong trang.
 * Không có file nào để đối chiếu, và crawler cũng không đi theo.
 */
function isExternal(url: string): boolean {
  return /^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i.test(url)
}

/** Bỏ query, hash và dấu `/` cuối, để `/tin-tuc/` và `/tin-tuc?page=2` khớp `/tin-tuc`. */
function normalise(url: string): string {
  // `split` luôn trả về ít nhất một phần tử, nhưng `noUncheckedIndexedAccess`
  // không biết điều đó — giá trị mặc định là để chiều tsc, không phải nhánh thật.
  const [path = url] = url.split(/[?#]/)

  return path.length > 1 ? path.replace(/\/+$/, '') : path
}

export async function checkMenuLinks(routes: Set<string>, apiBase: string): Promise<void> {
  const valid = new Set([...routes].map(normalise))

  /*
   * Địa chỉ mà một mục menu có lý do trỏ tới: trang chủ, các trang danh sách và
   * các trang tĩnh. Cố tình không gồm từng dịch vụ / bài viết — menu không trỏ
   * tới chúng, và in ra vài chục dòng thì người đọc lỗi bỏ qua cả khối.
   */
  const targets: Record<Locale, string[]> = { vi: [], en: [], zh: [] }

  /*
   * Cùng một đích đến ở cả ba ngôn ngữ, để gợi ý được "ý bạn là /zh/about-us?"
   * khi biên tập viên dán địa chỉ của ngôn ngữ khác hoặc quên tiền tố.
   */
  const siblings = new Map<string, Partial<Record<Locale, string>>>()

  const register = (paths: Partial<Record<Locale, string>>) => {
    for (const path of Object.values(paths)) {
      siblings.set(normalise(path), paths)
    }
  }

  const offer = (paths: Partial<Record<Locale, string>>) => {
    register(paths)

    for (const locale of LOCALES) {
      const path = paths[locale]

      if (path) {
        targets[locale].push(path)
      }
    }
  }

  for (const route of STATIC_ROUTES) {
    offer(route.paths)
  }

  const { data: entries } = await get<{ data: ContentEntry[] }>(apiBase, '/api/v1/sitemap-urls')

  for (const entry of entries) {
    const paths = resolvedPaths(entry)

    // Trang tĩnh thì đáng gợi ý; dịch vụ, ngành nghề và bài viết chỉ cần khớp.
    if (entry.type === 'page') {
      offer(paths)
    }
    else {
      register(paths)
    }
  }

  const problems: Problem[] = []

  /*
   * Link mở được nhưng thuộc ngôn ngữ khác: trang tiếng Trung trỏ sang bản
   * tiếng Việt. Cảnh báo chứ không chặn — bản build vẫn đúng, và một mục menu
   * chưa dịch sẽ mượn URL của ngôn ngữ nó dự phòng, nên chặn ở đây là biến một
   * chỗ chưa dịch thành lỗi deploy.
   */
  const leaks: Problem[] = []

  for (const locale of LOCALES) {
    for (const location of LOCATIONS) {
      const { data } = await get<{ data: MenuItem[] }>(apiBase, `/api/v1/menus/${location}?locale=${locale}`)

      walk(data, (item) => {
        const href = menuHref(item.target, locale)

        // `null` là mục chưa chọn đích, API đã lọc khỏi menu công khai rồi.
        if (href === null || isExternal(href)) {
          return
        }

        const path = normalise(href)
        const sibling = siblings.get(path)
        const problem = { locale, location, label: item.label, url: href, suggestion: sibling?.[locale] }

        if (!valid.has(path)) {
          problems.push(problem)
          return
        }

        if (sibling && sibling[locale] !== undefined && normalise(sibling[locale]) !== path) {
          leaks.push(problem)
        }
      })
    }
  }

  for (const leak of leaks) {
    const suffix = leak.suggestion ? ` — bản ${LOCALE_NAMES[leak.locale]} là ${leak.suggestion}` : ''

    console.warn(`[menu] ${leak.locale}/${leak.location} "${leak.label}" trỏ sang ${leak.url}${suffix}`)
  }

  if (problems.length > 0) {
    throw new Error(report(problems, targets))
  }
}

function walk(items: MenuItem[], visit: (item: MenuItem) => void): void {
  for (const item of items) {
    visit(item)
    walk(item.children ?? [], visit)
  }
}

function report(problems: Problem[], targets: Record<Locale, string[]>): string {
  const where = (p: Problem) => `${p.locale}/${p.location}`
  const column = Math.max(...problems.map(p => where(p).length))
  const label = Math.max(...problems.map(p => p.label.length))
  const url = Math.max(...problems.map(p => p.url.length))

  const lines = problems.map(p => [
    '  ',
    where(p).padEnd(column + 2),
    p.label.padEnd(label + 2),
    p.url.padEnd(url + 2),
    p.suggestion ? `→ có phải ${p.suggestion} ?` : '→ không có trang nào ở địa chỉ này',
  ].join(''))

  // Chỉ liệt kê ngôn ngữ đang có lỗi: người sửa menu tiếng Trung không cần
  // đọc danh sách địa chỉ tiếng Việt.
  const affected = LOCALES.filter(locale => problems.some(p => p.locale === locale))

  const available = affected.flatMap(locale => [
    '',
    `  ${LOCALE_NAMES[locale]} (${locale}):`,
    ...targets[locale].map(path => `    ${path}`),
  ])

  return [
    `${problems.length} link trong menu trỏ tới địa chỉ không tồn tại:`,
    '',
    ...lines,
    '',
    'Địa chỉ dùng được cho menu — trang chủ, trang danh sách và trang tĩnh:',
    ...available,
    '',
    'Menu lưu đích đến chứ không lưu URL, nên đây gần như chắc chắn KHÔNG phải',
    'lỗi dữ liệu: hai phía đang dựng địa chỉ khác nhau. Đối chiếu',
    '`app.locale_fallbacks` (be/config/app.php) với `LOCALE_FALLBACKS`',
    '(fe/shared/content-urls.ts), và `SiteRoute` với `STATIC_ROUTES`.',
  ].join('\n')
}
