<script setup lang="ts">
import type { AdminPage, ContentStatus, Locale } from '~/types/api'

definePageMeta({ layout: 'admin', middleware: 'auth' })

const route = useRoute()
const { saving, error, errors, find, save } = useAdminResource<AdminPage>('pages')

const id = computed(() => (route.params.id === 'new' ? null : Number(route.params.id)))

const form = reactive({
  key: '',
  cover_media_id: null as number | null,
  status: 'draft' as ContentStatus,
  translations: emptyTranslations({
    title: '',
    slug: '',
    body: '',
    meta_title: '',
    meta_description: '',
  }),
})

const cover = ref<AdminPage['cover']>(null)

if (id.value) {
  const record = await find(id.value)

  Object.assign(form, {
    key: record.key,
    cover_media_id: record.cover_media_id,
    status: record.status,
  })

  cover.value = record.cover

  for (const locale of SUPPORTED_LOCALES) {
    Object.assign(form.translations[locale], record.translations?.[locale] ?? {})
  }
}

async function submit() {
  const saved = await save(id.value, { ...form })

  if (saved && !id.value) {
    await navigateTo(`/admin/pages/${saved.id}`)
  }
}

function field(locale: Locale, name: string): string {
  return `translations.${locale}.${name}`
}
</script>

<template>
  <form @submit.prevent="submit">
    <AdminPageHeader :title="id ? 'Sửa trang' : 'Thêm trang'">
      <template #actions>
        <UButton variant="ghost" color="neutral" to="/admin/pages">Huỷ</UButton>
        <UButton type="submit" :loading="saving" icon="i-lucide-save">Lưu</UButton>
      </template>
    </AdminPageHeader>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
      <div class="rounded-lg border border-neutral-200 bg-white p-6">
        <AdminTranslationTabs :translations="form.translations" :errors="errors" title-field="title">
          <template #default="{ locale }">
            <div class="space-y-4">
              <AdminFormField
                label="Tiêu đề"
                :required="locale === PRIMARY_LOCALE"
                :error="error(field(locale, 'title'))"
              >
                <UInput v-model="form.translations[locale].title" size="lg" class="w-full" />
              </AdminFormField>

              <AdminFormField label="Đường dẫn (slug)" :error="error(field(locale, 'slug'))" hint="Để trống sẽ tự sinh từ tiêu đề.">
                <UInput v-model="form.translations[locale].slug" class="w-full" />
              </AdminFormField>

              <AdminFormField label="Nội dung" :error="error(field(locale, 'body'))">
                <AdminRichTextEditor :key="locale" v-model="form.translations[locale].body" />
              </AdminFormField>

              <details class="rounded border border-neutral-200 p-4">
                <summary class="cursor-pointer text-sm font-medium text-neutral-700">SEO</summary>
                <div class="mt-4 space-y-4">
                  <AdminFormField label="Meta title" :error="error(field(locale, 'meta_title'))">
                    <UInput v-model="form.translations[locale].meta_title" class="w-full" />
                  </AdminFormField>
                  <AdminFormField label="Meta description" :error="error(field(locale, 'meta_description'))">
                    <UTextarea v-model="form.translations[locale].meta_description" :rows="2" class="w-full" />
                  </AdminFormField>
                </div>
              </details>
            </div>
          </template>
        </AdminTranslationTabs>
      </div>

      <aside class="space-y-6">
        <div class="space-y-4 rounded-lg border border-neutral-200 bg-white p-5">
          <AdminFormField
            label="Khoá trang"
            required
            :error="error('key')"
            hint="Định danh cố định để frontend liên kết, ví dụ about-us. Không đổi theo ngôn ngữ."
          >
            <UInput v-model="form.key" class="w-full" placeholder="about-us" />
          </AdminFormField>

          <AdminFormField label="Trạng thái" :error="error('status')">
            <USelect
              v-model="form.status"
              class="w-full"
              :items="[{ value: 'draft', label: 'Nháp' }, { value: 'published', label: 'Đã đăng' }]"
            />
          </AdminFormField>

          <AdminFormField label="Ảnh bìa">
            <AdminMediaPicker v-model="form.cover_media_id" :preview="cover" />
          </AdminFormField>
        </div>
      </aside>
    </div>
  </form>
</template>
