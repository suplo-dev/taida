<script setup lang="ts">
import type { ContentStatus } from '~/types/api'

const props = defineProps<{ status: ContentStatus, publishedAt?: string | null }>()

/** A published record dated in the future is scheduled, not yet live. */
const state = computed(() => {
  if (props.status !== 'published') {
    return { label: 'Nháp', classes: 'bg-neutral-100 text-neutral-600' }
  }

  if (props.publishedAt && new Date(props.publishedAt) > new Date()) {
    return { label: 'Hẹn giờ', classes: 'bg-amber-100 text-amber-700' }
  }

  return { label: 'Đã đăng', classes: 'bg-emerald-100 text-emerald-700' }
})
</script>

<template>
  <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium" :class="state.classes">
    {{ state.label }}
  </span>
</template>
