<script setup lang="ts">
import type { Envelope, Media, Paginated } from '~/types/api'

const model = defineModel<number | null>({ default: null })

const props = defineProps<{ preview?: Media | null }>()

const api = useApi()
const toast = useToast()

const open = ref(false)
const uploading = ref(false)
const items = ref<Media[]>([])
const selected = ref<Media | null>(props.preview ?? null)
const fileInput = useTemplateRef<HTMLInputElement>('fileInput')

watch(() => props.preview, value => (selected.value = value ?? null))

async function load() {
  const response = await api<Paginated<Media>>('/admin/media', { query: { per_page: 40 } })
  items.value = response.data
}

async function openPicker() {
  open.value = true
  if (items.value.length === 0) {
    await load()
  }
}

function choose(media: Media | null) {
  selected.value = media
  model.value = media?.id ?? null
  open.value = false
}

async function upload(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]

  if (!file) {
    return
  }

  uploading.value = true
  const form = new FormData()
  form.append('file', file)

  try {
    const { data } = await api<Envelope<Media>>('/admin/media', { method: 'POST', body: form })
    items.value = [data, ...items.value]
    choose(data)
  }
  catch (error) {
    const message = (error as { data?: { errors?: Record<string, string[]> } })?.data?.errors?.file?.[0]
    toast.add({ title: message ?? 'Tải ảnh lên thất bại', color: 'error' })
  }
  finally {
    uploading.value = false
    if (fileInput.value) {
      fileInput.value.value = ''
    }
  }
}
</script>

<template>
  <div>
    <div class="flex items-start gap-3">
      <button
        type="button"
        class="flex size-28 shrink-0 items-center justify-center overflow-hidden rounded border border-dashed border-neutral-300 bg-neutral-50 hover:border-primary-400"
        @click="openPicker"
      >
        <img v-if="selected" :src="selected.thumbUrl ?? selected.url" :alt="selected.alt ?? ''" class="size-full object-cover">
        <UIcon v-else name="i-lucide-image-plus" class="size-6 text-neutral-400" />
      </button>

      <div class="flex flex-col gap-2 pt-1">
        <UButton size="xs" variant="soft" icon="i-lucide-images" @click="openPicker">
          Chọn ảnh
        </UButton>
        <UButton v-if="selected" size="xs" variant="ghost" color="neutral" icon="i-lucide-x" @click="choose(null)">
          Bỏ ảnh
        </UButton>
      </div>
    </div>

    <UModal v-model:open="open" title="Thư viện ảnh">
      <template #body>
        <div class="mb-4 flex items-center gap-3">
          <UButton size="sm" icon="i-lucide-upload" :loading="uploading" @click="fileInput?.click()">
            Tải ảnh lên
          </UButton>
          <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="upload">
          <span class="text-xs text-neutral-500">JPG, PNG, WebP hoặc AVIF, tối đa 8MB</span>
        </div>

        <p v-if="items.length === 0" class="py-8 text-center text-sm text-neutral-500">
          Thư viện chưa có ảnh nào.
        </p>

        <div v-else class="grid max-h-96 grid-cols-4 gap-2 overflow-y-auto">
          <button
            v-for="item in items"
            :key="item.id"
            type="button"
            class="aspect-square overflow-hidden rounded border-2 transition"
            :class="selected?.id === item.id ? 'border-primary-500' : 'border-transparent hover:border-neutral-300'"
            @click="choose(item)"
          >
            <img :src="item.thumbUrl ?? item.url" :alt="item.alt ?? ''" class="size-full object-cover">
          </button>
        </div>
      </template>
    </UModal>
  </div>
</template>
