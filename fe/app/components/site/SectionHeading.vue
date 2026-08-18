<script setup lang="ts">
defineProps<{ title: string, subtitle?: string, to?: string, linkLabel?: string }>()
</script>

<template>
  <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div class="max-w-2xl">
      <h2 class="text-2xl font-bold tracking-tight text-primary-600 sm:text-3xl">{{ title }}</h2>
      <p v-if="subtitle" class="mt-2 text-neutral-600">{{ subtitle }}</p>
    </div>

    <NuxtLink
      v-if="to"
      :to="to"
      class="inline-flex shrink-0 items-center gap-1 border-b-2 border-accent-500 pb-0.5 text-sm font-medium text-primary-700 transition hover:text-accent-700"
    >
      {{ linkLabel ?? $t('common.viewAll') }}
      <UIcon name="i-lucide-arrow-right" class="size-4" />
    </NuxtLink>
  </div>
</template>
