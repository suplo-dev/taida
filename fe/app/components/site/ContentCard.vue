<script setup lang="ts">
import type { Media } from '~/types/api'

/**
 * The one card used across services, industries and posts. Falls back to a
 * navy gradient when a record has no cover image, so listings stay uniform
 * before the client has uploaded artwork.
 */
const props = defineProps<{
  to: string
  title: string
  excerpt?: string | null
  cover?: Media | null
  icon?: string | null
  meta?: string | null
  /** Taller image area for the industry grid. */
  imageFirst?: boolean
}>()

const gradient = computed(() => {
  // Deterministic tint per title so a grid does not look monotonous.
  const seed = [...props.title].reduce((sum, char) => sum + char.charCodeAt(0), 0)
  return ['from-primary-500 to-primary-700', 'from-primary-700 to-primary-600', 'from-accent-700 to-primary-600'][seed % 3]
})
</script>

<template>
  <NuxtLink
    :to="to"
    class="group flex flex-col overflow-hidden rounded-lg border border-neutral-200 bg-white transition hover:border-primary-300 hover:shadow-lg"
  >
    <div v-if="imageFirst" class="relative aspect-[4/3] overflow-hidden">
      <NuxtImg
        v-if="cover"
        :src="cover.url"
        :alt="cover.alt ?? title"
        loading="lazy"
        sizes="sm:100vw md:50vw lg:25vw"
        class="size-full object-cover transition duration-500 group-hover:scale-105"
      />
      <div v-else class="flex size-full items-center justify-center bg-gradient-to-br" :class="gradient">
        <UIcon :name="`i-lucide-${icon || 'layers'}`" class="size-10 text-white/70" />
      </div>
    </div>

    <div class="flex flex-1 flex-col p-5">
      <UIcon
        v-if="icon && !imageFirst"
        :name="`i-lucide-${icon}`"
        class="mb-3 size-7 text-primary-500"
      />

      <p v-if="meta" class="mb-1.5 text-xs font-medium uppercase tracking-wide text-primary-500">{{ meta }}</p>

      <h3 class="font-semibold text-primary-700 transition group-hover:text-accent-700">
        {{ title }}
      </h3>

      <p v-if="excerpt" class="mt-2 line-clamp-3 text-sm leading-relaxed text-neutral-600">
        {{ excerpt }}
      </p>

      <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary-600">
        {{ $t('common.readMore') }}
        <UIcon name="i-lucide-arrow-right" class="size-4 transition group-hover:translate-x-0.5" />
      </span>
    </div>
  </NuxtLink>
</template>
