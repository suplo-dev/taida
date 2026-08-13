<script setup lang="ts">
const { t, locale } = useI18n()
const route = useRoute()

const { data: chrome } = await useSiteData()

/**
 * The region picker doubles as the language switcher: this site exists as a
 * Vietnamese edition and an English one, so "which site" and "which language"
 * are the same question. Rows are declared here rather than fetched because
 * they follow the configured locales, not editable content.
 */
const regions = [
  { locale: 'vi' as const, region: 'utility.vietnam', lang: 'Tiếng Việt' },
  { locale: 'en' as const, region: 'utility.globalSite', lang: 'English' },
]

const open = ref(false)
const wrapper = useTemplateRef<HTMLElement>('wrapper')

onClickOutside(wrapper, () => (open.value = false))
watch(() => route.fullPath, () => (open.value = false))
</script>

<template>
  <!--
    Corporate links that are not part of the main navigation, on the thin bar
    above the header. It scrolls away with the page — only the header itself is
    sticky — and it is dropped below md, where the links move into the burger
    menu instead of squeezing a second row into a 375 px viewport.
  -->
  <!--
    `relative z-60`, not the default flow: the header below is `sticky z-50`,
    which paints it into its own stacking context. At an equal z-index the
    later element in the document wins, so the region panel — which hangs down
    over the header — was drawn underneath it. The bar has to outrank the
    header for its own dropdown to be visible.
  -->
  <div class="relative z-60 hidden bg-white text-black md:block">
    <div class="mx-auto flex h-9 max-w-7xl items-center px-4 text-xs sm:px-6 lg:px-8">
      <span class="flex items-center gap-2 font-medium">
        <SiteFlagVn />
        {{ t('utility.edition') }}
      </span>

      <nav class="ml-auto flex items-center">
        <NuxtLink
          v-for="link in chrome?.utility ?? []"
          :key="link.id"
          :to="link.url ?? '/'"
          :target="link.opensInNewTab ? '_blank' : undefined"
          :rel="link.opensInNewTab ? 'noopener noreferrer' : undefined"
          class="border-l border-b-gray-500 px-3 transition first:border-l-0"
        >
          {{ link.label }}
        </NuxtLink>
      </nav>

      <div ref="wrapper" class="relative ml-1 border-l border-b-gray-500 pl-3">
        <button
          type="button"
          class="flex items-center gap-1.5 transition"
          :aria-expanded="open"
          @click="open = !open"
        >
          <UIcon name="i-lucide-globe" class="size-3.5" />
          {{ t('utility.global') }}
          <UIcon name="i-lucide-chevron-down" class="size-3 transition" :class="open && 'rotate-180'" />
        </button>

        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="-translate-y-1 opacity-0"
          leave-active-class="transition duration-100 ease-in"
          leave-to-class="-translate-y-1 opacity-0"
        >
          <div
            v-if="open"
            class="absolute right-0 top-full z-50 w-60 border-t-2 border-accent-500 bg-primary-600 py-2 shadow-xl"
          >
            <!--
              SwitchLocalePathLink for the same reason as the header switcher:
              on a detail page the target slug is only resolved after the page
              itself is, which a plain link rendered up front would miss.
            -->
            <SwitchLocalePathLink
              v-for="row in regions"
              :key="row.locale"
              :locale="row.locale"
              class="flex items-center gap-3 px-4 py-2 transition hover:bg-primary-800"
              :class="locale === row.locale ? 'text-white' : 'text-primary-200'"
            >
              <SiteFlagVn v-if="row.locale === 'vi'" :title="t('utility.vietnam')" />
              <UIcon v-else name="i-lucide-globe" class="size-5 text-primary-300" />

              <span class="flex flex-col leading-tight">
                <span class="font-medium">{{ t(row.region) }}</span>
                <span class="text-[11px] text-primary-300">{{ row.lang }}</span>
              </span>
            </SwitchLocalePathLink>
          </div>
        </Transition>
      </div>
    </div>
  </div>
</template>
