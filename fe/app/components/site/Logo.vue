<script setup lang="ts">
import type { Media } from '~/types/api'

const props = withDefaults(defineProps<{
  /** Uploaded in Cấu hình → Thương hiệu. Null falls back to the bundled mark. */
  logo?: Media | null
  /** Rendered height of the mark in pixels; the width follows the aspect ratio. */
  size?: number
}>(), { logo: null, size: 28 })

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
  <!--
    The wordmark names the link, so the mark itself is decorative (alt="").

    Two columns, two rows: the mark and TAIDA sit side by side on the first
    row, centred against each other; the slogan spans both columns underneath,
    so it starts at the left edge of the mark and runs on under the wordmark.
    `inline-grid` keeps the block only as wide as its widest row, which is what
    lets the header lay it out against the nav without a wrapper.

        [ LOGO ] TAIDA
        your partner for business excellence
  -->
  <NuxtLink
    :to="localePath('index')"
    class="inline-grid grid-cols-[auto_1fr] items-center gap-x-2.5"
  >
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
        the API and a file an order of magnitude too big for a 32 px slot.
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
    -->
    <!--
      Sized against the mark, not against the nav: at 20 px the plate stood a
      little under three times the cap height of the wordmark and read as the
      logo with a caption beside it. At 24 px the two carry about equal weight,
      which is what makes them read as one lockup.
    -->
    <span class="text-2xl font-bold leading-none tracking-tight">TAIDA</span>

    <!--
      The slogan is what sets the width of the whole block, and at ~190 px it
      would push the search, locale and menu buttons off a narrow phone —
      hence it only appears once there is room for it. Hiding it collapses the
      row, leaving the mark and wordmark side by side.
    -->
    <span
      class="col-span-2 mt-1.5 hidden text-[10px] font-medium leading-none tracking-wide text-primary-200 sm:block"
    >
      Your Partner for Business Excellence
    </span>
  </NuxtLink>
</template>
