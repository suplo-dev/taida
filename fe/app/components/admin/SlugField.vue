<script setup lang="ts">
import type { Locale } from '~/types/api'
import { LOCALE_FALLBACKS, MIRRORED_SLUG_LOCALES } from '~~/shared/content-urls'

/**
 * Ô đường dẫn của một ngôn ngữ.
 *
 * Tiếng Trung không có ô: nó dùng đúng địa chỉ của bản tiếng Anh, nên ở đây chỉ
 * hiện ra cho thấy nó sẽ là gì. Lý do nằm ở `app.mirrored_slug_locales` bên API
 * — `Str::slug()` bỏ hết ký tự Hán, nên một tiêu đề tiếng Trung không còn gì để
 * dựng địa chỉ, và mượn của tiếng Anh thì /zh/about-us là cùng một địa chỉ dù
 * trang đã dịch hay chưa.
 */
const slug = defineModel<string>({ required: true })

const props = defineProps<{
  locale: Locale
  /** Cả cụm bản dịch, để suy ra slug mà ngôn ngữ này sẽ soi. */
  translations: Record<Locale, { slug?: string }>
  error?: string
  label?: string
  hint?: string
  size?: 'sm' | 'md'
}>()

const mirrored = computed(() => MIRRORED_SLUG_LOCALES.includes(props.locale))

/**
 * Chỉ để xem: giá trị thật do API ghi lúc lưu. Ô này đọc bản đang gõ dở trong
 * form, nên nó đổi theo ngay khi sửa slug tiếng Anh.
 */
const mirroredSlug = computed(() => {
  for (const source of LOCALE_FALLBACKS[props.locale]) {
    const value = props.translations[source]?.slug?.trim()

    if (value) {
      return value
    }
  }

  return ''
})

const sourceLabel = computed(() => LOCALE_LABELS[LOCALE_FALLBACKS[props.locale][0] ?? PRIMARY_LOCALE])
</script>

<template>
  <AdminFormField
    :label="label ?? 'Đường dẫn (slug)'"
    :error="error"
    :hint="mirrored ? undefined : (hint ?? 'Để trống sẽ tự sinh từ tiêu đề.')"
  >
    <UInput
      v-if="mirrored"
      :model-value="mirroredSlug"
      disabled
      :size="size ?? 'md'"
      class="w-full"
      placeholder="theo bản tiếng Anh"
    />
    <UInput v-else v-model="slug" :size="size ?? 'md'" class="w-full" placeholder="tu-dong-sinh" />

    <template v-if="mirrored" #hint>
      Dùng chung đường dẫn với bản {{ sourceLabel }}, không sửa riêng được — tiêu đề
      tiếng Trung không dựng được địa chỉ, và dùng chung thì địa chỉ không đổi khi
      trang được dịch.
    </template>
  </AdminFormField>
</template>
