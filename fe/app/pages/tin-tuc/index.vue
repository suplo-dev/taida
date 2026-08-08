<script setup lang="ts">
import type { Category, Envelope, Paginated, Post } from '~/types/api'

const { t } = useI18n()
const localePath = useLocalePath()
const route = useRoute()
const router = useRouter()

const page = computed(() => Number(route.query.page ?? 1))
const category = computed(() => (route.query.category as string) || undefined)

const { data: posts } = await useApiData<Paginated<Post>>('/posts', {
  query: computed(() => ({ page: page.value, category: category.value, per_page: 9 })),
})

const { data: categories } = await useApiData<Envelope<Category[]>>('/categories')

function go(query: Record<string, string | number | undefined>) {
  router.push({ query: { ...route.query, ...query } })
}

useSeo(() => ({ title: t('nav.insights') }))
</script>

<template>
  <div>
    <SitePageHero :title="t('nav.insights')" />

    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
      <SiteBreadcrumb
        :items="[
          { label: t('common.home'), to: localePath('index') },
          { label: t('nav.insights'), to: localePath('tin-tuc') },
        ]"
      />

      <div class="mb-8 flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-full border px-4 py-1.5 text-sm transition"
          :class="!category ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-neutral-300 text-neutral-600 hover:border-primary-300'"
          @click="go({ category: undefined, page: 1 })"
        >
          {{ t('post.allCategories') }}
        </button>
        <button
          v-for="item in categories?.data ?? []"
          :key="item.id"
          type="button"
          class="rounded-full border px-4 py-1.5 text-sm transition"
          :class="category === item.slug ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-neutral-300 text-neutral-600 hover:border-primary-300'"
          @click="go({ category: item.slug, page: 1 })"
        >
          {{ item.name }}
          <span class="ml-1 text-xs text-neutral-400">{{ item.postCount }}</span>
        </button>
      </div>

      <div v-if="posts?.data.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <SiteContentCard
          v-for="post in posts.data"
          :key="post.id"
          image-first
          icon="newspaper"
          :to="localePath({ name: 'tin-tuc-slug', params: { slug: post.slug } })"
          :title="post.title"
          :excerpt="post.excerpt"
          :cover="post.cover"
          :meta="post.category?.name"
        />
      </div>

      <p v-else class="py-20 text-center text-neutral-500">{{ t('common.notFound') }}</p>

      <nav v-if="(posts?.meta.last_page ?? 1) > 1" class="mt-10 flex items-center justify-center gap-2">
        <button
          type="button"
          class="rounded border border-neutral-300 px-3 py-1.5 text-sm disabled:opacity-40"
          :disabled="page <= 1"
          @click="go({ page: page - 1 })"
        >
          {{ t('common.previous') }}
        </button>

        <button
          v-for="n in posts!.meta.last_page"
          :key="n"
          type="button"
          class="size-9 rounded border text-sm transition"
          :class="n === page ? 'border-primary-500 bg-primary-500 text-white' : 'border-neutral-300 text-neutral-600 hover:border-primary-300'"
          @click="go({ page: n })"
        >
          {{ n }}
        </button>

        <button
          type="button"
          class="rounded border border-neutral-300 px-3 py-1.5 text-sm disabled:opacity-40"
          :disabled="page >= (posts?.meta.last_page ?? 1)"
          @click="go({ page: page + 1 })"
        >
          {{ t('common.next') }}
        </button>
      </nav>
    </div>
  </div>
</template>
