<script setup lang="ts">
import type { Envelope, Industry } from '~/types/api'

const { t } = useI18n()
const localePath = useLocalePath()

const { data: industries } = await useApiData<Envelope<Industry[]>>('/industries')

useSeo(() => ({
  title: t('nav.industries'),
  description: t('home.industriesSubtitle'),
}))
</script>

<template>
  <div>
    <SitePageHero :title="t('nav.industries')" :subtitle="t('home.industriesSubtitle')" />

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
      <SiteBreadcrumb
        :items="[
          { label: t('common.home'), to: localePath('index') },
          { label: t('nav.industries'), to: localePath('nganh-nghe') },
        ]"
      />

      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <SiteContentCard
          v-for="industry in industries?.data ?? []"
          :key="industry.id"
          image-first
          :to="localePath({ name: 'nganh-nghe-slug', params: { slug: industry.slug } })"
          :title="industry.name"
          :excerpt="industry.excerpt"
          :cover="industry.cover"
          :icon="industry.icon"
        />
      </div>
    </div>
  </div>
</template>
