<script setup lang="ts">
import type { Envelope, SearchResults } from '~/types/api'

const { t } = useI18n()
const localePath = useLocalePath()
const route = useRoute()
const router = useRouter()

const term = ref((route.query.q as string) ?? '')
const query = computed(() => (route.query.q as string) ?? '')

const { data: results } = await useApiData<Envelope<SearchResults>>('/search', {
  query: computed(() => ({ q: query.value })),
})

const total = computed(() => {
  const groups = results.value?.data
  return (groups?.services.length ?? 0) + (groups?.industries.length ?? 0) + (groups?.posts.length ?? 0)
})

function submit() {
  router.push({ query: { q: term.value || undefined } })
}

useSeo(() => ({ title: query.value ? `${t('search.resultsFor')} “${query.value}”` : t('nav.search') }))
</script>

<template>
  <div>
    <SitePageHero :title="t('nav.search')" />

    <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
      <form class="flex gap-2" @submit.prevent="submit">
        <input
          v-model="term"
          type="search"
          :placeholder="t('search.placeholder')"
          class="flex-1 rounded border border-neutral-300 px-4 py-3 outline-none focus:border-primary-500"
        >
        <button type="submit" class="rounded bg-primary-600 px-6 py-3 font-medium text-white transition hover:bg-primary-700">
          {{ t('nav.search') }}
        </button>
      </form>

      <p v-if="!query" class="py-16 text-center text-neutral-500">{{ t('search.hint') }}</p>

      <template v-else>
        <p class="mt-8 text-sm text-neutral-500">
          {{ t('search.resultsFor') }} <strong class="text-neutral-800">“{{ query }}”</strong> — {{ total }}
        </p>

        <p v-if="total === 0" class="py-16 text-center text-neutral-500">{{ t('search.empty') }}</p>

        <div v-else class="mt-6 space-y-10">
          <section v-if="results?.data.services.length">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-neutral-500">{{ t('nav.services') }}</h2>
            <ul class="divide-y divide-neutral-100 rounded-lg border border-neutral-200">
              <li v-for="item in results.data.services" :key="item.id">
                <NuxtLink
                  :to="localePath({ name: 'dich-vu-slug', params: { slug: item.slug } })"
                  class="block px-5 py-4 transition hover:bg-neutral-50"
                >
                  <p class="font-medium text-primary-600">{{ item.name }}</p>
                  <p v-if="item.excerpt" class="mt-1 line-clamp-2 text-sm text-neutral-600">{{ item.excerpt }}</p>
                </NuxtLink>
              </li>
            </ul>
          </section>

          <section v-if="results?.data.industries.length">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-neutral-500">{{ t('nav.industries') }}</h2>
            <ul class="divide-y divide-neutral-100 rounded-lg border border-neutral-200">
              <li v-for="item in results.data.industries" :key="item.id">
                <NuxtLink
                  :to="localePath({ name: 'nganh-nghe-slug', params: { slug: item.slug } })"
                  class="block px-5 py-4 transition hover:bg-neutral-50"
                >
                  <p class="font-medium text-primary-600">{{ item.name }}</p>
                  <p v-if="item.excerpt" class="mt-1 line-clamp-2 text-sm text-neutral-600">{{ item.excerpt }}</p>
                </NuxtLink>
              </li>
            </ul>
          </section>

          <section v-if="results?.data.posts.length">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-neutral-500">{{ t('nav.insights') }}</h2>
            <ul class="divide-y divide-neutral-100 rounded-lg border border-neutral-200">
              <li v-for="item in results.data.posts" :key="item.id">
                <NuxtLink
                  :to="localePath({ name: 'tin-tuc-slug', params: { slug: item.slug } })"
                  class="block px-5 py-4 transition hover:bg-neutral-50"
                >
                  <p class="font-medium text-primary-600">{{ item.title }}</p>
                  <p v-if="item.excerpt" class="mt-1 line-clamp-2 text-sm text-neutral-600">{{ item.excerpt }}</p>
                </NuxtLink>
              </li>
            </ul>
          </section>
        </div>
      </template>
    </div>
  </div>
</template>
