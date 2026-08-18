<script setup lang="ts">
import type { Envelope, Industry, Paginated, Post, Service } from '~/types/api'

const { t, locale } = useI18n()
const localePath = useLocalePath()
const api = useApi()

const { data: chrome } = await useSiteData()

const { data: home } = await useAsyncData(
  `home:${locale.value}`,
  async () => {
    const [services, industries, posts] = await Promise.all([
      api<Envelope<Service[]>>('/services'),
      api<Envelope<Industry[]>>('/industries'),
      api<Paginated<Post>>('/posts', { query: { per_page: 3 } }),
    ])

    return { services: services.data, industries: industries.data, posts: posts.data }
  },
  { watch: [locale] },
)

const hero = computed(() => ({
  title: chrome.value?.settings.hero?.title ?? 'Total Quality. Assured.',
  subtitle: chrome.value?.settings.hero?.subtitle ?? '',
}))

/** The two highest-ranked services double as the featured solutions band. */
const featured = computed(() => (home.value?.services ?? []).filter(service => service.isFeatured).slice(0, 2))

useSeo(() => ({
  title: hero.value.title,
  description: hero.value.subtitle,
}))
</script>

<template>
  <div>
    <!-- 1. Hero -->
    <section class="relative isolate overflow-hidden bg-gradient-to-br from-primary-600 via-primary-600 to-primary-700">
      <div class="absolute inset-0 opacity-20" aria-hidden="true">
        <div class="absolute -right-24 top-1/2 size-[32rem] -translate-y-1/2 rounded-full bg-accent-500 blur-3xl" />
      </div>

      <div class="relative mx-auto max-w-8xl px-4 py-24 sm:px-6 lg:px-8 lg:py-32 xl:px-12">
        <h1 class="max-w-3xl text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl lg:text-6xl">
          {{ hero.title }}
        </h1>
        <p v-if="hero.subtitle" class="mt-6 max-w-2xl text-lg leading-relaxed text-primary-100">
          {{ hero.subtitle }}
        </p>

        <div class="mt-10 flex flex-wrap items-center gap-4">
          <NuxtLink
            :to="localePath('dich-vu')"
            class="inline-flex items-center gap-2 rounded bg-accent-500 px-6 py-3 font-semibold text-primary-950 transition hover:bg-accent-400"
          >
            {{ t('home.exploreServices') }}
            <UIcon name="i-lucide-arrow-right" class="size-5" />
          </NuxtLink>

          <a
            v-if="chrome?.settings.hotline"
            :href="`tel:${chrome.settings.hotline.replace(/\s/g, '')}`"
            class="inline-flex items-center gap-2 rounded border border-primary-400 px-6 py-3 font-medium text-white transition hover:bg-primary-800"
          >
            <UIcon name="i-lucide-phone" class="size-5" />
            {{ chrome.settings.hotline }}
          </a>
        </div>
      </div>
    </section>

    <!-- 2. Featured solutions -->
    <section v-if="featured.length" class="mx-auto max-w-8xl px-4 py-16 sm:px-6 lg:px-8 xl:px-12">
      <div class="grid gap-6 md:grid-cols-2">
        <NuxtLink
          v-for="service in featured"
          :key="service.id"
          :to="localePath({ name: 'dich-vu-slug', params: { slug: service.slug } })"
          class="group relative overflow-hidden rounded-lg bg-gradient-to-br from-primary-500 to-primary-700 p-8 text-white transition hover:shadow-xl"
        >
          <UIcon :name="`i-lucide-${service.icon || 'layers'}`" class="size-9 text-accent-400" />
          <h2 class="mt-5 text-2xl font-semibold">{{ service.name }}</h2>
          <p v-if="service.excerpt" class="mt-2 text-primary-100">{{ service.excerpt }}</p>
          <span class="mt-6 inline-flex items-center gap-1.5 font-medium text-accent-300">
            {{ t('common.readMore') }}
            <UIcon name="i-lucide-arrow-right" class="size-4 transition group-hover:translate-x-1" />
          </span>
        </NuxtLink>
      </div>
    </section>

    <!-- 3. Industries -->
    <section class="bg-cream-50 py-16">
      <div class="mx-auto max-w-8xl px-4 sm:px-6 lg:px-8 xl:px-12">
        <SiteSectionHeading
          :title="t('home.industriesTitle')"
          :subtitle="t('home.industriesSubtitle')"
          :to="localePath('nganh-nghe')"
        />

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          <SiteContentCard
            v-for="industry in home?.industries ?? []"
            :key="industry.id"
            image-first
            :to="localePath({ name: 'nganh-nghe-slug', params: { slug: industry.slug } })"
            :title="industry.name"
            :cover="industry.cover"
            :icon="industry.icon"
          />
        </div>
      </div>
    </section>

    <!-- 4. Service pillars -->
    <section class="mx-auto max-w-8xl px-4 py-16 sm:px-6 lg:px-8 xl:px-12">
      <SiteSectionHeading
        :title="t('home.servicesTitle')"
        :subtitle="t('home.servicesSubtitle')"
        :to="localePath('dich-vu')"
      />

      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <SiteContentCard
          v-for="service in home?.services ?? []"
          :key="service.id"
          :to="localePath({ name: 'dich-vu-slug', params: { slug: service.slug } })"
          :title="service.name"
          :excerpt="service.excerpt"
          :icon="service.icon"
        />
      </div>
    </section>

    <!-- 5. News -->
    <section v-if="home?.posts.length" class="bg-cream-50 py-16">
      <div class="mx-auto max-w-8xl px-4 sm:px-6 lg:px-8 xl:px-12">
        <SiteSectionHeading :title="t('home.newsTitle')" :to="localePath('tin-tuc')" />

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <SiteContentCard
            v-for="post in home.posts"
            :key="post.id"
            image-first
            :to="localePath({ name: 'tin-tuc-slug', params: { slug: post.slug } })"
            :title="post.title"
            :excerpt="post.excerpt"
            :cover="post.cover"
            :meta="post.category?.name"
            icon="newspaper"
          />
        </div>
      </div>
    </section>

    <!-- 6. Closing CTA -->
    <SiteCtaBand
      :title="t('home.ctaTitle')"
      :body="t('home.ctaBody')"
      :hotline="chrome?.settings.hotline"
    />
  </div>
</template>
