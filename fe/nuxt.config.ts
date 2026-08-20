// https://nuxt.com/docs/api/configuration/nuxt-config
// `$fetch` là auto-import của runtime Nuxt, không có trong tiến trình đọc file
// cấu hình này — nạp thẳng từ ofetch.
import { ofetch } from 'ofetch'
import { allPaths, type ContentEntry } from './shared/content-urls'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  modules: [
    '@nuxt/ui',
    '@nuxt/image',
    '@nuxtjs/i18n',
    '@nuxtjs/seo',
    '@vueuse/nuxt',
    // Chỉ sinh ra `.nuxt/eslint.config.mjs` (preset hợp với chính bộ module ở
    // trên) cho `pnpm lint` dùng — không thêm gì vào bundle.
    '@nuxt/eslint',
  ],

  css: ['~/assets/css/main.css'],

  app: {
    head: {
      // Read by the colour-mode inline script before the first paint, and it
      // beats whatever is in localStorage. Belt to the braces below: the
      // preference alone is only a default, and anything that writes a stored
      // preference — Nuxt DevTools does, in dev — would otherwise override it.
      htmlAttrs: { 'data-color-mode-forced': 'light' },
    },
  },

  colorMode: {
    // @nuxt/ui ships @nuxtjs/color-mode, which follows the OS by default. The
    // site has no dark theme — `body` is hard-coded white and nothing here uses
    // a `dark:` variant — but Nuxt UI's own components do, so on a machine in
    // dark mode the inputs turned black on a white page. Pin it to light.
    preference: 'light',
    fallback: 'light',
    // The old default key already holds "system" in every browser that has
    // opened the site — the inline script reads storage before it reads the
    // preference above, so without a new key the stale value would keep
    // winning and the fix would look like it did nothing.
    storageKey: 'taida-color-mode',
  },

  runtimeConfig: {
    /**
     * Base URL used when rendering on the server — during prerendering, that
     * is every page of the site. Same value as the browser uses.
     *
     * Keeping it on one variable matters: while the server and the browser
     * read separate ones, setting only the browser's built the whole site
     * against whatever happened to be running on port 8000. On a developer's
     * machine that is the dev database, so a "production" build quietly
     * shipped dev content and image URLs pointing at localhost, and reported
     * success.
     */
    apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000',
    public: {
      // Browser-side base URL — must be reachable from the client.
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000',
      siteUrl: process.env.NUXT_PUBLIC_SITE_URL || 'http://localhost:3000',
    },
  },

  site: {
    url: process.env.NUXT_PUBLIC_SITE_URL || 'http://localhost:3000',
    name: 'Taida',
  },

  nitro: {
    // Ships .gz/.br alongside every asset. The CSS bundle is ~220 KB raw and
    // render-blocking; without this it is the single biggest cost on first
    // paint. On static hosting nothing else can compress it — Apache serves
    // these files directly, see fe/public/.htaccess.
    compressPublicAssets: { gzip: true, brotli: true },

    prerender: {
      // Mỗi gốc ngôn ngữ được gieo sẵn; phần còn lại đi theo link từ đó, cộng
      // với danh sách lấy thẳng từ API ở hook bên dưới.
      routes: ['/', '/en', '/zh'],
      crawlLinks: true,
      // A link that 404s during the crawl means a page really is unreachable —
      // shipping the build anyway would put that broken link in front of
      // visitors and in the sitemap.
      failOnError: true,
    },

    hooks: {
      /**
       * Mọi trang nội dung, lấy từ chính API mà sitemap dùng — thay vì tin rằng
       * crawler đi tới được hết.
       *
       * Nó không đi tới được: nút phân trang của /tin-tuc là <button> gọi
       * router chứ không phải thẻ <a>, nên crawler dừng ở 9 bài đầu. Sitemap thì
       * khai đủ, nên bài thứ 10 trở đi được hứa với Google ở một địa chỉ không
       * có file — và người đọc bấm vào từ kết quả tìm kiếm nhận 404 của hosting.
       * Với dữ liệu thật (hàng chục bài) thì đó là hàng chục trang.
       *
       * Thêm cả địa chỉ của bản CHƯA dịch (slug rơi về tiếng Việt): trang danh
       * sách dưới /zh liên kết tới chúng, và link đó phải mở được khi tải thẳng
       * chứ không chỉ khi điều hướng trong trình duyệt.
       *
       * API không chạy thì bước này ném lỗi và cả bản build dừng — đúng như
       * mong muốn: bản build vốn đọc API cho từng trang, im lặng bỏ qua chỉ tạo
       * ra một bản build thiếu nội dung mà không ai biết.
       */
      async 'prerender:routes'(routes) {
        const base = process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000'
        const { data } = await ofetch<{ data: ContentEntry[] }>('/api/v1/sitemap-urls', { baseURL: base })

        for (const entry of data) {
          for (const path of allPaths(entry)) {
            routes.add(path)
          }
        }

        console.info(`[prerender] ${data.length} bản ghi từ API → ${routes.size} route`)
      },
    },
  },

  sitemap: {
    // URLs come from the API so newly published content appears without a
    // rebuild; see server/api/__sitemap__/urls.ts.
    sources: ['/api/__sitemap__/urls'],

    // `sources` chỉ THÊM nguồn — module vẫn tự nhặt mọi route đã prerender nếu
    // không tắt. Với hai ngôn ngữ đầy đủ bản dịch thì hai danh sách trùng nhau
    // nên không ai để ý; tiếng Trung làm nó lộ ra. Trang chưa dịch vẫn được sinh
    // (nội dung rơi về tiếng Việt, xem HasTranslations) và bị nhặt vào sitemap
    // kèm slug tiếng Việt — 21 địa chỉ khai với Google là "trang tiếng Trung"
    // trong khi nội dung y hệt bản tiếng Việt.
    //
    // Tắt hẳn nguồn tự thu thập: sitemap giờ đúng bằng những gì API trả về, tức
    // chỉ những bản ghi CÓ bản dịch thật. Đổi lại, một trang tĩnh mới thêm vào
    // sẽ không tự vào sitemap — phải khai trong STATIC_PAGES của urls.ts.
    excludeAppSources: true,

    // Search results and the CMS have no business in the index. Giữ lại cả khi
    // đã tắt nguồn tự thu thập: nó cũng lọc chính nguồn API.
    exclude: ['/admin/**', '/tim-kiem', '/en/search', '/zh/search'],
    // Locale variants are emitted explicitly with their own slugs.
    autoI18n: false,
  },

  robots: {
    disallow: ['/admin'],
  },

  image: {
    /**
     * IPX refuses to touch a remote image unless its host is listed here, so
     * without this every uploaded picture — the logo, article covers — was
     * handed to the browser at full size from a second origin. The logo is the
     * expensive one: it is in the header of every page, and a 400 KB source in
     * a 36 px slot cost eight Lighthouse points on its own.
     */
    // `host`, not `hostname`: the check compares against `new URL(src).host`,
    // so leaving the port off silently matches nothing in development.
    domains: [new URL(process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000').host],
  },

  icon: {
    /**
     * A server bundle is no use to a site with no server: once the pages are
     * prerendered there is nothing left to answer an icon request, so anything
     * not baked into the build falls back to fetching from the Iconify API —
     * a third-party request on every page load, and nothing at all if it is
     * unreachable. Prerendering was already hitting that path and logging
     * "failed to load icon" hundreds of times per build.
     *
     * `scan` collects every `i-lucide-…` written literally in the source.
     */
    clientBundle: {
      scan: true,

      /**
       * These cannot be scanned: they are stored in the `icon` column and
       * composed at runtime, so nothing in the source spells them out. Adding
       * a new icon to a service or industry in the CMS means adding it here
       * too — otherwise it silently renders as a blank space.
       */
      icons: [
        'lucide:badge-check',
        'lucide:bed-double',
        'lucide:flask-conical',
        'lucide:flask-round',
        'lucide:fuel',
        'lucide:hard-hat',
        'lucide:landmark',
        'lucide:layers',
        'lucide:search',
        'lucide:shield-check',
        'lucide:shopping-bag',
        'lucide:truck',
        'lucide:utensils',
      ],
    },
  },

  hooks: {
    /**
     * The other half of the colour-mode pin above. `colorMode: 'light'` in a
     * route's meta makes the module treat the theme as forced, which stops its
     * watcher from ever applying — or re-saving — a stored preference. Setting
     * it here covers every page without a `definePageMeta` in each one.
     */
    'pages:extend': (pages) => {
      const force = (list: typeof pages): void => {
        for (const page of list) {
          page.meta = { colorMode: 'light', ...page.meta }

          if (page.children?.length) {
            force(page.children)
          }
        }
      }

      force(pages)
    },

    /**
     * Nuxt emits a `modulepreload` link for every chunk the route imports —
     * around 25 of them, ~230 KB, all at high priority. On a real connection
     * that is a win; on a phone it makes the hydration bundle race the one
     * stylesheet that actually blocks the first paint, and the stylesheet
     * loses.
     *
     * The pages are server-rendered, so none of that JavaScript is needed to
     * show the page — only to hydrate it. Dropping the hints for non-entry
     * chunks lets the CSS have the connection to itself; the chunks are still
     * fetched, just as normal ESM imports a moment later.
     */
    'build:manifest': (manifest) => {
      for (const chunk of Object.values(manifest)) {
        if (!chunk.isEntry) {
          chunk.preload = false
        }
      }
    },
  },

  fonts: {
    // The site is set in Be Vietnam Pro (see `--font-sans` in main.css); the
    // module finds it on Google Fonts and self-hosts it from /_fonts.
    //
    // By default it emits every subset Google publishes — 61 @font-face rules
    // in the render-blocking stylesheet and, worse, an 86 KB latin-ext file
    // downloaded on every Vietnamese page purely because its unicode-range
    // overlaps the vietnamese one.
    //
    // Dropping latin-ext means the handful of characters that live only there
    // (ā, ř, ş …) fall back to the system sans. Neither Vietnamese nor English
    // needs them; Vietnamese letters — ă đ ơ ư and the whole U+1EA0–1EF9 block
    // — are in the vietnamese subset, which stays.
    defaults: {
      weights: [400, 500, 600, 700],
      styles: ['normal', 'italic'],
      subsets: ['latin', 'vietnamese'],
    },
  },

  ogImage: {
    // Without this the module keeps a runtime endpoint and every page's
    // og:image points at /_og/… — a URL that only a Node server can answer, so
    // on static hosting every share card 404s. Setting it makes the images be
    // rendered to .png files during the build instead.
    //
    // The module still copies its renderer fonts to `_og-static-fonts/` — 22
    // files, 2.1 MB, 17% of the whole build — even though with zeroRuntime
    // nothing ever reads them: they are input for rendering the .png files,
    // not output. `pnpm generate` deletes that directory afterwards; JSON
    // takes no comments, so the reason lives here.
    zeroRuntime: true,

    // `component` cố ý không có ở đây: nuxt-og-image v6 đã bỏ `defaults.component`
    // (renderer nay suy ra từ đuôi tên file, `OgTemplate.takumi.vue`). Mọi lời gọi
    // đều nêu tên component tường minh — xem `defineOgImage('OgTemplate', …)` trong
    // useSeo.ts — nên khai báo lại ở đây chỉ là cấu hình chết mà module lặng lẽ bỏ qua.
    defaults: { width: 1200, height: 630 },

    // The renderer downloads only the `latin` subset by default, which has the
    // ordinary accents (à á ả ô) but none of ư ơ đ or the U+1EA0–1EF9 block —
    // so "Chất lượng toàn diện. Đảm bảo." came out as "Chất l□□ng toàn di□n.
    // □ảm bảo." on every Vietnamese share card.
    // `chinese-simplified` cho Noto Sans SC mà OgTemplate gọi tới. Bộ này CHỈ
    // dùng lúc render card chia sẻ: font không nằm trong `fonts.families` nên
    // không có @font-face nào vào CSS của site, và người đọc không tải gì thêm.
    fontSubsets: ['latin', 'vietnamese', 'chinese-simplified'],
  },

  i18n: {
    // Required for absolute hreflang / canonical links.
    baseUrl: process.env.NUXT_PUBLIC_SITE_URL || 'http://localhost:3000',
    defaultLocale: 'vi',
    strategy: 'prefix_except_default',
    locales: [
      { code: 'vi', language: 'vi-VN', name: 'Tiếng Việt', file: 'vi.json' },
      { code: 'en', language: 'en-US', name: 'English', file: 'en.json' },
      // `zh-Hans` chứ không phải `zh-CN`: thẻ theo hệ CHỮ VIẾT, nên nó đúng cho
      // cả độc giả ở Trung Quốc, Singapore và Malaysia, trong khi `zh-CN` chỉ nói
      // về một quốc gia. Google dùng chính thẻ này cho hreflang.
      { code: 'zh', language: 'zh-Hans', name: '中文', file: 'zh.json' },
    ],
    customRoutes: 'config',
    // Bản tiếng Trung dùng lại chính đường dẫn tiếng Anh, chỉ khác tiền tố:
    // `/zh/services` chứ không phải bính âm hay `/zh/dich-vu`. ASCII nên chia sẻ,
    // gõ tay và ghi log đều không vỡ, và slug của từng bài cũng theo quy tắc đó
    // (xem SlugGenerator::base() bên backend).
    pages: {
      'dich-vu/index': { vi: '/dich-vu', en: '/services', zh: '/services' },
      'dich-vu/[slug]': { vi: '/dich-vu/[slug]', en: '/services/[slug]', zh: '/services/[slug]' },
      'nganh-nghe/index': { vi: '/nganh-nghe', en: '/industries', zh: '/industries' },
      'nganh-nghe/[slug]': { vi: '/nganh-nghe/[slug]', en: '/industries/[slug]', zh: '/industries/[slug]' },
      'tin-tuc/index': { vi: '/tin-tuc', en: '/insights', zh: '/insights' },
      'tin-tuc/[slug]': { vi: '/tin-tuc/[slug]', en: '/insights/[slug]', zh: '/insights/[slug]' },
      'tim-kiem': { vi: '/tim-kiem', en: '/search', zh: '/search' },

      // The CMS is Vietnamese-only and must not be duplicated under /en.
      'admin/login': false,
      'admin/index': false,
      'admin/services/index': false,
      'admin/services/[id]': false,
      'admin/industries/index': false,
      'admin/industries/[id]': false,
      'admin/posts/index': false,
      'admin/posts/[id]': false,
      'admin/categories/index': false,
      'admin/pages/index': false,
      'admin/pages/[id]': false,
      'admin/media/index': false,
      'admin/menus/index': false,
      'admin/settings/index': false,
    },
    experimental: { strictSeo: true },

    // Deliberately off. Redirecting by Accept-Language needs a server to do it
    // before the first byte; a static build can only do it from JavaScript,
    // after the Vietnamese page has already painted. Every English-preferring
    // visitor then saw the page rebuild itself — measured as CLS 1.6 and a
    // 25-point Lighthouse drop on the home page alone.
    //
    // The site is Vietnamese-first with a visible VI|EN switcher, so landing on
    // Vietnamese is the right default anyway.
    detectBrowserLanguage: false,
  },

  routeRules: {
    // The site is statically generated (`pnpm generate`), so there is no
    // server-side cache to tune — every public page is written to disk at
    // build time. The rules left here are the ones that must NOT be.

    // Results depend on a query string and change with the content, so there
    // is nothing worth freezing into a file. Rendered in the browser against
    // the live API; excluded from the sitemap in the `sitemap` config.
    '/tim-kiem': { ssr: false },
    '/en/search': { ssr: false },
    '/zh/search': { ssr: false },

    // The admin panel authenticates with Sanctum session cookies from the
    // browser, so it renders client-side only. Keeping it out of the index is
    // handled once in the `robots` config rather than repeated here.
    '/admin/**': { ssr: false },
  },
})
