<script setup lang="ts">
import type { Envelope, Media, Paginated } from '~/types/api'

definePageMeta({ layout: 'admin', middleware: 'auth' })

const api = useApi()
const toast = useToast()
const { user } = useAuth()
const { remove } = useAdminResource('media')

const page = ref(1)
const uploading = ref(false)
const fileInput = useTemplateRef<HTMLInputElement>('fileInput')

const { data, refresh } = await useAsyncData(
  'admin:media',
  () => api<Paginated<Media>>('/admin/media', { query: { page: page.value, per_page: 30 } }),
  { watch: [page] },
)

async function upload(event: Event) {
  const files = (event.target as HTMLInputElement).files

  if (!files?.length) {
    return
  }

  uploading.value = true

  try {
    // Uploaded one at a time so a single rejected file doesn't lose the rest.
    for (const file of Array.from(files)) {
      const form = new FormData()
      form.append('file', file)
      await api<Envelope<Media>>('/admin/media', { method: 'POST', body: form })
    }

    await refresh()
    toast.add({ title: `Đã tải lên ${files.length} ảnh`, color: 'success' })
  }
  catch (error) {
    const message = (error as { data?: { errors?: Record<string, string[]> } })?.data?.errors?.file?.[0]
    toast.add({ title: message ?? 'Tải ảnh lên thất bại', color: 'error' })
    await refresh()
  }
  finally {
    uploading.value = false
    if (fileInput.value) {
      fileInput.value.value = ''
    }
  }
}

async function destroy(media: Media) {
  if (window.confirm('Xoá ảnh này? Các nội dung đang dùng sẽ mất ảnh bìa.') && await remove(media.id)) {
    await refresh()
  }
}
</script>

<template>
  <div>
    <AdminPageHeader title="Thư viện ảnh" :subtitle="`${data?.meta.total ?? 0} ảnh`">
      <template #actions>
        <UButton icon="i-lucide-upload" :loading="uploading" @click="fileInput?.click()">Tải ảnh lên</UButton>
        <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="upload">
      </template>
    </AdminPageHeader>

    <div v-if="data?.data.length" class="grid grid-cols-2 gap-4 sm:grid-cols-4 xl:grid-cols-6">
      <figure
        v-for="item in data.data"
        :key="item.id"
        class="group relative overflow-hidden rounded-lg border border-neutral-200 bg-white"
      >
        <img :src="item.thumbUrl ?? item.url" :alt="item.alt ?? ''" class="aspect-square w-full object-cover">
        <figcaption class="truncate px-2 py-1.5 text-xs text-neutral-500">
          {{ item.width }}×{{ item.height }}
        </figcaption>
        <UButton
          v-if="user?.role === 'admin'"
          size="xs"
          color="error"
          icon="i-lucide-trash-2"
          class="absolute right-1.5 top-1.5 opacity-0 transition group-hover:opacity-100"
          @click="destroy(item)"
        />
      </figure>
    </div>

    <p v-else class="rounded-lg border border-dashed border-neutral-300 py-16 text-center text-sm text-neutral-500">
      Thư viện chưa có ảnh nào.
    </p>

    <div v-if="(data?.meta.last_page ?? 1) > 1" class="mt-6 flex justify-center">
      <UPagination
        v-model:page="page"
        :total="data?.meta.total ?? 0"
        :items-per-page="data?.meta.per_page ?? 30"
      />
    </div>
  </div>
</template>
