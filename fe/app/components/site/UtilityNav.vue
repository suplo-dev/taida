<script setup lang="ts">
const { t, locale } = useI18n()
const route = useRoute()

const { data: chrome } = await useSiteData()

/**
 * The region picker doubles as the language switcher: each edition of the site
 * is one language, so "which site" and "which language" are the same question.
 * Rows are declared here rather than fetched because they follow the configured
 * locales, not editable content.
 *
 * Each row needs its own region label — two rows both reading "Toàn cầu" would
 * look like a duplicate rather than a choice. The two country editions lead and
 * the global one closes the list, so the flags read as a pair.
 */
const regions = [
  { locale: 'vi' as const, region: 'utility.vietnam', lang: 'Tiếng Việt' },
  { locale: 'zh' as const, region: 'utility.china', lang: '中文' },
  { locale: 'en' as const, region: 'utility.global', lang: 'English' },
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
  <div class="relative z-60 hidden bg-cream-300 text-primary-700 md:block">
    <!--
      Set in the body size rather than the usual 12 px — these are the links
      customers actually ask for, and as fine print they get skipped over. At
      40 px the row was tight enough that the links looked crammed against the
      header seam; 56 px gives them roughly a line's worth of air above and
      below without the strip starting to compete with the header for height.
    -->
    <div class="mx-auto flex h-14 max-w-8xl items-center px-4 text-sm sm:px-6 lg:px-8 xl:px-12">
      <!--
        Dropped between md and lg: at 14 px the links plus the region picker
        already need the full width there, and this label is the part a visitor
        can lose — the flag reappears inside the region panel either way.
      -->
      <span class="hidden items-center gap-2 font-semibold lg:flex">
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
          class="border-l border-primary-950/15 px-4 font-semibold transition hover:text-brand-600 first:border-l-0 lg:px-5"
        >
          {{ link.label }}
        </NuxtLink>
      </nav>

      <div ref="wrapper" class="relative border-l border-primary-950/15 pl-4 lg:pl-5">
        <button
          type="button"
          class="flex items-center gap-1.5 font-semibold transition hover:text-brand-600"
          :aria-expanded="open"
          @click="open = !open"
        >
          <UIcon name="i-lucide-globe" class="size-4" />
          {{ t('utility.global') }}
          <UIcon name="i-lucide-chevron-down" class="size-3.5 transition" :class="open && 'rotate-180'" />
        </button>

        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="-translate-y-1 opacity-0"
          leave-active-class="transition duration-100 ease-in"
          leave-to-class="-translate-y-1 opacity-0"
        >
          <div
            v-if="open"
            class="absolute right-0 top-full z-50 w-60 border-t-2 border-accent-400 bg-brand-600 py-2 shadow-xl"
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
              class="flex items-center gap-3 px-4 py-2 transition hover:bg-brand-700"
              :class="locale === row.locale ? 'text-white' : 'text-brand-100'"
            >
              <!--
                A fixed box for the mark, not each mark at its own size: a flag
                is 30 px wide against the globe's 20 px, so laid out by the row's
                own gap the global row's label started 10 px further left than
                the other two. The box is the widest mark's width, which lines
                all three labels up, and its height is the globe's — at 20 px,
                the flags' height, a circle read visibly smaller than the
                rectangles beside it.
              -->
              <span class="flex h-6 w-[1.875rem] shrink-0 items-center justify-center">
                <SiteFlagVn v-if="row.locale === 'vi'" :title="t('utility.vietnam')" />
                <SiteFlagCn v-else-if="row.locale === 'zh'" :title="t('utility.china')" />
                <!-- The English edition is the global one, so it keeps the globe rather than a country's flag. -->
                <UIcon v-else name="i-lucide-globe" class="size-6 text-brand-200" />
              </span>

              <span class="flex flex-col leading-tight">
                <span class="font-medium">{{ t(row.region) }}</span>
                <span class="text-[11px] text-brand-200">{{ row.lang }}</span>
              </span>
            </SwitchLocalePathLink>
          </div>
        </Transition>
      </div>
    </div>
  </div>
</template>
