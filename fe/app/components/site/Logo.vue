<script setup lang="ts">
import type { Media } from '~/types/api'

const props = withDefaults(defineProps<{
  /** Uploaded in Cấu hình → Thương hiệu. Null falls back to the bundled mark. */
  logo?: Media | null
  /** Rendered height of the mark from `sm` up, in pixels; the width follows the aspect ratio. */
  size?: number
  /**
   * Its height below `sm`. A phone bar has to hold the lockup plus the search
   * and menu buttons inside ~285 px of content width, which the full-size
   * lockup (~211 px) overflowed; at 30 px it runs to ~174 px and fits.
   */
  compact?: number
}>(), { logo: null, size: 36, compact: 30 })

const localePath = useLocalePath()

/**
 * Every measurement in the lockup hangs off the mark's height, so it is handed
 * to CSS as `--mark` and the plate and the wordmark are sized from it there.
 * That is what lets the whole lockup change size at `sm` from one declaration
 * — a JS number could not, since the breakpoint is not knowable on the server.
 */
const marks = computed(() => ({ '--mark-compact': `${props.compact}px`, '--mark-full': `${props.size}px` }))

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

    A column of two rows: the mark and TAIDA side by side on the first, the
    slogan underneath spanning the whole block. `inline-flex` keeps the block
    only as wide as its widest row, which is what lets the header lay it out
    against the nav without a wrapper.

        [ LOGO ] TAIDA
        Your  Partner  for  Business  Excellence
  -->
  <NuxtLink
    :to="localePath('index')"
    :style="marks"
    class="inline-flex flex-col items-start [--mark:var(--mark-compact)] sm:[--mark:var(--mark-full)]"
  >
    <!--
      `sm:gap-4` rather than a tighter gap all the way up: from sm the row's
      width is what the slogan below justifies out to, and at 10 px the row
      measured ~205 px against the slogan's ~208 px — three pixels short, which
      is enough to make the justify inert. See the slogan's own note below.
      Below sm the slogan is hidden and the bar is short of room, so the tighter
      gap stands there.
    -->
    <span class="flex items-center gap-2.5 sm:gap-4">
      <!--
        The mark sits on a white plate. Logos are drawn for paper, so they
        usually arrive as an opaque light-background file — dropping one
        straight onto a coloured bar leaves a pale rectangle floating there.
        The plate makes that background look deliberate, and it works whatever
        shape the client uploads later.
      -->
      <span class="flex h-[calc(var(--mark)_+_12px)] shrink-0 items-center justify-center rounded-md bg-white p-1.5 ring-1 ring-primary-950/5">
        <NuxtImg
          :src="src"
          alt=""
          format="webp"
          :width="width * 2"
          :height="size * 2"
          class="h-[var(--mark)] w-auto max-w-40 object-contain"
        />
      </span>
      <span class="font-bold leading-none tracking-tight pt-1 [font-size:calc(var(--mark)/0.74)]">TAIDA</span>
    </span>
    <span
      class="mt-2 hidden w-full text-[12px] font-bold leading-none tracking-[-0.03em] opacity-80 [text-align-last:justify] sm:block"
    >
      Your Partner for Business Excellence
    </span>
  </NuxtLink>
</template>
