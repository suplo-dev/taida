<script setup lang="ts">
import type { Envelope, Post, PostDetail } from '~/types/api'

const { t, locale } = useI18n()
const localePath = useLocalePath()
const route = useRoute()

const { data, error } = await useApiData<Envelope<PostDetail>>(
  () => `/posts/${route.params.slug}`,
)

if (error.value) {
  throw createError({ statusCode: 404, statusMessage: t('common.notFound'), fatal: true })
}

const post = computed(() => data.value!.data)

useLocalisedSlugs(() => post.value.slugs)

const { data: related } = await useApiData<Envelope<Post[]>>(
  () => `/posts/${route.params.slug}/related`,
)

const publishedOn = computed(() => (post.value.publishedAt
  ? new Date(post.value.publishedAt).toLocaleDateString(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
      day: '2-digit', month: 'long', year: 'numeric',
    })
  : null))

useSeo(() => ({
  title: post.value.meta.title ?? post.value.title,
  description: post.value.meta.description,
  image: post.value.cover?.url,
  publishedAt: post.value.publishedAt,
  type: 'article',
}))
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <SiteBreadcrumb
      :items="[
        { label: t('common.home'), to: localePath('index') },
        { label: t('nav.insights'), to: localePath('tin-tuc') },
        { label: post.title, to: localePath({ name: 'tin-tuc-slug', params: { slug: post.slug } }) },
      ]"
    />

    <article class="mx-auto max-w-3xl">
      <p v-if="post.category" class="text-sm font-medium uppercase tracking-wide text-primary-500">
        {{ post.category.name }}
      </p>

      <h1 class="mt-2 text-3xl font-bold leading-tight tracking-tight text-primary-900 sm:text-4xl">
        {{ post.title }}
      </h1>

      <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-neutral-500">
        <time v-if="publishedOn" :datetime="post.publishedAt ?? undefined">
          {{ t('post.publishedOn') }} {{ publishedOn }}
        </time>
        <span v-if="post.author">· {{ post.author.name }}</span>
      </div>

      <NuxtImg
        v-if="post.cover"
        :src="post.cover.url"
        :alt="post.cover.alt ?? post.title"
        class="mt-8 aspect-[16/9] w-full rounded-lg object-cover"
        sizes="sm:100vw md:768px"
        preload
      />

      <div class="prose mt-8 max-w-none" v-html="post.body" />

      <div v-if="post.tags.length" class="mt-10 flex flex-wrap gap-2 border-t border-neutral-200 pt-6">
        <span
          v-for="tag in post.tags"
          :key="tag.id"
          class="rounded-full bg-neutral-100 px-3 py-1 text-xs text-neutral-600"
        >
          {{ tag.name }}
        </span>
      </div>
    </article>

    <section v-if="related?.data.length" class="mt-16">
      <SiteSectionHeading :title="t('post.related')" />
      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <SiteContentCard
          v-for="item in related.data"
          :key="item.id"
          image-first
          icon="newspaper"
          :to="localePath({ name: 'tin-tuc-slug', params: { slug: item.slug } })"
          :title="item.title"
          :excerpt="item.excerpt"
          :cover="item.cover"
          :meta="item.category?.name"
        />
      </div>
    </section>
  </div>
</template>
