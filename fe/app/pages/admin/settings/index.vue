<script setup lang="ts">
import type { Envelope, Locale, Media } from '~/types/api'

definePageMeta({ layout: 'admin', middleware: 'auth' })

/** Raw settings map — localised values are objects keyed by locale. */
type SettingsMap = Record<string, unknown>

const api = useApi()
const toast = useToast()
const { user } = useAuth()

const saving = ref(false)

const { data: settings } = await useAsyncData(
  'admin:settings',
  async () => (await api<Envelope<SettingsMap>>('/admin/settings')).data,
)

// The API expands the stored media id into a full record so the picker has a
// thumbnail to show; only the id goes back on save.
const logoPreview = computed(() => (settings.value?.logo as Media | null) ?? null)

const form = reactive({
  logo: (logoPreview.value?.id ?? null) as number | null,
  hotline: (settings.value?.hotline as string) ?? '',
  email: (settings.value?.email as string) ?? '',
  // Không dùng `emptyTranslations('')` được: helper đó nhân bản một *object*
  // cho mỗi locale, nên `{...''}` ra `{}` — ô nhập địa chỉ nhận một object rỗng
  // và hiện "[object Object]" khi cài đặt chưa có giá trị nào.
  address: {
    vi: '',
    en: '',
    ...(settings.value?.address as Partial<Record<Locale, string>> ?? {}),
  } as Record<Locale, string>,
  hero: {
    vi: { title: '', subtitle: '', ...((settings.value?.hero as Record<string, object>)?.vi ?? {}) },
    en: { title: '', subtitle: '', ...((settings.value?.hero as Record<string, object>)?.en ?? {}) },
  } as Record<Locale, { title: string, subtitle: string }>,
  social: {
    linkedin: '',
    facebook: '',
    youtube: '',
    ...(settings.value?.social as object ?? {}),
  } as Record<string, string>,
})

async function submit() {
  saving.value = true

  try {
    await api('/admin/settings', { method: 'PUT', body: { settings: { ...form } } })
    toast.add({ title: 'Đã lưu cấu hình', color: 'success' })
  }
  catch (error) {
    const data = (error as { data?: { message?: string } }).data
    toast.add({ title: data?.message ?? 'Lưu cấu hình thất bại', color: 'error' })
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <form @submit.prevent="submit">
    <AdminPageHeader title="Cấu hình" subtitle="Logo, thông tin liên hệ, khẩu hiệu trang chủ và mạng xã hội.">
      <template #actions>
        <UButton type="submit" :loading="saving" icon="i-lucide-save" :disabled="user?.role !== 'admin'">
          Lưu
        </UButton>
      </template>
    </AdminPageHeader>

    <div class="grid gap-6 lg:grid-cols-2">
      <section class="space-y-4 rounded-lg border border-neutral-200 bg-white p-6 lg:col-span-2">
        <h2 class="text-sm font-semibold text-neutral-700">Thương hiệu</h2>

        <AdminFormField
          label="Logo"
          hint="Hiển thị ở đầu trang và chân trang. Để trống sẽ dùng logo mặc định đi kèm mã nguồn."
        >
          <AdminMediaPicker v-model="form.logo" :preview="logoPreview" />
        </AdminFormField>
      </section>

      <section class="space-y-4 rounded-lg border border-neutral-200 bg-white p-6">
        <h2 class="text-sm font-semibold text-neutral-700">Liên hệ</h2>

        <AdminFormField label="Hotline">
          <UInput v-model="form.hotline" class="w-full" />
        </AdminFormField>

        <AdminFormField label="Email">
          <UInput v-model="form.email" type="email" class="w-full" />
        </AdminFormField>

        <AdminFormField v-for="locale in SUPPORTED_LOCALES" :key="locale" :label="`Địa chỉ (${LOCALE_LABELS[locale]})`">
          <UTextarea v-model="form.address[locale]" :rows="2" class="w-full" />
        </AdminFormField>
      </section>

      <section class="space-y-4 rounded-lg border border-neutral-200 bg-white p-6">
        <h2 class="text-sm font-semibold text-neutral-700">Mạng xã hội</h2>

        <AdminFormField v-for="network in ['linkedin', 'facebook', 'youtube']" :key="network" :label="network">
          <UInput v-model="form.social[network]" placeholder="https://" class="w-full" />
        </AdminFormField>
      </section>

      <section class="space-y-5 rounded-lg border border-neutral-200 bg-white p-6 lg:col-span-2">
        <h2 class="text-sm font-semibold text-neutral-700">Khẩu hiệu trang chủ</h2>

        <div class="grid gap-5 sm:grid-cols-2">
          <div v-for="locale in SUPPORTED_LOCALES" :key="locale" class="space-y-4">
            <p class="text-xs font-medium uppercase tracking-wide text-neutral-400">{{ LOCALE_LABELS[locale] }}</p>

            <AdminFormField label="Tiêu đề">
              <UInput v-model="form.hero[locale].title" class="w-full" />
            </AdminFormField>

            <AdminFormField label="Mô tả">
              <UTextarea v-model="form.hero[locale].subtitle" :rows="3" class="w-full" />
            </AdminFormField>
          </div>
        </div>
      </section>
    </div>
  </form>
</template>
