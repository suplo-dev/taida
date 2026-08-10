<script setup lang="ts">
const { locales } = useI18n()

// The hreflang alternates and og:locale tags used to be wired up here with
// useLocaleHead(). Under `experimental.strictSeo` the module emits them itself
// — and refuses to run if we also do — which is the point: it defers them until
// the page has registered its per-locale slugs, so they no longer point at a
// slug that only exists in the language being rendered.

// Keeps the language switcher aware of what is configured.
provide('availableLocales', locales)
</script>

<template>
  <UApp>
    <NuxtRouteAnnouncer />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>
