<script setup lang="ts">
import type { AdminCategory, AdminTag, Envelope, Locale } from '~/types/api'

definePageMeta({ layout: 'admin', middleware: 'auth' })

const api = useApi()
const { user } = useAuth()
const categories = useAdminResource<AdminCategory>('categories')
const tags = useAdminResource<AdminTag>('tags')

const { data, refresh } = await useAsyncData('admin:taxonomy', async () => {
  const [category, tag] = await Promise.all([
    api<Envelope<AdminCategory[]>>('/admin/categories'),
    api<Envelope<AdminTag[]>>('/admin/tags'),
  ])

  return { categories: category.data, tags: tag.data }
})

const open = ref(false)
const kind = ref<'categories' | 'tags'>('categories')
const editingId = ref<number | null>(null)
const form = reactive({
  sort_order: 0,
  translations: emptyTranslations({ name: '', slug: '', description: '' }),
})

const resource = computed(() => (kind.value === 'categories' ? categories : tags))
const errors = computed(() => resource.value.errors.value)

function nameOf(item: AdminCategory | AdminTag): string {
  return item.translations?.vi?.name ?? item.translations?.en?.name ?? `#${item.id}`
}

function edit(type: 'categories' | 'tags', item?: AdminCategory | AdminTag) {
  kind.value = type
  editingId.value = item?.id ?? null
  form.sort_order = (item as AdminCategory)?.sort_order ?? 0
  form.translations = emptyTranslations({ name: '', slug: '', description: '' })

  for (const locale of SUPPORTED_LOCALES) {
    Object.assign(form.translations[locale], item?.translations?.[locale] ?? {})
  }

  open.value = true
}

async function submit() {
  const payload = kind.value === 'categories'
    ? { sort_order: form.sort_order, translations: form.translations }
    : { translations: form.translations }

  if (await resource.value.save(editingId.value, payload)) {
    open.value = false
    await refresh()
  }
}

async function destroy(type: 'categories' | 'tags', item: AdminCategory | AdminTag) {
  const target = type === 'categories' ? categories : tags

  if (window.confirm(`Xoá "${nameOf(item)}"?`) && await target.remove(item.id)) {
    await refresh()
  }
}

function field(locale: Locale, name: string): string {
  return `translations.${locale}.${name}`
}
</script>

<template>
  <div>
    <AdminPageHeader title="Danh mục & Thẻ" subtitle="Phân loại cho bài viết." />

    <div class="grid gap-6 lg:grid-cols-2">
      <section class="rounded-lg border border-neutral-200 bg-white">
        <header class="flex items-center justify-between border-b border-neutral-200 px-5 py-3">
          <h2 class="text-sm font-semibold text-neutral-700">Danh mục</h2>
          <UButton size="xs" icon="i-lucide-plus" @click="edit('categories')">Thêm</UButton>
        </header>
        <ul class="divide-y divide-neutral-100">
          <li v-for="item in data?.categories" :key="item.id" class="flex items-center gap-3 px-5 py-3">
            <button type="button" class="flex-1 text-left text-sm text-neutral-800 hover:text-primary-600" @click="edit('categories', item)">
              {{ nameOf(item) }}
            </button>
            <span class="text-xs text-neutral-400">{{ item.posts_count ?? 0 }} bài</span>
            <UButton
              v-if="user?.role === 'admin'"
              size="xs"
              variant="ghost"
              color="error"
              icon="i-lucide-trash-2"
              @click="destroy('categories', item)"
            />
          </li>
          <li v-if="!data?.categories.length" class="px-5 py-8 text-center text-sm text-neutral-500">Chưa có danh mục.</li>
        </ul>
      </section>

      <section class="rounded-lg border border-neutral-200 bg-white">
        <header class="flex items-center justify-between border-b border-neutral-200 px-5 py-3">
          <h2 class="text-sm font-semibold text-neutral-700">Thẻ</h2>
          <UButton size="xs" icon="i-lucide-plus" @click="edit('tags')">Thêm</UButton>
        </header>
        <ul class="divide-y divide-neutral-100">
          <li v-for="item in data?.tags" :key="item.id" class="flex items-center gap-3 px-5 py-3">
            <button type="button" class="flex-1 text-left text-sm text-neutral-800 hover:text-primary-600" @click="edit('tags', item)">
              {{ nameOf(item) }}
            </button>
            <span class="text-xs text-neutral-400">{{ item.posts_count ?? 0 }} bài</span>
            <UButton
              v-if="user?.role === 'admin'"
              size="xs"
              variant="ghost"
              color="error"
              icon="i-lucide-trash-2"
              @click="destroy('tags', item)"
            />
          </li>
          <li v-if="!data?.tags.length" class="px-5 py-8 text-center text-sm text-neutral-500">Chưa có thẻ.</li>
        </ul>
      </section>
    </div>

    <UModal v-model:open="open" :title="kind === 'categories' ? 'Danh mục' : 'Thẻ'">
      <template #body>
        <form class="space-y-4" @submit.prevent="submit">
          <AdminTranslationTabs :translations="form.translations" :errors="errors" title-field="name">
            <template #default="{ locale }">
              <div class="space-y-4">
                <AdminFormField
                  label="Tên"
                  :required="locale === PRIMARY_LOCALE"
                  :error="resource.error(field(locale, 'name'))"
                >
                  <UInput v-model="form.translations[locale].name" class="w-full" />
                </AdminFormField>

                <AdminSlugField
                  v-model="form.translations[locale].slug"
                  :locale="locale"
                  :translations="form.translations"
                  :error="resource.error(field(locale, 'slug'))"
                  hint="Để trống sẽ tự sinh."
                />

                <AdminFormField v-if="kind === 'categories'" label="Mô tả">
                  <UTextarea v-model="form.translations[locale].description" :rows="2" class="w-full" />
                </AdminFormField>
              </div>
            </template>
          </AdminTranslationTabs>

          <AdminFormField v-if="kind === 'categories'" label="Thứ tự">
            <UInput v-model.number="form.sort_order" type="number" min="0" class="w-full" />
          </AdminFormField>

          <div class="flex justify-end gap-2 pt-2">
            <UButton variant="ghost" color="neutral" @click="open = false">Huỷ</UButton>
            <UButton type="submit" :loading="resource.saving.value">Lưu</UButton>
          </div>
        </form>
      </template>
    </UModal>
  </div>
</template>
