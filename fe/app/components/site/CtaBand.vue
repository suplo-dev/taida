<script setup lang="ts">
defineProps<{ title: string, body?: string, hotline?: string }>()
</script>

<template>
  <section class="bg-primary-600">
    <div class="mx-auto flex max-w-8xl flex-col items-start gap-6 px-4 py-14 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8 xl:px-12">
      <div class="max-w-2xl">
        <h2 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ title }}</h2>
        <p v-if="body" class="mt-2 text-primary-100">{{ body }}</p>
      </div>

      <a
        v-if="hotline"
        :href="`tel:${hotline.replace(/\s/g, '')}`"
        class="inline-flex shrink-0 items-center gap-2 rounded bg-brand-600 px-6 py-3 font-semibold text-white transition hover:bg-brand-500"
      >
        <UIcon name="i-lucide-phone" class="size-5" />
        {{ hotline }}
      </a>
    </div>
  </section>
</template>
