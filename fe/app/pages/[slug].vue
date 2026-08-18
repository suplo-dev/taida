<script setup lang="ts">
import type { Envelope, Page } from '~/types/api'

useDetailPageKey()

const { t } = useI18n()
const localePath = useLocalePath()
const route = useRoute()

const { data, error } = await useApiData<Envelope<Page>>(() => `/pages/${route.params.slug}`)

if (error.value) {
  throw createError({ statusCode: 404, statusMessage: t('common.notFound'), fatal: true })
}

const page = computed(() => data.value!.data)

useLocalisedSlugs(() => page.value.slugs)

useSeo(() => ({
  title: page.value.meta.title ?? page.value.title,
  description: page.value.meta.description,
  image: page.value.cover?.url,
}))
</script>

<template>
  <div>
    <SitePageHero :title="page.title" :cover="page.cover" />

    <!--
      The same outer container every other page uses, so the breadcrumb lines
      up with the hero title above it and with the nav above that. Editorial
      pages read better in a narrow measure, but narrowing the whole column
      moved the breadcrumb inwards too and made this the one page whose
      content started somewhere different from the rest of the site — the
      measure belongs to the prose, not to the page.

      Ranged left inside that container rather than centred: the hero title
      above starts at the container's left edge, so a centred body left the
      page with two different left margins stacked on top of each other. The
      news article centres its column because it has no hero — its own title
      lives inside the column.
    -->
    <div class="mx-auto max-w-8xl px-4 py-12 sm:px-6 lg:px-8 xl:px-12">
      <SiteBreadcrumb
        :items="[
          { label: t('common.home'), to: localePath('index') },
          { label: page.title, to: `/${page.slug}` },
        ]"
      />

      <article class="prose max-w-3xl" v-html="page.body" />
    </div>
  </div>
</template>
