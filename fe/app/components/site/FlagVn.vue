<script setup lang="ts">
/**
 * The Vietnamese flag, drawn inline. Nuxt UI only ships the Lucide set here,
 * which has no flags, and a one-off raster asset for a 16 px mark would cost a
 * request and blur on retina — two shapes of SVG are cheaper than either.
 *
 * Decorative unless given a `title`. Every place it is used today sits it next
 * to the same name in visible text, and an SVG `<title>` there is not extra
 * politeness — it is the row read out twice, "Việt Nam, Việt Nam". Naming it
 * costs nothing where the flag stands alone; the default is the case that
 * actually occurs.
 */
defineProps<{
  /** Accessible name, for a flag with no visible label beside it. */
  title?: string
}>()
</script>

<template>
  <!--
    3:2, the flag's own proportion, in both the viewBox and the rendered box —
    a 20×14 box against a 30×20 drawing stretched it a few percent wide. 30×20
    rather than the 24×16 it started at: beside 14 px type the smaller mark was
    reading as an icon instead of as a flag, and the star lost its points.
  -->
  <!--
    `aria-label` rather than a `<title>` child: same accessible name, but it
    leaves the markup with one `<title>` in it, which is the document's.
  -->
  <svg
    viewBox="0 0 30 20"
    class="h-5 w-[1.875rem] shrink-0 rounded-[1px]"
    :role="title ? 'img' : undefined"
    :aria-label="title"
    :aria-hidden="title ? undefined : 'true'"
  >
    <rect width="30" height="20" fill="#da251d" />
    <!--
      Centred on (15,10) — the middle of the field — with a circumradius of 6,
      i.e. a fifth of the flag's length, which is the standard construction.
      The previous star was both oversized and centred ~1.8 units low, so it
      sat visibly below the middle.
    -->
    <polygon
      fill="#ff0"
      points="15,4 16.347,8.146 20.706,8.146 17.18,10.708 18.527,14.854 15,12.292 11.473,14.854 12.82,10.708 9.294,8.146 13.653,8.146"
    />
  </svg>
</template>
