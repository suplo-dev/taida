<script setup lang="ts">
import type { Envelope, Page } from '~/types/api'

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

    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
      <SiteBreadcrumb
        :items="[
          { label: t('common.home'), to: localePath('index') },
          { label: page.title, to: `/${page.slug}` },
        ]"
      />

      <article class="prose max-w-none" v-html="page.body" />
    </div>
  </div>
</template>
