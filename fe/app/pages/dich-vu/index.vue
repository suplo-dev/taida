<script setup lang="ts">
import type { Envelope, Service } from '~/types/api'

const { t, locale } = useI18n()
const localePath = useLocalePath()

const { data: services } = await useApiData<Envelope<Service[]>>('/services')

useSeo(() => ({
  title: t('nav.services'),
  description: t('home.servicesSubtitle'),
}))
</script>

<template>
  <div>
    <SitePageHero :title="t('nav.services')" :subtitle="t('home.servicesSubtitle')" />

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
      <SiteBreadcrumb
        :items="[
          { label: t('common.home'), to: localePath('index') },
          { label: t('nav.services'), to: localePath('dich-vu') },
        ]"
      />

      <div class="space-y-12">
        <section v-for="service in services?.data ?? []" :key="service.id">
          <div class="flex items-start gap-4">
            <UIcon :name="`i-lucide-${service.icon || 'layers'}`" class="mt-1 size-8 shrink-0 text-primary-500" />
            <div class="min-w-0 flex-1">
              <h2 class="text-xl font-semibold text-primary-600">
                <NuxtLink
                  :to="localePath({ name: 'dich-vu-slug', params: { slug: service.slug } })"
                  class="hover:text-primary-600"
                >
                  {{ service.name }}
                </NuxtLink>
              </h2>
              <p v-if="service.excerpt" class="mt-1.5 max-w-3xl text-neutral-600">{{ service.excerpt }}</p>
            </div>
          </div>

          <div v-if="service.children?.length" class="mt-5 grid gap-4 pl-12 sm:grid-cols-2 lg:grid-cols-3">
            <SiteContentCard
              v-for="child in service.children"
              :key="child.id"
              :to="localePath({ name: 'dich-vu-slug', params: { slug: child.slug } })"
              :title="child.name"
              :excerpt="child.excerpt"
            />
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
