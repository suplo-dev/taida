<script setup lang="ts">
import type { Envelope, Industry, Service } from '~/types/api'

const { t, locale } = useI18n()
const localePath = useLocalePath()
const api = useApi()
const route = useRoute()

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

/** Which mega panel is open on desktop; null when none. */
const openPanel = ref<'services' | 'industries' | null>(null)
const mobileOpen = ref(false)

// Any navigation closes whatever was open.
watch(() => route.fullPath, () => {
  openPanel.value = null
  mobileOpen.value = false
})

const header = useTemplateRef<HTMLElement>('header')
onClickOutside(header, () => (openPanel.value = null))
</script>

<template>
  <header ref="header" class="sticky top-0 z-50 bg-primary-600 text-white">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-8 px-4 sm:px-6 lg:px-8">
      <SiteLogo :logo="chrome?.settings.logo" />

      <nav class="hidden flex-1 items-stretch gap-1 md:flex">
        <button
          type="button"
          class="flex items-center gap-1 border-b-2 px-3 text-sm font-medium transition"
          :class="openPanel === 'industries' ? 'border-accent-500 text-white' : 'border-transparent text-primary-100 hover:text-white'"
          @click="openPanel = openPanel === 'industries' ? null : 'industries'"
        >
          {{ t('nav.industries') }}
          <UIcon name="i-lucide-chevron-down" class="size-3.5" />
        </button>

        <button
          type="button"
          class="flex items-center gap-1 border-b-2 px-3 text-sm font-medium transition"
          :class="openPanel === 'services' ? 'border-accent-500 text-white' : 'border-transparent text-primary-100 hover:text-white'"
          @click="openPanel = openPanel === 'services' ? null : 'services'"
        >
          {{ t('nav.services') }}
          <UIcon name="i-lucide-chevron-down" class="size-3.5" />
        </button>

        <NuxtLink
          v-for="link in chrome?.header.slice(2) ?? []"
          :key="link.id"
          :to="link.url ?? '/'"
          class="flex items-center border-b-2 border-transparent px-3 text-sm font-medium text-primary-100 transition hover:text-white"
          active-class="border-accent-500 text-white"
        >
          {{ link.label }}
        </NuxtLink>
      </nav>

      <div class="ml-auto flex items-center gap-4">
        <a
          v-if="chrome?.settings.hotline"
          :href="`tel:${chrome.settings.hotline.replace(/\s/g, '')}`"
          class="hidden items-center gap-1.5 text-sm text-primary-100 hover:text-white lg:flex"
        >
          <UIcon name="i-lucide-phone" class="size-4" />
          {{ chrome.settings.hotline }}
        </a>

        <NuxtLink :to="localePath('tim-kiem')" class="text-primary-100 hover:text-white" :aria-label="t('nav.search')">
          <UIcon name="i-lucide-search" class="size-5" />
        </NuxtLink>

        <!--
          SwitchLocalePathLink, not NuxtLink + switchLocalePath(): on a detail
          page the target slug is only known once the page itself has resolved,
          and a plain link is rendered before that. This component leaves a
          marker that i18n rewrites after the render finishes, which is what
          makes the switcher survive prerendering.

          Mobile only: from md up the utility bar carries the region picker,
          and two switchers stacked one above the other would just be noise.
        -->
        <div class="flex items-center gap-1 text-xs font-medium md:hidden">
          <SwitchLocalePathLink
            locale="vi"
            class="px-1"
            :class="locale === 'vi' ? 'text-white underline underline-offset-4' : 'text-primary-100 hover:text-white'"
          >VI</SwitchLocalePathLink>
          <span class="text-primary-400">|</span>
          <SwitchLocalePathLink
            locale="en"
            class="px-1"
            :class="locale === 'en' ? 'text-white underline underline-offset-4' : 'text-primary-100 hover:text-white'"
          >EN</SwitchLocalePathLink>
        </div>

        <button
          type="button"
          class="text-primary-100 hover:text-white md:hidden"
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
      <div v-if="openPanel" class="absolute inset-x-0 hidden border-t-2 border-accent-500 bg-white shadow-xl md:block">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
          <div class="mb-5 flex items-baseline justify-between">
            <h2 class="text-lg font-semibold text-primary-600">
              {{ openPanel === 'services' ? t('nav.services') : t('nav.industries') }}
            </h2>
            <NuxtLink
              :to="localePath(openPanel === 'services' ? 'dich-vu' : 'nganh-nghe')"
              class="text-sm font-medium text-primary-600 hover:text-primary-800"
            >
              {{ t('common.viewAll') }} →
            </NuxtLink>
          </div>

          <div class="grid grid-cols-4 gap-x-8 gap-y-6">
            <div v-for="item in (openPanel === 'services' ? catalogues?.services : catalogues?.industries) ?? []" :key="item.id">
              <NuxtLink
                :to="localePath({ name: openPanel === 'services' ? 'dich-vu-slug' : 'nganh-nghe-slug', params: { slug: item.slug } })"
                class="font-medium text-primary-600 hover:text-primary-600"
              >
                {{ item.name }}
              </NuxtLink>
              <ul v-if="item.children?.length" class="mt-2 space-y-1.5">
                <li v-for="child in item.children" :key="child.id">
                  <NuxtLink
                    :to="localePath({ name: openPanel === 'services' ? 'dich-vu-slug' : 'nganh-nghe-slug', params: { slug: child.slug } })"
                    class="text-sm text-neutral-600 hover:text-primary-600"
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
    <div v-if="mobileOpen" class="border-t border-primary-800 bg-primary-600 md:hidden">
      <nav class="space-y-1 px-4 py-4">
        <details v-for="group in ([
          { key: 'industries', label: t('nav.industries'), to: 'nganh-nghe', items: catalogues?.industries ?? [] },
          { key: 'services', label: t('nav.services'), to: 'dich-vu', items: catalogues?.services ?? [] },
        ])" :key="group.key" class="border-b border-primary-800 pb-2">
          <summary class="cursor-pointer py-2 text-sm font-medium text-white">{{ group.label }}</summary>
          <ul class="space-y-1 pb-2 pl-3">
            <li v-for="item in group.items" :key="item.id">
              <NuxtLink
                :to="localePath({ name: group.to === 'dich-vu' ? 'dich-vu-slug' : 'nganh-nghe-slug', params: { slug: item.slug } })"
                class="block py-1.5 text-sm text-primary-100"
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
          class="block py-2.5 text-sm font-medium text-primary-100"
        >
          {{ link.label }}
        </NuxtLink>

        <!-- The utility bar is hidden at this width, so its links live here instead. -->
        <div v-if="chrome?.utility.length" class="mt-2 border-t border-primary-800 pt-2">
          <NuxtLink
            v-for="link in chrome.utility"
            :key="link.id"
            :to="link.url ?? '/'"
            :target="link.opensInNewTab ? '_blank' : undefined"
            :rel="link.opensInNewTab ? 'noopener noreferrer' : undefined"
            class="block py-2 text-xs text-primary-300"
          >
            {{ link.label }}
          </NuxtLink>
        </div>
      </nav>
    </div>
  </header>
</template>
