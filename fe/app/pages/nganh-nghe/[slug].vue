<script setup lang="ts">
import type { Envelope, IndustryDetail } from '~/types/api'

useDetailPageKey()

const { t } = useI18n()
const localePath = useLocalePath()
const route = useRoute()

const { data, error } = await useApiData<Envelope<IndustryDetail>>(
  () => `/industries/${route.params.slug}`,
)

if (error.value) {
  throw createError({ statusCode: 404, statusMessage: t('common.notFound'), fatal: true })
}

const industry = computed(() => data.value!.data)

useLocalisedSlugs(() => industry.value.slugs)

useSeo(() => ({
  title: industry.value.meta.title ?? industry.value.name,
  description: industry.value.meta.description,
  image: industry.value.cover?.url,
}))
</script>

<template>
  <div>
    <SitePageHero :title="industry.name" :subtitle="industry.excerpt" :cover="industry.cover" />

    <div class="mx-auto max-w-8xl px-4 py-12 sm:px-6 lg:px-8 xl:px-12">
      <SiteBreadcrumb
        :items="[
          { label: t('common.home'), to: localePath('index') },
          { label: t('nav.industries'), to: localePath('nganh-nghe') },
          { label: industry.name, to: localePath({ name: 'nganh-nghe-slug', params: { slug: industry.slug } }) },
        ]"
      />

      <article class="prose max-w-3xl" v-html="industry.body" />

      <section v-if="industry.services.length" class="mt-14">
        <SiteSectionHeading :title="t('industry.relatedServices')" />
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          <SiteContentCard
            v-for="service in industry.services"
            :key="service.id"
            :to="localePath({ name: 'dich-vu-slug', params: { slug: service.slug } })"
            :title="service.name"
            :excerpt="service.excerpt"
            :icon="service.icon"
          />
        </div>
      </section>
    </div>
  </div>
</template>
