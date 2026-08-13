<script setup lang="ts">
const { user, logout } = useAuth()

const nav = [
  { to: '/admin', label: 'Tổng quan', icon: 'i-lucide-layout-dashboard' },
  { to: '/admin/services', label: 'Dịch vụ', icon: 'i-lucide-wrench' },
  { to: '/admin/industries', label: 'Ngành nghề', icon: 'i-lucide-factory' },
  { to: '/admin/posts', label: 'Tin tức', icon: 'i-lucide-newspaper' },
  { to: '/admin/categories', label: 'Danh mục', icon: 'i-lucide-tags' },
  { to: '/admin/pages', label: 'Trang tĩnh', icon: 'i-lucide-file-text' },
  { to: '/admin/media', label: 'Thư viện ảnh', icon: 'i-lucide-image' },
  { to: '/admin/menus', label: 'Menu', icon: 'i-lucide-menu' },
  { to: '/admin/settings', label: 'Cấu hình', icon: 'i-lucide-settings' },
]
</script>

<template>
  <div class="flex min-h-screen bg-neutral-50">
    <aside class="w-60 shrink-0 bg-primary-600 text-white">
      <div class="px-5 py-4 text-lg font-semibold tracking-tight">Taida CMS</div>
      <nav class="flex flex-col gap-0.5 px-2 pb-4">
        <NuxtLink
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-2.5 rounded px-3 py-2 text-sm text-primary-100 hover:bg-primary-800 hover:text-white"
          active-class="bg-primary-800 text-white"
        >
          <UIcon :name="item.icon" class="size-4" />
          {{ item.label }}
        </NuxtLink>
      </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="flex h-14 items-center justify-end gap-3 border-b border-neutral-200 bg-white px-6">
        <span class="text-sm text-neutral-600">{{ user?.name }}</span>
        <UButton size="xs" variant="ghost" color="neutral" icon="i-lucide-log-out" @click="logout()">
          Đăng xuất
        </UButton>
      </header>
      <main class="min-w-0 flex-1 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
