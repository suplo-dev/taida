<script setup lang="ts">
import type { Media } from '~/types/api'

const model = defineModel<number | null>({ default: null })

const props = defineProps<{
  preview?: Media | null
  /**
   * Bề ngang tối thiểu để ảnh không vỡ ở chỗ nó được dùng. Ảnh bìa trải hết
   * chiều ngang màn hình, nên một tấm 568 px bị phóng gần bốn lần trên màn
   * 1920 px — cảnh báo ngay lúc chọn để người nhập biết mà thay ảnh lớn hơn.
   */
  minWidth?: number
}>()

const open = ref(false)
const selected = ref<Media | null>(props.preview ?? null)

watch(() => props.preview, value => (selected.value = value ?? null))

/** Không biết kích thước (ảnh cũ chưa đo, SVG) thì không cảnh báo — không có gì để so. */
const tooSmall = computed(() =>
  !!props.minWidth && !!selected.value?.width && selected.value.width < props.minWidth,
)

function choose(media: Media | null) {
  selected.value = media
  model.value = media?.id ?? null
}
</script>

<template>
  <div>
    <div class="flex items-start gap-3">
      <button
        type="button"
        class="flex size-28 shrink-0 items-center justify-center overflow-hidden rounded border border-dashed border-neutral-300 bg-neutral-50 hover:border-primary-400"
        @click="open = true"
      >
        <img v-if="selected" :src="selected.thumbUrl ?? selected.url" :alt="selected.alt ?? ''" class="size-full object-cover">
        <UIcon v-else name="i-lucide-image-plus" class="size-6 text-neutral-400" />
      </button>

      <div class="flex flex-col gap-2 pt-1">
        <UButton size="xs" variant="soft" icon="i-lucide-images" @click="open = true">
          Chọn ảnh
        </UButton>
        <UButton v-if="selected" size="xs" variant="ghost" color="neutral" icon="i-lucide-x" @click="choose(null)">
          Bỏ ảnh
        </UButton>
        <span v-if="selected?.width && selected.height" class="text-xs text-neutral-500">
          {{ selected.width }} × {{ selected.height }} px
        </span>
      </div>
    </div>

    <p v-if="tooSmall" class="mt-2 flex items-start gap-1.5 text-xs text-amber-700">
      <UIcon name="i-lucide-triangle-alert" class="mt-px size-4 shrink-0" />
      <span>
        Ảnh chỉ rộng {{ selected!.width }} px, có thể bị vỡ khi hiển thị. Nên dùng ảnh ngang, rộng từ {{ minWidth }} px trở lên.
      </span>
    </p>

    <AdminMediaLibrary v-model:open="open" :selected-id="selected?.id" :min-width="minWidth" @select="choose" />
  </div>
</template>
