<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'auth' })
</script>

<template>
  <AdminTreeContentList
    endpoint="services"
    title="Dịch vụ"
    subtitle="Các trụ cột chính và những dịch vụ con bên dưới."
  />
</template>
