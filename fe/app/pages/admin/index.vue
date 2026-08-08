<script setup lang="ts">
import type { AdminCategory, AdminPage, AdminService, AdminPost, Paginated, Envelope } from '~/types/api'

definePageMeta({ layout: 'admin', middleware: 'auth' })

const api = useApi()

const { data } = await useAsyncData('admin:dashboard', async () => {
  const [services, industries, posts, pages] = await Promise.all([
    api<Envelope<AdminService[]>>('/admin/services', { query: { tree: 0 } }),
    api<Envelope<AdminService[]>>('/admin/industries', { query: { tree: 0 } }),
    api<Paginated<AdminPost>>('/admin/posts', { query: { per_page: 5 } }),
    api<Envelope<AdminPage[]>>('/admin/pages'),
  ])

  return { services: services.data, industries: industries.data, posts, pages: pages.data }
})

const tiles = computed(() => [
  { label: 'Dịch vụ', value: data.value?.services.length ?? 0, to: '/admin/services', icon: 'i-lucide-wrench' },
  { label: 'Ngành nghề', value: data.value?.industries.length ?? 0, to: '/admin/industries', icon: 'i-lucide-factory' },
  { label: 'Bài viết', value: data.value?.posts.meta.total ?? 0, to: '/admin/posts', icon: 'i-lucide-newspaper' },
  { label: 'Trang tĩnh', value: data.value?.pages.length ?? 0, to: '/admin/pages', icon: 'i-lucide-file-text' },
])

function titleOf(post: AdminPost): string {
  return post.translations.vi?.title ?? post.translations.en?.title ?? '(chưa đặt tiêu đề)'
}
</script>

<template>
  <div>
    <AdminPageHeader title="Tổng quan" subtitle="Tình trạng nội dung trên site." />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <NuxtLink
        v-for="tile in tiles"
        :key="tile.label"
        :to="tile.to"
        class="rounded-lg border border-neutral-200 bg-white p-5 transition hover:border-primary-300 hover:shadow-sm"
      >
        <div class="flex items-center justify-between">
          <span class="text-sm text-neutral-500">{{ tile.label }}</span>
          <UIcon :name="tile.icon" class="size-4 text-primary-500" />
        </div>
        <p class="mt-2 text-2xl font-semibold text-neutral-900">{{ tile.value }}</p>
      </NuxtLink>
    </div>

    <div class="mt-8 rounded-lg border border-neutral-200 bg-white">
      <h2 class="border-b border-neutral-200 px-5 py-3 text-sm font-semibold text-neutral-700">
        Bài viết gần đây
      </h2>
      <ul class="divide-y divide-neutral-100">
        <li v-for="post in data?.posts.data" :key="post.id" class="flex items-center gap-3 px-5 py-3">
          <NuxtLink :to="`/admin/posts/${post.id}`" class="flex-1 truncate text-sm text-neutral-800 hover:text-primary-600">
            {{ titleOf(post) }}
          </NuxtLink>
          <AdminStatusBadge :status="post.status" :published-at="post.published_at" />
        </li>
        <li v-if="!data?.posts.data.length" class="px-5 py-6 text-center text-sm text-neutral-500">
          Chưa có bài viết nào.
        </li>
      </ul>
    </div>
  </div>
</template>
