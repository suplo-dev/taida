<script setup lang="ts">
/**
 * Social share card. Rendered headlessly by nuxt-og-image, so it may only use
 * plain Tailwind utilities — no runtime data fetching, no custom fonts.
 */
withDefaults(defineProps<{ title?: string, description?: string, siteName?: string }>(), {
  title: 'Taida',
  siteName: 'Taida',
})
</script>

<template>
  <!--
    The font family has to be named explicitly. Left unset, the renderer uses
    its own bundled default, which stops at Latin-1 — every ư ơ đ ệ ợ in a
    Vietnamese headline came out as a "no glyph" box. Inter is registered by
    @nuxt/fonts with the vietnamese subset, so naming it here is enough.
  -->
  <div
    class="flex h-full w-full flex-col justify-between bg-[#003a70] p-20"
    style="font-family: Inter"
  >
    <div class="flex text-4xl font-bold text-white">{{ siteName }}</div>

    <div class="flex flex-col">
      <div class="flex text-6xl font-bold leading-tight text-white">{{ title }}</div>
      <div v-if="description" class="mt-6 flex text-2xl leading-relaxed text-[#bcdcff]">
        {{ description.slice(0, 140) }}
      </div>
    </div>

    <div class="flex h-2 w-40 bg-[#00a3e0]" />
  </div>
</template>
