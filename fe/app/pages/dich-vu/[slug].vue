<script setup lang="ts">
import type { Envelope, ServiceDetail } from '~/types/api'

useDetailPageKey()

const { t } = useI18n()
const localePath = useLocalePath()
const route = useRoute()

const { data, error } = await useApiData<Envelope<ServiceDetail>>(
  () => `/services/${route.params.slug}`,
)

if (error.value) {
  throw createError({ statusCode: 404, statusMessage: t('common.notFound'), fatal: true })
}

const service = computed(() => data.value!.data)

useLocalisedSlugs(() => service.value.slugs)

useSeo(() => ({
  title: service.value.meta.title ?? service.value.name,
  description: service.value.meta.description,
  image: service.value.cover?.url,
}))
</script>

<template>
  <div>
    <SitePageHero :title="service.name" :subtitle="service.excerpt" :cover="service.cover" />

    <div class="mx-auto max-w-8xl px-4 py-12 sm:px-6 lg:px-8 xl:px-12">
      <SiteBreadcrumb
        :items="[
          { label: t('common.home'), to: localePath('index') },
          { label: t('nav.services'), to: localePath('dich-vu') },
          ...(service.parent
            ? [{ label: service.parent.name, to: localePath({ name: 'dich-vu-slug', params: { slug: service.parent.slug } }) }]
            : []),
          { label: service.name, to: localePath({ name: 'dich-vu-slug', params: { slug: service.slug } }) },
        ]"
      />

      <div class="grid gap-12 lg:grid-cols-[1fr_300px]">
        <article class="prose max-w-none" v-html="service.body" />

        <aside v-if="service.industries.length" class="lg:border-l lg:border-neutral-200 lg:pl-8">
          <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-500">
            {{ t('service.relatedIndustries') }}
          </h2>
          <ul class="space-y-2.5">
            <li v-for="industry in service.industries" :key="industry.id">
              <NuxtLink
                :to="localePath({ name: 'nganh-nghe-slug', params: { slug: industry.slug } })"
                class="flex items-center gap-2 text-sm text-neutral-700 hover:text-primary-600"
              >
                <UIcon :name="`i-lucide-${industry.icon || 'dot'}`" class="size-4 text-primary-400" />
                {{ industry.name }}
              </NuxtLink>
            </li>
          </ul>
        </aside>
      </div>

      <section v-if="service.children?.length" class="mt-14">
        <SiteSectionHeading :title="t('service.subServices')" />
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <SiteContentCard
            v-for="child in service.children"
            :key="child.id"
            :to="localePath({ name: 'dich-vu-slug', params: { slug: child.slug } })"
            :title="child.name"
            :excerpt="child.excerpt"
            :cover="child.cover"
          />
        </div>
      </section>
    </div>
  </div>
</template>
