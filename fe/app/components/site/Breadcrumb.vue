<script setup lang="ts">
const props = defineProps<{ items: { label: string, to: string }[] }>()

useBreadcrumbSchema(() => props.items)
</script>

<template>
  <nav aria-label="Breadcrumb" class="mb-6 flex flex-wrap items-center gap-1.5 text-sm text-neutral-500">
    <template v-for="(item, index) in items" :key="item.to">
      <UIcon v-if="index > 0" name="i-lucide-chevron-right" class="size-3.5 text-neutral-300" />
      <NuxtLink
        v-if="index < items.length - 1"
        :to="item.to"
        class="hover:text-primary-600"
      >
        {{ item.label }}
      </NuxtLink>
      <span v-else class="font-medium text-neutral-700">{{ item.label }}</span>
    </template>
  </nav>
</template>
