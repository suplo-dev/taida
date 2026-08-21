<script setup lang="ts">
import type { Locale } from '~/types/api'

/**
 * Side-by-side editing of the same record in every locale. The tab for a
 * locale with nothing filled in is flagged, since a blank secondary locale
 * means the public site will fall back to Vietnamese.
 */
const props = defineProps<{
  /** Field whose emptiness marks a locale as "not translated yet". */
  titleField: string
  translations: Record<string, Record<string, unknown>>
  /** Validation messages from the API, keyed by `translations.<locale>.<field>`. */
  errors?: Record<string, string[]>
}>()

const active = ref<Locale>(PRIMARY_LOCALE)

function isFilled(locale: Locale): boolean {
  const value = props.translations[locale]?.[props.titleField]
  return typeof value === 'string' && value.trim().length > 0
}

function hasError(locale: Locale): boolean {
  return Object.keys(props.errors ?? {}).some(field => field.startsWith(`translations.${locale}.`))
}

/*
 * Lưu xong mà lỗi nằm ở tab khác thì form trông như không có gì sai. Nhảy thẳng
 * sang tab đầu tiên có lỗi ngay khi API trả về.
 */
watch(() => props.errors, () => {
  const errored = SUPPORTED_LOCALES.find(hasError)
  if (errored && !hasError(active.value)) active.value = errored
}, { deep: true })
</script>

<template>
  <div>
    <div class="flex items-center gap-1 border-b border-neutral-200">
      <button
        v-for="locale in SUPPORTED_LOCALES"
        :key="locale"
        type="button"
        class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition"
        :class="active === locale
          ? 'border-primary-500 text-primary-700'
          : 'border-transparent text-neutral-500 hover:text-neutral-800'"
        @click="active = locale"
      >
        {{ LOCALE_LABELS[locale] }}
        <UIcon
          v-if="hasError(locale)"
          name="i-lucide-circle-x"
          class="size-3.5 text-red-500"
          title="Tab này còn ô chưa hợp lệ"
        />
        <UIcon
          v-else-if="locale !== PRIMARY_LOCALE && !isFilled(locale)"
          name="i-lucide-circle-alert"
          class="size-3.5 text-amber-500"
          title="Chưa dịch — site sẽ hiển thị bản tiếng Việt"
        />
      </button>
    </div>

    <div class="pt-5">
      <slot :locale="active" />
    </div>
  </div>
</template>
