<script setup lang="ts">
import type { AdminService, Envelope } from '~/types/api'

/**
 * Listing for the two hierarchical catalogues (services, industries), which
 * share a shape: ordered roots, each with its children indented beneath.
 */
const props = defineProps<{
  endpoint: 'services' | 'industries'
  title: string
  subtitle: string
}>()

const api = useApi()
const { user } = useAuth()
const { remove } = useAdminResource(props.endpoint)

const { data: items, refresh } = await useAsyncData(
  `admin:${props.endpoint}`,
  async () => (await api<Envelope<AdminService[]>>(`/admin/${props.endpoint}`)).data,
)

function nameOf(item: AdminService): string {
  return item.translations?.vi?.name ?? item.translations?.en?.name ?? '(chưa đặt tên)'
}

async function destroy(item: AdminService) {
  if (!window.confirm(`Xoá "${nameOf(item)}"? Các mục con cũng sẽ bị xoá.`)) {
    return
  }

  if (await remove(item.id)) {
    await refresh()
  }
}
</script>

<template>
  <div>
    <AdminPageHeader :title="title" :subtitle="subtitle">
      <template #actions>
        <UButton :to="`/admin/${endpoint}/new`" icon="i-lucide-plus">Thêm mới</UButton>
      </template>
    </AdminPageHeader>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white">
      <table class="w-full text-sm">
        <thead class="border-b border-neutral-200 bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-500">
          <tr>
            <th class="px-5 py-2.5 font-medium">Tên</th>
            <th class="w-32 px-5 py-2.5 font-medium">Trạng thái</th>
            <th class="w-24 px-5 py-2.5 font-medium">Thứ tự</th>
            <th class="w-28 px-5 py-2.5" />
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <template v-for="item in items" :key="item.id">
            <tr class="hover:bg-neutral-50">
              <td class="px-5 py-3">
                <NuxtLink :to="`/admin/${endpoint}/${item.id}`" class="font-medium text-neutral-800 hover:text-primary-600">
                  {{ nameOf(item) }}
                </NuxtLink>
                <UIcon v-if="item.is_featured" name="i-lucide-star" class="ml-1.5 size-3.5 text-amber-500" title="Nổi bật" />
              </td>
              <td class="px-5 py-3">
                <AdminStatusBadge :status="item.status" :published-at="item.published_at" />
              </td>
              <td class="px-5 py-3 text-neutral-500">{{ item.sort_order }}</td>
              <td class="px-5 py-3 text-right">
                <UButton
                  v-if="user?.role === 'admin'"
                  size="xs"
                  variant="ghost"
                  color="error"
                  icon="i-lucide-trash-2"
                  @click="destroy(item)"
                />
              </td>
            </tr>
            <tr v-for="child in item.children" :key="child.id" class="hover:bg-neutral-50">
              <td class="py-2.5 pl-12 pr-5">
                <NuxtLink :to="`/admin/${endpoint}/${child.id}`" class="text-neutral-600 hover:text-primary-600">
                  <UIcon name="i-lucide-corner-down-right" class="mr-1.5 size-3.5 text-neutral-400" />
                  {{ nameOf(child) }}
                </NuxtLink>
              </td>
              <td class="px-5 py-2.5">
                <AdminStatusBadge :status="child.status" :published-at="child.published_at" />
              </td>
              <td class="px-5 py-2.5 text-neutral-500">{{ child.sort_order }}</td>
              <td class="px-5 py-2.5 text-right">
                <UButton
                  v-if="user?.role === 'admin'"
                  size="xs"
                  variant="ghost"
                  color="error"
                  icon="i-lucide-trash-2"
                  @click="destroy(child)"
                />
              </td>
            </tr>
          </template>
          <tr v-if="!items?.length">
            <td colspan="4" class="px-5 py-10 text-center text-neutral-500">Chưa có mục nào.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
