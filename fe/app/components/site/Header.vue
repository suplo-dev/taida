<script setup lang="ts">
import type { Envelope, Industry, Service } from '~/types/api'

const { t, locale } = useI18n()
const localePath = useLocalePath()
const api = useApi()
const route = useRoute()
const router = useRouter()

const { data: chrome } = await useSiteData()

// The mega menu shows the catalogues themselves, so it is fetched with them.
const { data: catalogues } = await useAsyncData(
  `site:mega:${locale.value}`,
  async () => {
    const [services, industries] = await Promise.all([
      api<Envelope<Service[]>>('/services'),
      api<Envelope<Industry[]>>('/industries'),
    ])

    return { services: services.data, industries: industries.data }
  },
  { watch: [locale] },
)

/**
 * The two editions, for the switcher inside the burger menu. Named in their own
 * language, as language names are written, so neither row needs translating —
 * the utility bar's picker, which is what carries this on desktop, does the
 * same. Kept in the same order there and here.
 */
const editions = [
  { locale: 'vi' as const, lang: 'Tiếng Việt' },
  { locale: 'en' as const, lang: 'English' },
]

/** Which mega panel is open on desktop; null when none. */
const openPanel = ref<'services' | 'industries' | null>(null)
const mobileOpen = ref(false)

/**
 * Shared with the search page's own field, so on /tim-kiem the two boxes hold
 * the same term and editing either one moves both.
 */
const term = useSearchTerm()

// Any navigation closes whatever was open.
watch(() => route.fullPath, () => {
  openPanel.value = null
  mobileOpen.value = false
})

/**
 * Same destination the search page's own form pushes to, addressed by route
 * name rather than as a URL: that keeps the /en prefix in the English edition
 * and keeps this a client-side navigation instead of a full page load.
 */
function submitSearch() {
  const q = term.value.trim()
  if (!q) return

  router.push(localePath({ name: 'tim-kiem', query: { q } }))
}

const header = useTemplateRef<HTMLElement>('header')
onClickOutside(header, () => (openPanel.value = null))
</script>

