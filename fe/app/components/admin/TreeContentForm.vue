<script setup lang="ts">
import type { AdminIndustry, AdminService, ContentStatus, Envelope, Locale } from '~/types/api'

/**
 * Create/edit form shared by services and industries. `linkEndpoint` is the
 * other catalogue: the two are cross-linked, so editing a service picks
 * industries and vice versa.
 */
const props = defineProps<{
  endpoint: 'services' | 'industries'
  linkEndpoint: 'services' | 'industries'
  linkLabel: string
  linkField: 'industry_ids' | 'service_ids'
  title: string
}>()

/**
 * Hai catalogue dùng chung đúng một hình dạng, chỉ khác tên trường liên kết
 * (`industry_ids` với dịch vụ, `service_ids` với ngành nghề). Giao của hai kiểu
 * cho phép đọc cả hai trường — cả hai đều optional nên không kiểu nào bị nới rộng.
 */
type TreeRecord = AdminService & AdminIndustry

const route = useRoute()
const api = useApi()
const { saving, error, errors, find, save } = useAdminResource<TreeRecord>(props.endpoint)

const id = computed(() => (route.params.id === 'new' ? null : Number(route.params.id)))

const form = reactive({
  parent_id: null as number | null,
  cover_media_id: null as number | null,
  icon: '',
  sort_order: 0,
  is_featured: false,
  status: 'draft' as ContentStatus,
  published_at: null as string | null,
  linked: [] as number[],
  translations: emptyTranslations({
    name: '',
    slug: '',
    excerpt: '',
    body: '',
    meta_title: '',
    meta_description: '',
  }),
})

const cover = ref<TreeRecord['cover']>(null)

const { data: options } = await useAsyncData(`admin:${props.endpoint}:options`, async () => {
  const [own, linked] = await Promise.all([
    api<Envelope<AdminService[]>>(`/admin/${props.endpoint}`, { query: { tree: 0 } }),
    api<Envelope<AdminService[]>>(`/admin/${props.linkEndpoint}`, { query: { tree: 0 } }),
  ])

  const label = (item: AdminService) =>
    item.translations?.vi?.name ?? item.translations?.en?.name ?? `#${item.id}`

  return {
    // A record cannot be its own parent, so it is filtered out of the list.
    parents: own.data.filter(item => item.id !== id.value).map(item => ({ value: item.id, label: label(item) })),
    linkable: linked.data.map(item => ({ value: item.id, label: label(item) })),
  }
})

if (id.value) {
  const record = await find(id.value)

  Object.assign(form, {
    parent_id: record.parent_id,
    cover_media_id: record.cover_media_id,
    icon: record.icon ?? '',
    sort_order: record.sort_order,
    is_featured: record.is_featured,
    status: record.status,
    published_at: record.published_at ? record.published_at.slice(0, 16) : null,
    linked: record[props.linkField] ?? [],
  })

  cover.value = record.cover

  for (const locale of SUPPORTED_LOCALES) {
    Object.assign(form.translations[locale], record.translations?.[locale] ?? {})
  }
}

async function submit() {
  const saved = await save(id.value, {
    parent_id: form.parent_id,
    cover_media_id: form.cover_media_id,
    icon: form.icon || null,
    sort_order: form.sort_order,
    is_featured: form.is_featured,
    status: form.status,
    published_at: form.published_at,
    [props.linkField]: form.linked,
    translations: form.translations,
  })

  if (saved && !id.value) {
    await navigateTo(`/admin/${props.endpoint}/${saved.id}`)
  }
}

function field(locale: Locale, name: string): string {
  return `translations.${locale}.${name}`
}
</script>

<template>
  <form @submit.prevent="submit">
    <AdminPageHeader :title="id ? `Sửa ${title.toLowerCase()}` : `Thêm ${title.toLowerCase()}`">
      <template #actions>
        <UButton variant="ghost" color="neutral" :to="`/admin/${endpoint}`">Huỷ</UButton>
        <UButton type="submit" :loading="saving" icon="i-lucide-save">Lưu</UButton>
      </template>
    </AdminPageHeader>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
      <div class="rounded-lg border border-neutral-200 bg-white p-6">
        <AdminTranslationTabs :translations="form.translations" :errors="errors" title-field="name">
          <template #default="{ locale }">
            <div class="space-y-4">
              <AdminFormField
                label="Tên"
                :required="locale === PRIMARY_LOCALE"
                :error="error(field(locale, 'name'))"
              >
                <UInput v-model="form.translations[locale].name" size="lg" class="w-full" />
              </AdminFormField>

              <AdminFormField
                label="Đường dẫn (slug)"
                :error="error(field(locale, 'slug'))"
                hint="Để trống sẽ tự sinh từ tên."
              >
                <UInput v-model="form.translations[locale].slug" class="w-full" placeholder="tu-dong-sinh" />
              </AdminFormField>

              <AdminFormField label="Mô tả ngắn" :error="error(field(locale, 'excerpt'))">
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
            hint="Để trống nghĩa là đăng ngay."
          >
            <!-- Ô trống nghĩa là đăng ngay, và API phân biệt null với chuỗi rỗng;
                 UInput chỉ nhận `undefined` cho trạng thái rỗng nên nối tay hai chiều. -->
            <UInput
              :model-value="form.published_at ?? undefined"
              type="datetime-local"
              class="w-full"
              @update:model-value="(value: string | number) => form.published_at = String(value) || null"
            />
          </AdminFormField>

          <label class="flex items-center gap-2 text-sm text-neutral-700">
            <UCheckbox v-model="form.is_featured" />
            Hiển thị nổi bật ở trang chủ
          </label>
        </div>

        <div class="space-y-4 rounded-lg border border-neutral-200 bg-white p-5">
          <AdminFormField label="Ảnh bìa">
            <AdminMediaPicker v-model="form.cover_media_id" :preview="cover" />
          </AdminFormField>

          <AdminFormField label="Thuộc mục cha" :error="error('parent_id')">
            <USelectMenu
              :model-value="form.parent_id ?? undefined"
              class="w-full"
              value-key="value"
              :items="options?.parents ?? []"
              placeholder="Không có"
              @update:model-value="(value: number | undefined) => form.parent_id = value ?? null"
            />
          </AdminFormField>

          <AdminFormField label="Icon" hint="Tên icon Lucide, ví dụ shield-check.">
            <UInput v-model="form.icon" class="w-full" />
          </AdminFormField>

          <AdminFormField label="Thứ tự" :error="error('sort_order')">
            <UInput v-model.number="form.sort_order" type="number" min="0" class="w-full" />
          </AdminFormField>
        </div>

        <div class="rounded-lg border border-neutral-200 bg-white p-5">
          <AdminFormField :label="linkLabel" hint="Dùng để liên kết chéo giữa dịch vụ và ngành nghề.">
            <USelectMenu
              v-model="form.linked"
              multiple
              class="w-full"
              value-key="value"
              :items="options?.linkable ?? []"
              placeholder="Chưa chọn"
            />
          </AdminFormField>
        </div>
      </aside>
    </div>
  </form>
</template>
