<script setup lang="ts">
/**
 * The Vietnamese flag, drawn inline. Nuxt UI only ships the Lucide set here,
 * which has no flags, and a one-off raster asset for a 16 px mark would cost a
 * request and blur on retina — two shapes of SVG are cheaper than either.
 */
withDefaults(defineProps<{ title?: string }>(), { title: 'Việt Nam' })
</script>

<template>
  <!--
    3:2, the flag's own proportion, in both the viewBox and the rendered box —
    a 20×14 box against a 30×20 drawing stretched it a few percent wide.
  -->
  <svg viewBox="0 0 30 20" class="h-4 w-6 shrink-0 rounded-[1px]" role="img">
    <title>{{ title }}</title>
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
