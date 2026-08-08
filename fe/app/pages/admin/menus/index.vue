<script setup lang="ts">
import type { AdminMenuItem, Envelope, Locale } from '~/types/api'

definePageMeta({ layout: 'admin', middleware: 'auth' })

interface DraftItem {
  translations: Record<Locale, { label: string, url: string }>
  opens_in_new_tab: boolean
  children: DraftItem[]
}

const api = useApi()
const toast = useToast()
const { user } = useAuth()

const location = ref<'header' | 'footer'>('header')
const saving = ref(false)
const items = ref<DraftItem[]>([])

function blank(): DraftItem {
  return {
    translations: emptyTranslations({ label: '', url: '' }),
    opens_in_new_tab: false,
    children: [],
  }
}

function toDraft(item: AdminMenuItem): DraftItem {
  const draft = blank()

  for (const locale of SUPPORTED_LOCALES) {
    draft.translations[locale].label = item.translations?.[locale]?.label ?? ''
    draft.translations[locale].url = item.translations?.[locale]?.url ?? ''
  }

  draft.opens_in_new_tab = item.opens_in_new_tab
  draft.children = (item.children ?? []).map(toDraft)

  return draft
}

async function load() {
  const { data } = await api<Envelope<AdminMenuItem[]>>(`/admin/menus/${location.value}`)
  items.value = data.map(toDraft)
}

await load()
watch(location, load)

function move(list: DraftItem[], index: number, delta: number) {
  const target = index + delta

  if (target < 0 || target >= list.length) {
    return
  }

  const [moved] = list.splice(index, 1)
  list.splice(target, 0, moved!)
}

async function submit() {
  saving.value = true

  try {
    await api(`/admin/menus/${location.value}`, { method: 'PUT', body: { items: items.value } })
    toast.add({ title: 'Đã lưu menu', color: 'success' })
    await load()
  }
  catch (error) {
    const data = (error as { data?: { message?: string } }).data
    toast.add({ title: data?.message ?? 'Lưu menu thất bại', color: 'error' })
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <AdminPageHeader title="Menu" subtitle="Điều hướng đầu trang và chân trang, khai báo riêng cho từng ngôn ngữ.">
      <template #actions>
        <UButton :loading="saving" icon="i-lucide-save" :disabled="user?.role !== 'admin'" @click="submit">
          Lưu menu
        </UButton>
      </template>
    </AdminPageHeader>

    <p v-if="user?.role !== 'admin'" class="mb-4 rounded bg-amber-50 px-4 py-3 text-sm text-amber-800">
      Chỉ tài khoản quản trị mới sửa được menu.
    </p>

    <div class="mb-5 flex gap-1 border-b border-neutral-200">
      <button
        v-for="place in (['header', 'footer'] as const)"
        :key="place"
        type="button"
        class="-mb-px border-b-2 px-4 py-2.5 text-sm font-medium capitalize transition"
        :class="location === place ? 'border-primary-500 text-primary-700' : 'border-transparent text-neutral-500 hover:text-neutral-800'"
        @click="location = place"
      >
        {{ place === 'header' ? 'Đầu trang' : 'Chân trang' }}
      </button>
    </div>

    <div class="space-y-3">
      <div
        v-for="(item, index) in items"
        :key="index"
        class="rounded-lg border border-neutral-200 bg-white p-4"
      >
        <div class="flex items-start gap-3">
          <div class="flex flex-col gap-0.5 pt-1">
            <UButton size="xs" variant="ghost" color="neutral" icon="i-lucide-chevron-up" @click="move(items, index, -1)" />
            <UButton size="xs" variant="ghost" color="neutral" icon="i-lucide-chevron-down" @click="move(items, index, 1)" />
          </div>

          <div class="grid flex-1 gap-3 sm:grid-cols-2">
            <div v-for="locale in SUPPORTED_LOCALES" :key="locale" class="space-y-2">
              <p class="text-xs font-medium uppercase tracking-wide text-neutral-400">{{ LOCALE_LABELS[locale] }}</p>
              <UInput v-model="item.translations[locale].label" placeholder="Nhãn hiển thị" class="w-full" />
              <UInput v-model="item.translations[locale].url" placeholder="/duong-dan" class="w-full" />
            </div>
          </div>

          <div class="flex flex-col items-end gap-2">
            <UButton size="xs" variant="ghost" color="error" icon="i-lucide-trash-2" @click="items.splice(index, 1)" />
            <UButton size="xs" variant="ghost" color="neutral" icon="i-lucide-plus" @click="item.children.push(blank())">
              Mục con
            </UButton>
          </div>
        </div>

        <div v-if="item.children.length" class="mt-4 space-y-2 border-l-2 border-neutral-100 pl-6">
          <div v-for="(child, childIndex) in item.children" :key="childIndex" class="flex items-start gap-3">
            <div class="flex flex-col gap-0.5 pt-1">
              <UButton size="xs" variant="ghost" color="neutral" icon="i-lucide-chevron-up" @click="move(item.children, childIndex, -1)" />
              <UButton size="xs" variant="ghost" color="neutral" icon="i-lucide-chevron-down" @click="move(item.children, childIndex, 1)" />
            </div>
            <div class="grid flex-1 gap-3 sm:grid-cols-2">
              <div v-for="locale in SUPPORTED_LOCALES" :key="locale" class="space-y-2">
                <UInput v-model="child.translations[locale].label" :placeholder="`Nhãn (${locale})`" size="sm" class="w-full" />
                <UInput v-model="child.translations[locale].url" :placeholder="`/duong-dan (${locale})`" size="sm" class="w-full" />
              </div>
            </div>
            <UButton size="xs" variant="ghost" color="error" icon="i-lucide-trash-2" @click="item.children.splice(childIndex, 1)" />
          </div>
        </div>
      </div>

      <UButton variant="soft" icon="i-lucide-plus" @click="items.push(blank())">
        Thêm mục menu
      </UButton>
    </div>
  </div>
</template>
