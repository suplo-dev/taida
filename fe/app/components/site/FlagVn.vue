<script setup lang="ts">
/**
 * The Vietnamese flag, drawn inline. Nuxt UI only ships the Lucide set here,
 * which has no flags, and a one-off raster asset for a 16 px mark would cost a
 * request and blur on retina — two shapes of SVG are cheaper than either.
 */
withDefaults(defineProps<{ title?: string }>(), { title: 'Việt Nam' })
</script>

<template>
  <svg viewBox="0 0 30 20" class="h-3.5 w-5 shrink-0 rounded-[1px]" role="img">
    <title>{{ title }}</title>
    <rect width="30" height="20" fill="#da251d" />
    <path
      fill="#ff0"
      d="m15 4 1.76 5.42h5.7l-4.61 3.34 1.76 5.42L15 14.84l-4.61 3.34 1.76-5.42-4.61-3.34h5.7z"
    />
  </svg>
</template>
