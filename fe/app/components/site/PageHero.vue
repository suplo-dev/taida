<script setup lang="ts">
import type { Media } from '~/types/api'

defineProps<{ title: string, subtitle?: string | null, cover?: Media | null }>()
</script>

<template>
  <section class="relative isolate overflow-hidden bg-primary-600">
    <NuxtImg
      v-if="cover"
      :src="cover.url"
      :alt="cover.alt ?? title"
      class="absolute inset-0 size-full object-cover opacity-30"
      sizes="100vw"
      preload
    />
    <div v-else class="absolute -right-32 top-1/2 size-96 -translate-y-1/2 rounded-full bg-accent-500/20 blur-3xl" aria-hidden="true" />

    <div class="relative mx-auto max-w-8xl px-4 py-16 sm:px-6 lg:px-8 xl:px-12">
      <h1 class="max-w-4xl text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ title }}</h1>
      <p v-if="subtitle" class="mt-4 max-w-2xl text-lg text-primary-100">{{ subtitle }}</p>
    </div>
  </section>
</template>
