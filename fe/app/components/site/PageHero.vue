<script setup lang="ts">
import type { Media } from '~/types/api'

defineProps<{ title: string, subtitle?: string | null, cover?: Media | null }>()
</script>

<template>
  <section class="relative isolate overflow-hidden bg-cream-300">
    <!--
      `sizes` phải có tiền tố breakpoint. Viết trơ trọi là `sizes="100vw"` thì
      @nuxt/image v2 không hiểu là "rộng bằng khung nhìn" mà sinh ra srcset
      `w_1 1w, w_2 2w` — ảnh bìa được phục vụ ở đúng 1 pixel rồi kéo giãn ra cả
      màn hình. Nằm dưới `opacity-25` nên nó chỉ trông như một mảng màu nhạt,
      không ai nhận ra là hỏng.
    -->
    <NuxtImg
      v-if="cover"
      :src="cover.url"
      :alt="cover.alt ?? title"
      class="absolute inset-0 size-full object-cover opacity-25"
      sizes="sm:100vw md:100vw lg:100vw xl:100vw"
      preload
    />
    <div v-else class="absolute -right-32 top-1/2 size-96 -translate-y-1/2 rounded-full bg-brand-500/15 blur-3xl" aria-hidden="true" />

    <div class="relative mx-auto max-w-8xl px-4 py-16 sm:px-6 lg:px-8 xl:px-12">
      <h1 class="max-w-4xl text-3xl font-bold tracking-tight text-primary-900 sm:text-4xl">{{ title }}</h1>
      <p v-if="subtitle" class="mt-4 max-w-2xl text-lg text-primary-500">{{ subtitle }}</p>
    </div>
  </section>
</template>
