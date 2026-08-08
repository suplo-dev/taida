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
}>()

const active = ref<Locale>(PRIMARY_LOCALE)

function isFilled(locale: Locale): boolean {
  const value = props.translations[locale]?.[props.titleField]
  return typeof value === 'string' && value.trim().length > 0
}
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
          v-if="locale !== PRIMARY_LOCALE && !isFilled(locale)"
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
