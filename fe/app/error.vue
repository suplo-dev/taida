<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()

const { t } = useI18n()
const isNotFound = computed(() => props.error.statusCode === 404)
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center bg-primary-700 px-4 text-center">
    <p class="text-6xl font-bold text-accent-400">{{ error.statusCode }}</p>

    <h1 class="mt-4 text-2xl font-semibold text-white">
      {{ isNotFound ? t('error.notFoundTitle') : t('error.generalTitle') }}
    </h1>

    <p class="mt-2 max-w-md text-primary-200">
      {{ isNotFound ? t('error.notFoundBody') : error.message }}
    </p>

    <button
      type="button"
      class="mt-8 rounded bg-brand-600 px-6 py-3 font-semibold text-white transition hover:bg-brand-500"
      @click="clearError({ redirect: '/' })"
    >
      {{ t('common.backToHome') }}
    </button>
  </div>
</template>
