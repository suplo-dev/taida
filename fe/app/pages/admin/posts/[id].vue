<script setup lang="ts">
import type { AdminCategory, AdminPost, AdminTag, ContentStatus, Envelope, Locale } from '~/types/api'

definePageMeta({ layout: 'admin', middleware: 'auth' })

const route = useRoute()
const api = useApi()
const { saving, error, errors, find, save } = useAdminResource<AdminPost>('posts')

const id = computed(() => (route.params.id === 'new' ? null : Number(route.params.id)))

const form = reactive({
  category_id: null as number | null,
  cover_media_id: null as number | null,
  is_featured: false,
  status: 'draft' as ContentStatus,
  published_at: null as string | null,
  tag_ids: [] as number[],
  translations: emptyTranslations({
    title: '',
    slug: '',
    excerpt: '',
    body: '',
    meta_title: '',
    meta_description: '',
  }),
})

const cover = ref<AdminPost['cover']>(null)

const { data: options } = await useAsyncData('admin:posts:options', async () => {
  const [categories, tags] = await Promise.all([
    api<Envelope<AdminCategory[]>>('/admin/categories'),
    api<Envelope<AdminTag[]>>('/admin/tags'),
  ])

  return {
    categories: categories.data.map(item => ({
      value: item.id,
      label: item.translations?.vi?.name ?? `#${item.id}`,
    })),
    tags: tags.data.map(item => ({
      value: item.id,
      label: item.translations?.vi?.name ?? `#${item.id}`,
    })),
  }
})

if (id.value) {
  const record = await find(id.value)

  Object.assign(form, {
    category_id: record.category_id,
    cover_media_id: record.cover_media_id,
    is_featured: record.is_featured,
    status: record.status,
    published_at: record.published_at ? record.published_at.slice(0, 16) : null,
    tag_ids: record.tag_ids ?? [],
  })

  cover.value = record.cover

  for (const locale of SUPPORTED_LOCALES) {
    Object.assign(form.translations[locale], record.translations?.[locale] ?? {})
  }
}

async function submit() {
  const saved = await save(id.value, { ...form })

  if (saved && !id.value) {
    await navigateTo(`/admin/posts/${saved.id}`)
  }
}

function field(locale: Locale, name: string): string {
  return `translations.${locale}.${name}`
}
</script>

<template>
  <form @submit.prevent="submit">
    <AdminPageHeader :title="id ? 'Sửa bài viết' : 'Viết bài mới'">
      <template #actions>
        <UButton variant="ghost" color="neutral" to="/admin/posts">Huỷ</UButton>
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

              <AdminFormField
                label="Đường dẫn (slug)"
                :error="error(field(locale, 'slug'))"
                hint="Để trống sẽ tự sinh từ tiêu đề."
              >
                <UInput v-model="form.translations[locale].slug" class="w-full" placeholder="tu-dong-sinh" />
              </AdminFormField>

              <AdminFormField label="Tóm tắt" :error="error(field(locale, 'excerpt'))">
                <UTextarea v-model="form.translations[locale].excerpt" :rows="3" class="w-full" />
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
          <AdminFormField label="Trạng thái" :error="error('status')">
            <USelect
              v-model="form.status"
              class="w-full"
              :items="[{ value: 'draft', label: 'Nháp' }, { value: 'published', label: 'Đã đăng' }]"
            />
          </AdminFormField>

          <AdminFormField
            label="Thời điểm đăng"
            :error="error('published_at')"
            hint="Đặt ngày tương lai để hẹn giờ."
          >
            <!-- Xem ghi chú ở AdminTreeContentForm: null (đăng ngay) khác chuỗi rỗng. -->
            <UInput
              :model-value="form.published_at ?? undefined"
              type="datetime-local"
              class="w-full"
              @update:model-value="(value: string | number) => form.published_at = String(value) || null"
            />
          </AdminFormField>

          <label class="flex items-center gap-2 text-sm text-neutral-700">
            <UCheckbox v-model="form.is_featured" />
            Bài nổi bật
          </label>
        </div>

        <div class="space-y-4 rounded-lg border border-neutral-200 bg-white p-5">
          <AdminFormField label="Ảnh bìa">
            <AdminMediaPicker v-model="form.cover_media_id" :preview="cover" />
          </AdminFormField>

          <AdminFormField label="Danh mục" :error="error('category_id')">
            <!--
              `null` là "chưa phân loại" đối với API — bỏ trống thì khoá phải đi
              kèm giá trị null, không phải biến mất khỏi JSON. USelectMenu lại chỉ
              nhận `undefined` cho trạng thái rỗng, nên hai chiều được nối tay ở
              đây thay vì `v-model`.
            -->
            <USelectMenu
              :model-value="form.category_id ?? undefined"
              class="w-full"
              value-key="value"
              :items="options?.categories ?? []"
              placeholder="Chưa phân loại"
              @update:model-value="(value: number | undefined) => form.category_id = value ?? null"
            />
          </AdminFormField>

          <AdminFormField label="Thẻ" :error="error('tag_ids')">
            <USelectMenu
              v-model="form.tag_ids"
              multiple
              class="w-full"
              value-key="value"
              :items="options?.tags ?? []"
              placeholder="Chưa gắn thẻ"
            />
          </AdminFormField>
        </div>
      </aside>
    </div>
  </form>
</template>