<template>
  <!--
    The logo's own blue, so the bar and the mark read as one thing and the
    wordmark can simply be white. The brass underline is what marks the
    section you are in — on blue it separates cleanly from the white type,
    which a lighter blue would not.
  -->
  <header ref="header" class="sticky top-0 z-50 bg-brand-600 text-white">
    <!--
      The bar carries the logo lockup, which is ~69 px on its own from sm, so a
      64 px row left it looking wedged in. 112 px on desktop puts about a fifth
      of the height as clear space above and below it — the proportion corporate
      bars of this kind hold — while staying short enough to keep sticking to
      the top without eating the viewport.

      A phone gets 64 px back: the lockup drops to its compact size there (~42
      px, the slogan being hidden), and holding the row at 80 px would have left
      it swimming while costing a sticky bar's worth of a short viewport.
    -->
    <div class="mx-auto flex h-16 max-w-8xl items-center gap-4 px-4 sm:h-20 sm:px-6 lg:h-28 lg:gap-6 lg:px-8 xl:px-12">
      <SiteLogo :logo="chrome?.settings.logo" />

      <!--
        Pushed to the right rather than sitting against the logo: the lockup
        now runs to ~210 px and the nav starting right beside it made the left
        half of the bar dense and the right half empty. Ranged right, the nav
        and the contact controls read as one group.
      -->
      <nav class="ml-auto hidden items-stretch gap-1 md:flex">
        <button
          type="button"
          class="flex items-center gap-1.5 whitespace-nowrap border-b-2 px-1.5 text-base font-semibold transition lg:px-3.5 lg:text-lg xl:px-5"
          :class="openPanel === 'industries' ? 'border-accent-400 text-white' : 'border-transparent text-brand-50 hover:text-white'"
          @click="openPanel = openPanel === 'industries' ? null : 'industries'"
        >
          {{ t('nav.industries') }}
          <UIcon name="i-lucide-chevron-down" class="size-3.5" />
        </button>

        <button
          type="button"
          class="flex items-center gap-1.5 whitespace-nowrap border-b-2 px-1.5 text-base font-semibold transition lg:px-3.5 lg:text-lg xl:px-5"
          :class="openPanel === 'services' ? 'border-accent-400 text-white' : 'border-transparent text-brand-50 hover:text-white'"
          @click="openPanel = openPanel === 'services' ? null : 'services'"
        >
          {{ t('nav.services') }}
          <UIcon name="i-lucide-chevron-down" class="size-3.5" />
        </button>

        <!--
          `border-accent-400!` rather than plain `border-accent-400`: the
          resting state needs `border-transparent` so the item always reserves
          the underline's 2 px, and the two utilities have equal specificity —
          whichever Tailwind emits last wins, regardless of which one the
          router adds. The important modifier is what makes the active state
          actually paint. The mega-menu buttons above sidestep this by binding
          one class list or the other, never both.
        -->
        <NuxtLink
          v-for="link in chrome?.header.slice(2) ?? []"
          :key="link.id"
          :to="link.url ?? '/'"
          class="flex items-center whitespace-nowrap border-b-2 border-transparent px-1.5 text-base font-semibold text-brand-50 transition hover:text-white lg:px-3.5 lg:text-lg xl:px-5"
          active-class="border-accent-400! text-white"
        >
          {{ link.label }}
        </NuxtLink>
      </nav>

      <!--
        `ml-auto` up to md, then the nav takes over: the nav above is what
        absorbs the free space and ranges this group right, but it is hidden
        below md, which left the controls sitting against the logo with the
        whole right half of a phone bar empty. Only one of the two may claim the
        free space at a time, hence `md:ml-0`.
      -->
      <div class="ml-auto flex items-center gap-4 md:ml-0">
        <a
          v-if="chrome?.settings.hotline"
          :href="`tel:${chrome.settings.hotline.replace(/\s/g, '')}`"
          class="hidden items-center gap-2 text-base font-semibold text-brand-50 transition hover:text-white lg:flex"
        >
          <UIcon name="i-lucide-phone" class="size-5" />
          {{ chrome.settings.hotline }}
        </a>

        <!--
          A real field rather than a link to the search page: the term is the
          thing visitors arrive with, and typing it here saves them a page.
          Submitting — by the icon or by Enter — pushes /tim-kiem?q=<term>,
          which is where the search page reads its query from.

          It only unfolds from xl. Below that the nav, the hotline and the
          burger already fill the bar, and a ~240 px field pushed the nav onto
          a second line; the icon link keeps the same destination one tap away.
        -->
        <form
          role="search"
          class="hidden items-center rounded-full bg-white pl-4 pr-1 ring-1 ring-white/25 transition focus-within:ring-accent-400 xl:flex"
          @submit.prevent="submitSearch"
        >
          <input
            v-model="term"
            type="search"
            :placeholder="t('search.headerPlaceholder')"
            :aria-label="t('nav.search')"
            class="w-36 2xl:w-56 appearance-none bg-transparent py-2.5 text-base font-medium text-neutral-900 outline-none placeholder:font-normal placeholder:text-neutral-500"
          >
          <button
            type="submit"
            class="rounded-full p-2 text-neutral-500 transition hover:text-brand-600"
            :aria-label="t('nav.search')"
          >
            <UIcon name="i-lucide-search" class="size-5" />
          </button>
        </form>

        <NuxtLink
          :to="localePath('tim-kiem')"
          class="text-brand-50 transition hover:text-white xl:hidden"
          :aria-label="t('nav.search')"
        >
          <UIcon name="i-lucide-search" class="size-6" />
        </NuxtLink>

        <button
          type="button"
          class="text-brand-50 transition hover:text-white md:hidden"
          :aria-label="t('nav.menu')"
          @click="mobileOpen = !mobileOpen"
        >
          <UIcon :name="mobileOpen ? 'i-lucide-x' : 'i-lucide-menu'" class="size-6" />
        </button>
      </div>
    </div>

    <!-- Desktop mega panel -->
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="-translate-y-1 opacity-0"
      leave-active-class="transition duration-100 ease-in"
      leave-to-class="-translate-y-1 opacity-0"
    >
      <div v-if="openPanel" class="absolute inset-x-0 hidden border-t-2 border-accent-400 bg-white shadow-xl md:block">
        <div class="mx-auto max-w-8xl px-4 py-8 sm:px-6 lg:px-8 xl:px-12">
          <div class="mb-5 flex items-baseline justify-between">
            <h2 class="text-lg font-semibold text-primary-900">
              {{ openPanel === 'services' ? t('nav.services') : t('nav.industries') }}
            </h2>
            <NuxtLink
              :to="localePath(openPanel === 'services' ? 'dich-vu' : 'nganh-nghe')"
              class="text-sm font-medium text-brand-600 transition hover:text-brand-800"
            >
              {{ t('common.viewAll') }} →
            </NuxtLink>
          </div>

          <div class="grid grid-cols-4 gap-x-8 gap-y-6">
            <div v-for="item in (openPanel === 'services' ? catalogues?.services : catalogues?.industries) ?? []" :key="item.id">
              <NuxtLink
                :to="localePath({ name: openPanel === 'services' ? 'dich-vu-slug' : 'nganh-nghe-slug', params: { slug: item.slug } })"
                class="font-medium text-primary-700 transition hover:text-brand-600"
              >
                {{ item.name }}
              </NuxtLink>
              <ul v-if="item.children?.length" class="mt-2 space-y-1.5">
                <li v-for="child in item.children" :key="child.id">
                  <NuxtLink
                    :to="localePath({ name: openPanel === 'services' ? 'dich-vu-slug' : 'nganh-nghe-slug', params: { slug: child.slug } })"
                    class="text-sm text-neutral-600 transition hover:text-brand-600"
                  >
                    {{ child.name }}
                  </NuxtLink>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Mobile accordion -->
    <div v-if="mobileOpen" class="border-t border-white/15 bg-brand-600 md:hidden">
      <nav class="space-y-1 px-4 py-4">
        <details
