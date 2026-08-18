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

/**
 * The wordmark is set so its capitals stand exactly as tall as the mark beside
 * it, which is what makes the pair read as one lockup rather than a logo with
 * a label. Inter's cap height is 0.727 em, so the type size is the mark's
 * height divided by that — derived rather than hard-coded so re-sizing the
 * mark carries the wordmark with it.
 */
const wordmarkSize = computed(() => Math.round(props.size / 0.727))
</script>

<template>
  <!--
    The wordmark names the link, so the mark itself is decorative (alt="").

    A column of two rows: the mark and TAIDA side by side on the first, the
    slogan underneath spanning the whole block. `inline-flex` keeps the block
    only as wide as its widest row, which is what lets the header lay it out
    against the nav without a wrapper.

        [ LOGO ] TAIDA
        Your  Partner  for  Business  Excellence
  -->
  <NuxtLink
    :to="localePath('index')"
    class="inline-flex flex-col items-start"
  >
    <span class="flex items-center gap-2.5">
      <!--
        The mark sits on a white plate. Logos are drawn for paper, so they
        usually arrive as an opaque light-background file — dropping one
        straight onto a coloured bar leaves a pale rectangle floating there.
        The plate makes that background look deliberate, and it works whatever
        shape the client uploads later.
      -->
      <span
        class="flex shrink-0 items-center justify-center rounded-md bg-white p-1.5 ring-1 ring-primary-950/5"
        :style="{ height: `${size + 12}px` }"
      >
        <!--
          Served through @nuxt/image whether it is the bundled file or an
          upload: both come back as a WebP at twice the rendered size, from
          this origin. Handing the browser the original instead means a second
          connection to the API and a file an order of magnitude too big.
        -->
        <NuxtImg
          :src="src"
          alt=""
          format="webp"
          :width="width * 2"
          :height="size * 2"
          :style="{ height: `${size}px`, width: 'auto' }"
          class="max-w-40 object-contain"
        />
      </span>

      <!--
        Wordmark and slogan are brand copy, not interface copy: they read the
        same in both locales, so they stay here rather than in the message
        files. Both inherit their colour from whatever bar they sit on.
      -->
      <span
        class="font-bold leading-none tracking-tight"
        :style="{ fontSize: `${wordmarkSize}px` }"
      >TAIDA</span>
    </span>

    <!--
      `text-align-last: justify` sets the slogan to the exact width of the
      mark-plus-wordmark row above it: the row is the wider of the two, so the
      slogan's word spaces open up to fill the difference and the two rows end
      on the same vertical line. Left ragged, the block looked like it was
      leaning off the right edge of the lockup.

      It only appears from sm: at ~200 px it would push the search, locale and
      menu buttons off a narrow phone. Hiding it collapses the row, leaving the
      mark and wordmark side by side.
    -->
    <span
      class="mt-2 hidden w-full text-[11px] font-medium leading-none opacity-80 [text-align-last:justify] sm:block"
    >
      Your Partner for Business Excellence
    </span>
  </NuxtLink>
</template>
