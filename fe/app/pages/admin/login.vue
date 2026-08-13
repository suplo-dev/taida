<script setup lang="ts">
definePageMeta({ layout: false })

const { login, user, fetchUser } = useAuth()
const route = useRoute()

const email = ref('')
const password = ref('')
const pending = ref(false)
const error = ref('')

// Someone arriving with a live session should not have to log in again.
onMounted(async () => {
  if (await fetchUser()) {
    await navigateTo((route.query.redirect as string) || '/admin')
  }
})

async function submit() {
  pending.value = true
  error.value = ''

  try {
    await login(email.value, password.value)
    await navigateTo((route.query.redirect as string) || '/admin')
  }
  catch (caught) {
    const data = (caught as { data?: { errors?: Record<string, string[]>, message?: string } }).data
    error.value = data?.errors?.email?.[0] ?? data?.message ?? 'Đăng nhập thất bại.'
  }
  finally {
    pending.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-primary-700 px-4">
    <form class="w-full max-w-sm rounded-lg bg-white p-8 shadow-xl" @submit.prevent="submit">
      <h1 class="text-lg font-semibold text-neutral-900">Taida CMS</h1>
      <p class="mt-1 mb-6 text-sm text-neutral-500">Đăng nhập để quản trị nội dung.</p>

      <div class="space-y-4">
        <AdminFormField label="Email" required>
          <UInput v-model="email" type="email" autocomplete="username" size="lg" class="w-full" required />
        </AdminFormField>

        <AdminFormField label="Mật khẩu" required>
          <UInput v-model="password" type="password" autocomplete="current-password" size="lg" class="w-full" required />
        </AdminFormField>
      </div>

      <p v-if="error" class="mt-4 rounded bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ error }}
      </p>

      <UButton type="submit" block size="lg" class="mt-6" :loading="pending" :disabled="!!user">
        Đăng nhập
      </UButton>
    </form>
  </div>
</template>
