<script setup lang="ts">
defineProps<{
  label: string
  /** Validation message from the API, if this field has one. */
  error?: string
  hint?: string
  required?: boolean
}>()
</script>

<template>
  <div>
    <label class="mb-1.5 block text-sm font-medium text-neutral-700">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <slot />

    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    <p v-else-if="hint" class="mt-1 text-xs text-neutral-500">{{ hint }}</p>
  </div>
</template>
