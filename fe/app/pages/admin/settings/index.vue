<script setup lang="ts">
import type { Envelope, Locale, Media } from '~/types/api'

definePageMeta({ layout: 'admin', middleware: 'auth' })

/** Raw settings map — localised values are objects keyed by locale. */
type SettingsMap = Record<string, unknown>

/** Một mạng xã hội trong cấu hình: địa chỉ, và công tắc quyết định site có hiện nó không. */
interface SocialLink {
  url: string
  enabled: boolean
}

/** Một hàng mã QR đang sửa. `preview` chỉ phục vụ bộ chọn ảnh, không gửi lên. */
interface QrRow {
  /**
   * Khoá của `v-for`, không lưu xuống database. Dùng chỉ số mảng làm khoá thì
   * xoá một hàng ở giữa sẽ khiến Vue dùng lại đúng bộ chọn ảnh đó cho hàng phía
   * sau, mà bộ chọn giữ ảnh đang chọn trong state riêng — hàng mới hiện ảnh của
   * hàng vừa bị xoá.
   */
  key: number
  label: string
  enabled: boolean
  media: number | null
  preview: Media | null
}

/** Nhãn hiện trên form; mạng nào không có ở đây thì lấy nguyên tên khoá. */
const SOCIAL_LABELS: Record<string, string> = {
  linkedin: 'LinkedIn',
  facebook: 'Facebook',
  youtube: 'YouTube',
  tiktok: 'TikTok',
  lemon8: 'Lemon8',
}

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
  // Endpoint admin trả về đủ mọi mạng — kể cả mạng chưa ai điền — nên form dựng
  // ô nhập từ chính nó. Thêm một mạng mới ở backend là có ô ngay, không phải sửa
  // hai chỗ rồi quên một.
  social: Object.fromEntries(
    Object.entries((settings.value?.social as Record<string, SocialLink>) ?? {})
      .map(([network, link]) => [network, { url: link?.url ?? '', enabled: link?.enabled ?? false }]),
  ) as Record<string, SocialLink>,
})

/**
 * Mã QR là một danh sách nên nó nằm ngoài `form`: mỗi hàng còn phải giữ thêm
 * bản ghi media để bộ chọn có hình xem trước, thứ không được gửi lên khi lưu.
 */
let nextQrKey = 0

const qrCodes = ref<QrRow[]>(
  ((settings.value?.contactQr as Array<{ label: string, enabled: boolean, media: Media }>) ?? [])
    .map(item => ({ key: nextQrKey++, label: item.label, enabled: item.enabled, media: item.media.id, preview: item.media })),
)

function addQrCode() {
  qrCodes.value.push({ key: nextQrKey++, label: '', enabled: true, media: null, preview: null })
}

function removeQrCode(index: number) {
  qrCodes.value.splice(index, 1)
}

async function submit() {
  // Backend từ chối hàng thiếu ảnh hoặc thiếu nhãn, nhưng lỗi của trang này chỉ
  // hiện thành một dòng toast không gắn vào ô nào — chặn ở đây thì người dùng
  // biết phải sửa cái gì.
  if (qrCodes.value.some(qr => !qr.label.trim() || !qr.media)) {
    toast.add({ title: 'Mỗi mã QR cần một tên gọi và một ảnh.', color: 'error' })

    return
  }

  saving.value = true

  const contactQr = qrCodes.value.map(qr => ({ label: qr.label.trim(), enabled: qr.enabled, media: qr.media }))

  try {
    await api('/admin/settings', { method: 'PUT', body: { settings: { ...form, contactQr } } })
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
    <AdminPageHeader title="Cấu hình" subtitle="Logo, thông tin liên hệ, mã QR, ảnh nền và khẩu hiệu trang chủ, mạng xã hội.">
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
        <p class="-mt-2 text-xs text-neutral-500">Chỉ những mạng đang bật mới hiện ở chân trang. Tắt là ẩn khỏi site, địa chỉ vẫn giữ nguyên ở đây.</p>

        <AdminFormField
          v-for="(link, network) in form.social"
          :key="network"
          :label="SOCIAL_LABELS[network] ?? network"
        >
          <div class="flex items-center gap-3">
            <UInput v-model="link.url" placeholder="https://" class="flex-1" />
            <USwitch v-model="link.enabled" :aria-label="`Hiện ${SOCIAL_LABELS[network] ?? network} trên site`" />
          </div>
        </AdminFormField>
      </section>

      <section class="space-y-4 rounded-lg border border-neutral-200 bg-white p-6 lg:col-span-2">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-sm font-semibold text-neutral-700">Mã QR liên hệ</h2>
            <p class="mt-1 text-xs text-neutral-500">
              Hiện ở chân trang, cạnh hotline và email. Tên gọi nằm dưới ảnh — ảnh QR nào cũng chỉ là ô đen trắng,
              người xem không tự biết đâu là Zalo, đâu là WeChat. Tắt công tắc là ẩn khỏi site mà vẫn giữ ảnh ở đây.
            </p>
          </div>

          <UButton size="xs" variant="soft" icon="i-lucide-plus" @click="addQrCode">
            Thêm mã QR
          </UButton>
        </div>

        <p v-if="qrCodes.length === 0" class="rounded border border-dashed border-neutral-300 py-6 text-center text-sm text-neutral-500">
          Chưa có mã QR nào.
        </p>

        <div v-else class="grid gap-4 sm:grid-cols-2">
          <div v-for="(qr, index) in qrCodes" :key="qr.key" class="flex gap-4 rounded-lg border border-neutral-200 p-4">
            <AdminMediaPicker v-model="qr.media" :preview="qr.preview" />

            <div class="flex-1 space-y-3">
              <AdminFormField label="Tên gọi">
                <UInput v-model="qr.label" placeholder="Zalo, WeChat…" class="w-full" />
              </AdminFormField>

              <div class="flex items-center justify-between">
                <USwitch v-model="qr.enabled" label="Hiện trên site" />

                <UButton
                  size="xs"
                  variant="ghost"
                  color="error"
                  icon="i-lucide-trash-2"
                  :aria-label="`Xoá mã QR ${qr.label || index + 1}`"
                  @click="removeQrCode(index)"
                />
              </div>
            </div>
          </div>
        </div>
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
