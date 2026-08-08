<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'auth' })
</script>

<template>
  <AdminTreeContentForm
    endpoint="services"
    link-endpoint="industries"
    link-field="industry_ids"
    link-label="Ngành nghề áp dụng"
    title="Dịch vụ"
  />
</template>
