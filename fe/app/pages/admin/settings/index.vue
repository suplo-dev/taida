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

/** Một chuỗi rỗng cho mỗi locale — bản chuỗi của `emptyTranslations`. */
function localisedText(): Record<Locale, string> {
  return Object.fromEntries(SUPPORTED_LOCALES.map(locale => [locale, ''])) as Record<Locale, string>
}

const heroImagePreview = computed(() => (settings.value?.heroImage as Media | null) ?? null)

const form = reactive({
  logo: (logoPreview.value?.id ?? null) as number | null,
  heroImage: (heroImagePreview.value?.id ?? null) as number | null,
  heroVideo: (settings.value?.heroVideo as string) ?? '',
  hotline: (settings.value?.hotline as string) ?? '',
  email: (settings.value?.email as string) ?? '',
  // Cả hai ô này dựng theo SUPPORTED_LOCALES chứ không liệt kê tay: template bên
  // dưới lặp qua chính danh sách đó, nên thiếu một locale là `form.hero[locale]`
  // thành undefined và ô nhập của ngôn ngữ mới vỡ ngay khi mở tab.
  //
  // `emptyTranslations` chỉ nhân bản được *object*, nên nó hợp với `hero` nhưng
  // không hợp với `address` — địa chỉ là một chuỗi cho mỗi locale.
  address: {
    ...localisedText(),
    ...(settings.value?.address as Partial<Record<Locale, string>> ?? {}),
  } as Record<Locale, string>,
  // Trộn theo TỪNG locale, không trộn ở tầng ngoài: một hero đã lưu mà chỉ có
  // `title` sẽ đè nguyên object mặc định và `subtitle` thành undefined, đủ để
  // ô nhập của nó vỡ.
  hero: Object.fromEntries(SUPPORTED_LOCALES.map(locale => [locale, {
    title: '',
    subtitle: '',
    ...((settings.value?.hero as Record<string, object>)?.[locale] ?? {}),
  }])) as Record<Locale, { title: string, subtitle: string }>,
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
    <AdminPageHeader title="Cấu hình" subtitle="Logo, thông tin liên hệ, ảnh nền và khẩu hiệu trang chủ, mạng xã hội.">
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
        <h2 class="text-sm font-semibold text-neutral-700">Ảnh nền trang chủ</h2>

        <AdminFormField label="Ảnh nền">
          <AdminMediaPicker v-model="form.heroImage" :preview="heroImagePreview" />

          <template #hint>
            <p>Nằm phía sau khẩu hiệu ở đầu trang chủ. Để trống thì dùng nền màu như hiện nay.</p>

            <!--
              Sơ đồ thay cho một câu mô tả: "chủ thể nằm lệch phải" nghe thì rõ,
              nhưng đến lúc chọn ảnh không ai ước lượng được là lệch bao nhiêu.
            -->
            <div class="mt-2 flex h-16 w-full max-w-md overflow-hidden rounded border border-neutral-200">
              <div class="flex w-1/2 items-center justify-center bg-neutral-100 text-[10px] font-medium text-neutral-500">
                Chữ đè lên đây
              </div>
              <div class="flex w-1/2 items-center justify-center bg-emerald-50 text-[10px] font-medium text-emerald-700">
                Đặt chủ thể ở đây
              </div>
            </div>

            <ul class="mt-2 list-disc space-y-1 pl-4">
              <li><strong>Ảnh ngang, tối thiểu 1920×1080</strong> (đẹp nhất là 2400×1200). Ảnh nhỏ hơn bị kéo giãn và vỡ trên màn hình lớn.</li>
              <li><strong>Nửa trái bị lớp phủ sáng làm mờ</strong> để chữ đọc được — đừng đặt mặt người, logo hay chữ ở đó.</li>
              <li><strong>Ảnh bị cắt bớt chiều cao</strong> trên màn hình hẹp, nên tránh chi tiết quan trọng sát mép trên và mép dưới.</li>
              <li>Ảnh <strong>sáng và ít chi tiết vụn</strong> hợp hơn ảnh tối, nhiều chữ.</li>
              <li>Tải lên bản gốc chất lượng tốt (tối đa 8 MB, JPG/PNG/WebP) — hệ thống tự nén và tự tạo bảy kích cỡ cho từng loại màn hình.</li>
            </ul>
          </template>
        </AdminFormField>

        <AdminFormField label="Video nền">
          <UInput v-model="form.heroVideo" placeholder="https://..." class="w-full" />

          <template #hint>
            <p>Không bắt buộc. Có địa chỉ hợp lệ thì video thay chỗ ảnh, ảnh ở trên thành hình chờ.</p>

            <ul class="mt-2 list-disc space-y-1 pl-4">
              <li><strong>Vẫn phải chọn ảnh nền ở trên.</strong> Nó là hình hiển thị trong lúc video tải, và là thứ thay thế cho người bật "giảm chuyển động" trong máy của họ.</li>
              <li><strong>Địa chỉ https</strong> tới một file <strong>.mp4 (H.264)</strong>. Địa chỉ http bị trình duyệt chặn và hero sẽ mất nền mà không báo gì.</li>
              <li><strong>Đặt file ở CDN</strong> hoặc nơi lưu trữ khác — không tải vào thư viện ảnh (giới hạn 8 MB, và hosting này không hợp để phát video).</li>
              <li><strong>Video luôn bị tắt tiếng</strong> và chạy lặp; trình duyệt chặn tự phát nếu có tiếng. Nên chọn đoạn ngắn 10–20 giây, lặp lại mượt.</li>
              <li>Giữ dung lượng dưới ~10 MB: mỗi người mở trang chủ đều tải nó.</li>
            </ul>
          </template>
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
