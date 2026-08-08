<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'auth' })
</script>

<template>
  <AdminTreeContentList
    endpoint="services"
    title="Dịch vụ"
    subtitle="Bốn trụ cột và các dịch vụ con bên dưới."
  />
</template>
