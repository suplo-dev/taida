<script setup lang="ts">
import type { Envelope, Media, Paginated } from '~/types/api'

/**
 * Hộp thoại thư viện ảnh: duyệt, tải lên và chọn một ảnh. Dùng chung cho ô
 * chọn ảnh bìa (`MediaPicker`) và nút chèn ảnh trong trình soạn bài.
 */
const open = defineModel<boolean>('open', { default: false })

const props = defineProps<{
  /** Ảnh đang chọn, để viền nổi lên trong lưới. */
  selectedId?: number | null
  /** Ảnh hẹp hơn mức này được đánh dấu màu vàng trong lưới. */
  minWidth?: number
}>()

const emit = defineEmits<{ select: [Media] }>()

const api = useApi()
const toast = useToast()

/**
 * Một trang vừa đủ sáu hàng của lưới bốn cột, nên trang nào cũng đầy — trước
 * đây picker xin 40 ảnh rồi thôi, thư viện quá 40 tấm thì những tấm cũ hơn
 * không có đường nào chạm tới.
 */
const PER_PAGE = 24

const uploading = ref(false)
const loading = ref(false)
const items = ref<Media[]>([])
const page = ref(1)
const total = ref(0)
/**
 * Tách khỏi `items.length`: thư viện rỗng cũng là kết quả hợp lệ, lấy số ảnh
 * làm dấu "đã nạp chưa" thì mỗi lần mở lại gọi API thêm một lần nữa.
 */
const loaded = ref(false)
const fileInput = useTemplateRef<HTMLInputElement>('fileInput')

async function load() {
  loading.value = true

  try {
    const response = await api<Paginated<Media>>('/admin/media', {
      query: { page: page.value, per_page: PER_PAGE },
    })

    items.value = response.data
    total.value = response.meta.total
    loaded.value = true
  }
  finally {
    loading.value = false
  }
}

watch(page, () => load())

watch(open, (value) => {
  if (value && !loaded.value) {
    load()
  }
})

function choose(media: Media) {
  emit('select', media)
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

    if (page.value === 1) {
      await load()
    }
    else {
      page.value = 1 // watcher nạp lại
    }

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
        {{ loading ? 'Đang tải…' : 'Thư viện chưa có ảnh nào.' }}
      </p>

      <div v-else class="grid max-h-96 grid-cols-4 gap-2 overflow-y-auto transition-opacity" :class="loading ? 'opacity-50' : ''">
        <button
          v-for="item in items"
          :key="item.id"
          type="button"
          class="relative aspect-square overflow-hidden rounded border-2 transition"
          :class="props.selectedId === item.id ? 'border-primary-500' : 'border-transparent hover:border-neutral-300'"
          @click="choose(item)"
        >
          <img :src="item.thumbUrl ?? item.url" :alt="item.alt ?? ''" class="size-full object-cover">
          <span
            v-if="item.width"
            class="absolute inset-x-0 bottom-0 bg-black/55 px-1 py-0.5 text-[10px] text-white"
            :class="minWidth && item.width < minWidth ? 'text-amber-300' : ''"
          >
            {{ item.width }} × {{ item.height }}
          </span>
        </button>
      </div>

      <div v-if="total > PER_PAGE" class="mt-4 flex justify-center">
        <UPagination v-model:page="page" :total="total" :items-per-page="PER_PAGE" size="sm" />
      </div>
    </template>
  </UModal>
</template>
