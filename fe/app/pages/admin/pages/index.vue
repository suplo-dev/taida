<script setup lang="ts">
import type { AdminPage, Envelope } from '~/types/api'

definePageMeta({ layout: 'admin', middleware: 'auth' })

const api = useApi()
const { user } = useAuth()
const { remove } = useAdminResource('pages')

const { data: pages, refresh } = await useAsyncData(
  'admin:pages',
  async () => (await api<Envelope<AdminPage[]>>('/admin/pages')).data,
)

function titleOf(page: AdminPage): string {
  return page.translations?.vi?.title ?? page.translations?.en?.title ?? page.key
}

async function destroy(page: AdminPage) {
  if (window.confirm(`Xoá trang "${titleOf(page)}"?`) && await remove(page.id)) {
    await refresh()
  }
}
</script>

<template>
  <div>
    <AdminPageHeader title="Trang tĩnh" subtitle="Giới thiệu, chính sách và các trang nội dung cố định.">
      <template #actions>
        <UButton to="/admin/pages/new" icon="i-lucide-plus">Thêm trang</UButton>
      </template>
    </AdminPageHeader>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white">
      <table class="w-full text-sm">
        <thead class="border-b border-neutral-200 bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-500">
          <tr>
            <th class="px-5 py-2.5 font-medium">Tiêu đề</th>
            <th class="w-48 px-5 py-2.5 font-medium">Khoá</th>
            <th class="w-32 px-5 py-2.5 font-medium">Trạng thái</th>
            <th class="w-24 px-5 py-2.5" />
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-for="page in pages" :key="page.id" class="hover:bg-neutral-50">
            <td class="px-5 py-3">
              <NuxtLink :to="`/admin/pages/${page.id}`" class="font-medium text-neutral-800 hover:text-primary-600">
                {{ titleOf(page) }}
              </NuxtLink>
            </td>
            <td class="px-5 py-3">
              <code class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs text-neutral-600">{{ page.key }}</code>
            </td>
            <td class="px-5 py-3">
              <AdminStatusBadge :status="page.status" />
            </td>
            <td class="px-5 py-3 text-right">
              <UButton
                v-if="user?.role === 'admin'"
                size="xs"
                variant="ghost"
                color="error"
                icon="i-lucide-trash-2"
                @click="destroy(page)"
              />
            </td>
          </tr>
          <tr v-if="!pages?.length">
            <td colspan="4" class="px-5 py-10 text-center text-neutral-500">Chưa có trang nào.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
