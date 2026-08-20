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
    <!--
      Phần lớn ô chỉ cần một câu, nên `hint` vẫn là prop chuỗi. Slot dành cho vài
      ô mà một câu là không đủ — ảnh nền trang chủ chẳng hạn: chọn sai kích thước
      hay đặt chủ thể lệch bên là hỏng cả đầu trang, mà lúc đó không có gì báo.
    -->
    <div v-else-if="$slots.hint" class="mt-1.5 text-xs leading-relaxed text-neutral-500">
      <slot name="hint" />
    </div>
    <p v-else-if="hint" class="mt-1 text-xs text-neutral-500">{{ hint }}</p>
  </div>
</template>
