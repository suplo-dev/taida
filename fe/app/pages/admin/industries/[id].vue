<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'auth' })
</script>

<template>
  <AdminTreeContentForm
    endpoint="industries"
    link-endpoint="services"
    link-field="service_ids"
    link-label="Dịch vụ cung cấp"
    title="Ngành nghề"
  />
</template>
