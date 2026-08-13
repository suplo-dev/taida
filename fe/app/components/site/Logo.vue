<script setup lang="ts">
import type { Media } from '~/types/api'

const props = withDefaults(defineProps<{
  /** Uploaded in Cấu hình → Thương hiệu. Null falls back to the bundled mark. */
  logo?: Media | null
  /** Rendered height of the mark in pixels; the width follows the aspect ratio. */
  size?: number
}>(), { logo: null, size: 36 })

const localePath = useLocalePath()

const src = computed(() => props.logo?.url ?? '/logo.jpg')

/**
 * Reserving the exact box the image will occupy keeps the header from
 * reflowing once it loads. The bundled mark is square; an upload is measured
 * on the way in, so its real ratio is known too.
 */
const width = computed(() => {
  const { width: w, height: h } = props.logo ?? {}

  return w && h ? Math.round((w / h) * props.size) : props.size
})
</script>

<template>
  <!-- The wordmark names the link, so the mark itself is decorative (alt=""). -->
  <NuxtLink :to="localePath('index')" class="flex items-center gap-2.5">
    <!--
      The mark sits on a white plate. Logos are drawn for paper, so they
      usually arrive as an opaque light-background file — dropping one
      straight onto the navy bar leaves a pale rectangle floating there. The
      plate makes that background look deliberate, and it works whatever
      shape the client uploads later.
    -->
    <span
      class="flex shrink-0 items-center justify-center rounded-md bg-white p-1"
      :style="{ height: `${size + 8}px` }"
    >
      <!--
        Served through @nuxt/image whether it is the bundled file or an upload:
        both come back as a WebP at twice the rendered size, from this origin.
        Handing the browser the original instead means a second connection to
        the API and a file an order of magnitude too big for a 36 px slot.
      -->
      <NuxtImg
        :src="src"
        alt=""
        format="webp"
        :width="width * 2"
        :height="size * 2"
        :style="{ height: `${size}px`, width: 'auto' }"
        class="max-w-32 object-contain"
      />
    </span>

    <!--
      Wordmark and slogan are brand copy, not interface copy: they read the
      same in both locales, so they stay here rather than in the message files.
      The slogan is long enough to crowd the mobile header, where it competes
      with the hotline, search and menu buttons — hence it only appears once
      there is room for it.
    -->
    <span class="flex flex-col leading-none">
      <span class="text-xl font-bold tracking-tight">TAIDA</span>
      <span class="mt-1 hidden text-[10px] font-medium tracking-wide text-primary-200 sm:block">
        Your Partner for Business Excellence
      </span>
    </span>
  </NuxtLink>
</template>
