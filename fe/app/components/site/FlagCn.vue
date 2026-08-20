<script setup lang="ts">
/**
 * The Chinese flag, drawn inline for the same reason as [SiteFlagVn]: the only
 * icon set bundled here is Lucide, which has no flags, and a raster asset for a
 * 20 px mark would cost a request and blur on retina.
 */
withDefaults(defineProps<{ title?: string }>(), { title: '中国' })
</script>

<template>
  <!--
    Same 30×20 box as the Vietnamese flag beside it in the switcher — 3:2, both
    flags' own proportion — so the two rows line up on the same mark width.
  -->
  <svg viewBox="0 0 30 20" class="h-5 w-[1.875rem] shrink-0 rounded-[1px]" role="img">
    <title>{{ title }}</title>
    <rect width="30" height="20" fill="#de2910" />
    <!--
      The canton is a fixed construction, not an eyeballed arrangement: on a
      30×20 field the large star is centred on (5,5) with a circumradius of 3,
      one point straight up, and the four small stars have a circumradius of 1
      centred on (10,2), (12,4), (12,7) and (10,9) — each rotated so that one of
      its points aims at the centre of the large one, which is what gives the
      group its fan. Every coordinate below follows from those five centres.
    -->
    <g fill="#ffde00">
      <polygon points="5,2 5.674,4.073 7.853,4.073 6.09,5.354 6.763,7.427 5,6.146 3.237,7.427 3.91,5.354 2.147,4.073 4.326,4.073" />
      <polygon points="9.143,2.514 9.62,1.966 9.246,1.343 9.914,1.628 10.391,1.08 10.328,1.803 10.996,2.088 10.288,2.251 10.224,2.975 9.851,2.352" />
      <polygon points="11.01,4.141 11.662,3.821 11.56,3.102 12.065,3.624 12.718,3.304 12.378,3.946 12.884,4.467 12.168,4.343 11.829,4.985 11.726,4.266" />
      <polygon points="11.038,6.725 11.765,6.699 11.964,6.001 12.213,6.683 12.939,6.657 12.367,7.105 12.616,7.787 12.014,7.382 11.442,7.83 11.641,7.131" />
      <polygon points="9.219,8.375 9.899,8.632 10.353,8.064 10.319,8.79 10.999,9.046 10.298,9.239 10.265,9.964 9.865,9.357 9.165,9.55 9.618,8.982" />
    </g>
  </svg>
</template>
