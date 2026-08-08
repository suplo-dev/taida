<script setup lang="ts">
import type { AdminPost, Paginated } from '~/types/api'

definePageMeta({ layout: 'admin', middleware: 'auth' })

const api = useApi()
const { user } = useAuth()
const { remove } = useAdminResource('posts')

const page = ref(1)
const status = ref<string | undefined>(undefined)
const search = ref('')

const { data, refresh } = await useAsyncData(
  'admin:posts',
  () => api<Paginated<AdminPost>>('/admin/posts', {
    query: { page: page.value, status: status.value, q: search.value || undefined },
  }),
  { watch: [page, status] },
)

// Typing shouldn't fire a request per keystroke.
const debouncedSearch = useDebounceFn(() => {
  page.value = 1
  refresh()
}, 400)

function titleOf(post: AdminPost): string {
  return post.translations.vi?.title ?? post.translations.en?.title ?? '(chưa đặt tiêu đề)'
}

async function destroy(post: AdminPost) {
  if (window.confirm(`Xoá bài "${titleOf(post)}"?`) && await remove(post.id)) {
    await refresh()
  }
}
</script>

<template>
  <div>
    <AdminPageHeader title="Tin tức" :subtitle="`${data?.meta.total ?? 0} bài viết`">
      <template #actions>
        <UButton to="/admin/posts/new" icon="i-lucide-plus">Viết bài</UButton>
      </template>
    </AdminPageHeader>

    <div class="mb-4 flex flex-wrap items-center gap-3">
      <UInput
        v-model="search"
        icon="i-lucide-search"
        placeholder="Tìm theo tiêu đề…"
        class="w-64"
        @update:model-value="debouncedSearch"
      />
      <USelect
        v-model="status"
        class="w-40"
        placeholder="Mọi trạng thái"
        :items="[
          { value: undefined, label: 'Mọi trạng thái' },
          { value: 'published', label: 'Đã đăng' },
          { value: 'draft', label: 'Nháp' },
        ]"
      />
    </div>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white">
      <table class="w-full text-sm">
        <thead class="border-b border-neutral-200 bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-500">
          <tr>
            <th class="px-5 py-2.5 font-medium">Tiêu đề</th>
            <th class="w-32 px-5 py-2.5 font-medium">Trạng thái</th>
            <th class="w-40 px-5 py-2.5 font-medium">Đăng lúc</th>
            <th class="w-24 px-5 py-2.5" />
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-for="post in data?.data" :key="post.id" class="hover:bg-neutral-50">
            <td class="px-5 py-3">
              <NuxtLink :to="`/admin/posts/${post.id}`" class="font-medium text-neutral-800 hover:text-primary-600">
                {{ titleOf(post) }}
              </NuxtLink>
            </td>
            <td class="px-5 py-3">
              <AdminStatusBadge :status="post.status" :published-at="post.published_at" />
            </td>
            <td class="px-5 py-3 text-neutral-500">
              {{ post.published_at ? new Date(post.published_at).toLocaleDateString('vi-VN') : '—' }}
            </td>
            <td class="px-5 py-3 text-right">
              <UButton
                v-if="user?.role === 'admin'"
                size="xs"
                variant="ghost"
                color="error"
                icon="i-lucide-trash-2"
                @click="destroy(post)"
              />
            </td>
          </tr>
          <tr v-if="!data?.data.length">
            <td colspan="4" class="px-5 py-10 text-center text-neutral-500">Không có bài viết nào.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="(data?.meta.last_page ?? 1) > 1" class="mt-4 flex justify-center">
      <UPagination
        v-model:page="page"
        :total="data?.meta.total ?? 0"
        :items-per-page="data?.meta.per_page ?? 20"
      />
    </div>
  </div>
</template>