v-for="group in ([
          { key: 'industries', label: t('nav.industries'), to: 'nganh-nghe', items: catalogues?.industries ?? [] },
          { key: 'services', label: t('nav.services'), to: 'dich-vu', items: catalogues?.services ?? [] },
        ])" :key="group.key" class="border-b border-white/15 pb-2">
          <summary class="cursor-pointer py-2.5 text-base font-semibold text-white">{{ group.label }}</summary>
          <ul class="space-y-1 pb-2 pl-3">
            <li v-for="item in group.items" :key="item.id">
              <NuxtLink
                :to="localePath({ name: group.to === 'dich-vu' ? 'dich-vu-slug' : 'nganh-nghe-slug', params: { slug: item.slug } })"
                class="block py-2 text-base text-brand-50"
              >
                {{ item.name }}
              </NuxtLink>
            </li>
          </ul>
        </details>

        <NuxtLink
          v-for="link in chrome?.header.slice(2) ?? []"
          :key="link.id"
          :to="link.url ?? '/'"
          class="block py-3 text-base font-semibold text-brand-50"
        >
          {{ link.label }}
        </NuxtLink>

        <!--
          The edition switcher, which the utility bar carries from md up. It
          used to sit in the bar as a VI|EN pair beside the burger; between it,
          the search icon and the menu button the controls needed ~137 px, and
          with the lockup they overflowed a 320–390 px bar. A drawer row can
          afford the language's own name, which reads better than a two-letter
          code anyway.

          SwitchLocalePathLink, not NuxtLink + switchLocalePath(): on a detail
          page the target slug is only known once the page itself has resolved,
          and a plain link is rendered before that. This component leaves a
          marker that i18n rewrites after the render finishes, which is what
          makes the switcher survive prerendering.
        -->
        <div class="mt-2 border-t border-white/15 pt-2">
          <SwitchLocalePathLink
            v-for="edition in editions"
            :key="edition.locale"
            :locale="edition.locale"
            class="flex items-center gap-3 py-2.5 text-base"
            :class="locale === edition.locale ? 'font-semibold text-white' : 'text-brand-50'"
          >
            <SiteFlagVn v-if="edition.locale === 'vi'" :title="t('utility.vietnam')" />
            <UIcon v-else name="i-lucide-globe" class="size-5 text-brand-200" />
            {{ edition.lang }}
            <UIcon
              v-if="locale === edition.locale"
              name="i-lucide-check"
              class="ml-auto size-4 text-accent-400"
            />
          </SwitchLocalePathLink>
        </div>

        <!-- The utility bar is hidden at this width, so its links live here instead. -->
        <div v-if="chrome?.utility.length" class="mt-2 border-t border-white/15 pt-2">
          <NuxtLink
            v-for="link in chrome.utility"
            :key="link.id"
            :to="link.url ?? '/'"
            :target="link.opensInNewTab ? '_blank' : undefined"
            :rel="link.opensInNewTab ? 'noopener noreferrer' : undefined"
            class="block py-2 text-xs text-brand-200"
          >
            {{ link.label }}
          </NuxtLink>
        </div>
      </nav>
    </div>
  </header>
</template>
