<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'auth' })
</script>

<template>
  <AdminTreeContentList
    endpoint="industries"
    title="Ngành nghề"
    subtitle="Các nhóm ngành và ngành con."
  />
</template>
