<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui'
import type { ButtonAttrs, ButtonVariant, ColumnsLayout, ImageAlign } from '~/editor/extensions'
import type { Media } from '~/types/api'
import TextAlign from '@tiptap/extension-text-align'
import { Color, TextStyle } from '@tiptap/extension-text-style'
import StarterKit from '@tiptap/starter-kit'
import { Editor, EditorContent } from '@tiptap/vue-3'
import {
  ArticleImage,
  Column,
  Columns,
  CtaButton,
  currentColumnsLayout,
  insertColumns,
  isColumnsBoxed,
  setColumnsLayout,
  toggleColumnsBox,
  unsetColumns,
} from '~/editor/extensions'

const model = defineModel<string | null>({ default: '' })

const editor = shallowRef<Editor>()

const tools = [
  { icon: 'i-lucide-bold', action: 'toggleBold', name: 'bold', label: 'Đậm' },
  { icon: 'i-lucide-italic', action: 'toggleItalic', name: 'italic', label: 'Nghiêng' },
  { icon: 'i-lucide-underline', action: 'toggleUnderline', name: 'underline', label: 'Gạch chân' },
  { icon: 'i-lucide-heading-2', action: 'toggleHeading', args: { level: 2 }, name: 'heading', label: 'Tiêu đề 2' },
  { icon: 'i-lucide-heading-3', action: 'toggleHeading', args: { level: 3 }, name: 'heading', label: 'Tiêu đề 3' },
  { icon: 'i-lucide-list', action: 'toggleBulletList', name: 'bulletList', label: 'Danh sách' },
  { icon: 'i-lucide-list-ordered', action: 'toggleOrderedList', name: 'orderedList', label: 'Danh sách số' },
  { icon: 'i-lucide-quote', action: 'toggleBlockquote', name: 'blockquote', label: 'Trích dẫn' },
] as const

const aligns = [
  { value: 'left', icon: 'i-lucide-align-left', label: 'Căn trái' },
  { value: 'center', icon: 'i-lucide-align-center', label: 'Căn giữa' },
  { value: 'right', icon: 'i-lucide-align-right', label: 'Căn phải' },
  { value: 'justify', icon: 'i-lucide-align-justify', label: 'Căn đều' },
] as const

/**
 * Màu chữ giới hạn trong bảng màu của site thay vì một bảng chọn tự do: bài nào
 * cũng phải trông như cùng một trang web.
 */
const colors = [
  { value: '#0077bd', label: 'Xanh thương hiệu' },
  { value: '#16223a', label: 'Xanh đậm' },
  { value: '#855f1a', label: 'Vàng đồng' },
  { value: '#dc2626', label: 'Đỏ' },
] as const

onMounted(() => {
  editor.value = new Editor({
    content: model.value ?? '',
    extensions: [
      // StarterKit already bundles Link; registering it again duplicates the mark.
      StarterKit.configure({ link: { openOnClick: false } }),
      TextStyle,
      Color,
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
      ArticleImage,
      Columns,
      Column,
      CtaButton,
    ],
    editorProps: {
      attributes: {
        class: 'prose prose-sm max-w-none min-h-56 px-3 py-2.5 focus:outline-none',
      },
    },
    onUpdate: ({ editor: instance }) => {
      const html = instance.getHTML()
      model.value = html === '<p></p>' ? '' : html
    },
  })
})

onBeforeUnmount(() => editor.value?.destroy())

// The editor owns its content once mounted; only external resets (switching
// locale tab or loading a record) are pushed back in.
watch(model, (value) => {
  if (editor.value && (value ?? '') !== editor.value.getHTML()) {
    editor.value.commands.setContent(value ?? '', { emitUpdate: false })
  }
})

function run(tool: (typeof tools)[number]) {
  const chain = editor.value?.chain().focus() as Record<string, (args?: unknown) => { run: () => void }> | undefined
  chain?.[tool.action]?.('args' in tool ? tool.args : undefined).run()
}

function setLink() {
  const previous = editor.value?.getAttributes('link').href as string | undefined
  const url = window.prompt('Địa chỉ liên kết', previous ?? 'https://')

  if (url === null) {
    return
  }

  if (url === '') {
    editor.value?.chain().focus().unsetLink().run()
    return
  }

  editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

function setColor(value: string | null) {
  const chain = editor.value?.chain().focus()
  ;(value ? chain?.setColor(value) : chain?.unsetColor())?.run()
}

/* ------------------------------------------------------------------ Ảnh -- */

const libraryOpen = ref(false)

/**
 * Việc cần làm khi thư viện trả về một ảnh: chèn ảnh đơn, hay dựng khối cột
 * với ảnh nằm ở cột trái/phải.
 */
const pendingImage = ref<'single' | 'left' | 'right'>('single')

function pickImage(mode: 'single' | 'left' | 'right') {
  pendingImage.value = mode
  libraryOpen.value = true
}

function insertImage(media: Media) {
  const instance = editor.value

  if (!instance) {
    return
  }

  // Mặc định `center` — giữ kích thước thật, không bao giờ phóng ảnh nhỏ ra.
  const image = {
    type: 'image',
    attrs: {
      src: media.url,
      alt: media.alt ?? '',
      width: media.width,
      height: media.height,
      align: pendingImage.value === 'single' ? 'center' : 'full',
    },
  }

  if (pendingImage.value === 'left') {
    insertColumns(instance, '1-2', { index: 0, content: [image] })
  }
  else if (pendingImage.value === 'right') {
    insertColumns(instance, '2-1', { index: 1, content: [image] })
  }
  else {
    instance.chain().focus().insertContent(image).run()
  }
}

const imageAligns: { value: ImageAlign, icon: string, label: string }[] = [
  { value: 'center', icon: 'i-lucide-image', label: 'Kích thước gốc, căn giữa' },
  { value: 'full', icon: 'i-lucide-maximize-2', label: 'Rộng hết khung' },
  { value: 'left', icon: 'i-lucide-panel-left', label: 'Bên trái, chữ chảy quanh' },
  { value: 'right', icon: 'i-lucide-panel-right', label: 'Bên phải, chữ chảy quanh' },
]

function setImageAlign(align: ImageAlign) {
  editor.value?.chain().focus().updateAttributes('image', { align }).run()
}

/* --------------------------------------------------------------- Bố cục -- */

const layoutItems: DropdownMenuItem[] = [
  { label: 'Ảnh trái – chữ phải', icon: 'i-lucide-panel-left', onSelect: () => pickImage('left') },
  { label: 'Chữ trái – ảnh phải', icon: 'i-lucide-panel-right', onSelect: () => pickImage('right') },
  { label: '2 cột bằng nhau', icon: 'i-lucide-columns-2', onSelect: () => editor.value && insertColumns(editor.value, '1-1') },
  { label: '3 cột', icon: 'i-lucide-columns-3', onSelect: () => editor.value && insertColumns(editor.value, '1-1-1') },
]

const columnLayouts: { value: Exclude<ColumnsLayout, '1-1-1'>, label: string }[] = [
  { value: '1-1', label: '1 : 1' },
  { value: '1-2', label: '1 : 2' },
  { value: '2-1', label: '2 : 1' },
]

const layout = computed(() => (editor.value ? currentColumnsLayout(editor.value) : null))
const boxed = computed(() => (editor.value ? isColumnsBoxed(editor.value) : false))

/* ------------------------------------------------------------- Nút bấm -- */

const buttonOpen = ref(false)
const buttonForm = reactive<ButtonAttrs>({ href: '', label: '', variant: 'primary', newTab: false })
/** Đang sửa nút đã có (đang được chọn) hay tạo nút mới. */
const editingButton = ref(false)

const variants: { value: ButtonVariant, label: string }[] = [
  { value: 'primary', label: 'Xanh' },
  { value: 'accent', label: 'Vàng đồng' },
  { value: 'outline', label: 'Viền' },
]

function openButton() {
  editingButton.value = !!editor.value?.isActive('ctaButton')
  Object.assign(buttonForm, editingButton.value
    ? editor.value!.getAttributes('ctaButton')
    : { href: '', label: '', variant: 'primary', newTab: false })
  buttonOpen.value = true
}

function saveButton() {
  if (!buttonForm.label.trim() || !buttonForm.href.trim()) {
    return
  }

  const attrs = { ...buttonForm, label: buttonForm.label.trim(), href: buttonForm.href.trim() }

  if (editingButton.value) {
    editor.value?.chain().focus().updateAttributes('ctaButton', attrs).run()
  }
  else {
    // Thêm khoảng trắng sau nút để nút kế tiếp không dính vào.
    editor.value?.chain().focus().insertContent([{ type: 'ctaButton', attrs }, { type: 'text', text: ' ' }]).run()
  }

  buttonOpen.value = false
}

function removeSelection() {
  editor.value?.chain().focus().deleteSelection().run()
}
</script>

<template>
  <div class="overflow-hidden rounded border border-neutral-300 focus-within:border-primary-500">
    <div class="sticky top-0 z-10 border-b border-neutral-200 bg-neutral-50">
      <div class="flex flex-wrap items-center gap-0.5 px-1.5 py-1.5">
        <button
          v-for="tool in tools"
          :key="tool.label"
          type="button"
          :title="tool.label"
          class="rounded p-1.5 text-neutral-600 hover:bg-neutral-200"
          :class="editor?.isActive(tool.name, 'args' in tool ? tool.args : undefined) ? 'bg-neutral-200 text-primary-700' : ''"
          @click="run(tool)"
        >
          <UIcon :name="tool.icon" class="size-4" />
        </button>

        <span class="mx-1 h-5 w-px bg-neutral-300" />

        <button
          v-for="align in aligns"
          :key="align.value"
          type="button"
          :title="align.label"
          class="rounded p-1.5 text-neutral-600 hover:bg-neutral-200"
          :class="editor?.isActive({ textAlign: align.value }) ? 'bg-neutral-200 text-primary-700' : ''"
          @click="editor?.chain().focus().setTextAlign(align.value).run()"
        >
          <UIcon :name="align.icon" class="size-4" />
        </button>

        <UPopover>
          <button type="button" title="Màu chữ" class="rounded p-1.5 text-neutral-600 hover:bg-neutral-200">
            <UIcon name="i-lucide-baseline" class="size-4" />
          </button>

          <template #content>
            <div class="flex items-center gap-1.5 p-2">
              <button
                v-for="color in colors"
                :key="color.value"
                type="button"
                :title="color.label"
                class="size-6 rounded-full ring-offset-1 hover:ring-2 hover:ring-neutral-300"
                :style="{ backgroundColor: color.value }"
                @click="setColor(color.value)"
              />
              <button
                type="button"
                title="Màu mặc định"
                class="flex size-6 items-center justify-center rounded-full border border-neutral-300 text-neutral-500 hover:bg-neutral-100"
                @click="setColor(null)"
              >
                <UIcon name="i-lucide-eraser" class="size-3.5" />
              </button>
            </div>
          </template>
        </UPopover>

        <button
          type="button"
          title="Liên kết"
          class="rounded p-1.5 text-neutral-600 hover:bg-neutral-200"
          :class="editor?.isActive('link') ? 'bg-neutral-200 text-primary-700' : ''"
          @click="setLink"
        >
          <UIcon name="i-lucide-link" class="size-4" />
        </button>

        <button
          type="button"
          title="Đường kẻ ngang"
          class="rounded p-1.5 text-neutral-600 hover:bg-neutral-200"
          @click="editor?.chain().focus().setHorizontalRule().run()"
        >
          <UIcon name="i-lucide-minus" class="size-4" />
        </button>

        <span class="mx-1 h-5 w-px bg-neutral-300" />

        <UButton size="xs" variant="ghost" color="neutral" icon="i-lucide-image-plus" @click="pickImage('single')">
          Ảnh
        </UButton>
        <UDropdownMenu :items="layoutItems">
          <UButton size="xs" variant="ghost" color="neutral" icon="i-lucide-layout-panel-left" trailing-icon="i-lucide-chevron-down">
            Bố cục
          </UButton>
        </UDropdownMenu>
        <UButton size="xs" variant="ghost" color="neutral" icon="i-lucide-mouse-pointer-click" @click="openButton">
          Nút bấm
        </UButton>
      </div>

      <!-- Thanh phụ theo ngữ cảnh: chỉ hiện khi đang chọn ảnh, nút, hoặc đứng trong khối cột. -->
      <div
        v-if="editor?.isActive('image')"
        class="flex flex-wrap items-center gap-1 border-t border-neutral-200 px-2 py-1.5 text-xs text-neutral-600"
      >
        <span class="mr-1 font-medium">Ảnh:</span>
        <UButton
          v-for="option in imageAligns"
          :key="option.value"
          size="xs"
          :variant="editor.isActive('image', { align: option.value }) ? 'soft' : 'ghost'"
          color="neutral"
          :icon="option.icon"
          @click="setImageAlign(option.value)"
        >
          {{ option.label }}
        </UButton>
        <UButton size="xs" variant="ghost" color="error" icon="i-lucide-trash-2" @click="removeSelection">
          Xoá ảnh
        </UButton>
      </div>

      <div
        v-else-if="editor?.isActive('ctaButton')"
        class="flex flex-wrap items-center gap-1 border-t border-neutral-200 px-2 py-1.5 text-xs text-neutral-600"
      >
        <span class="mr-1 font-medium">Nút:</span>
        <UButton size="xs" variant="ghost" color="neutral" icon="i-lucide-pencil" @click="openButton">
          Sửa nút
        </UButton>
        <UButton size="xs" variant="ghost" color="error" icon="i-lucide-trash-2" @click="removeSelection">
          Xoá nút
        </UButton>
      </div>

      <div
        v-if="layout"
        class="flex flex-wrap items-center gap-1 border-t border-neutral-200 px-2 py-1.5 text-xs text-neutral-600"
      >
        <span class="mr-1 font-medium">Khối cột:</span>
        <template v-if="layout !== '1-1-1'">
          <UButton
            v-for="option in columnLayouts"
            :key="option.value"
            size="xs"
            :variant="layout === option.value ? 'soft' : 'ghost'"
            color="neutral"
            @click="setColumnsLayout(editor!, option.value)"
          >
            {{ option.label }}
          </UButton>
        </template>
        <UButton
          size="xs"
          :variant="boxed ? 'soft' : 'ghost'"
          color="neutral"
          icon="i-lucide-square"
          @click="toggleColumnsBox(editor!)"
        >
          Khung viền
        </UButton>
        <UButton size="xs" variant="ghost" color="error" icon="i-lucide-ungroup" @click="unsetColumns(editor!)">
          Bỏ chia cột
        </UButton>
      </div>
    </div>

    <EditorContent :editor="editor" />

    <AdminMediaLibrary v-model:open="libraryOpen" @select="insertImage" />

    <UModal v-model:open="buttonOpen" :title="editingButton ? 'Sửa nút bấm' : 'Thêm nút bấm'">
      <template #body>
        <form class="space-y-4" @submit.prevent="saveButton">
          <UFormField label="Chữ trên nút" required>
            <UInput v-model="buttonForm.label" placeholder="Liên hệ ngay" class="w-full" autofocus />
          </UFormField>
          <UFormField label="Liên kết" required help="Trang trong site (/lien-he), địa chỉ web, tel:0901… hoặc mailto:…">
            <UInput v-model="buttonForm.href" placeholder="/lien-he" class="w-full" />
          </UFormField>
          <UFormField label="Kiểu nút">
            <div class="flex gap-2">
              <UButton
                v-for="variant in variants"
                :key="variant.value"
                size="sm"
                :variant="buttonForm.variant === variant.value ? 'solid' : 'outline'"
                color="neutral"
                @click="buttonForm.variant = variant.value"
              >
                {{ variant.label }}
              </UButton>
            </div>
          </UFormField>
          <UCheckbox v-model="buttonForm.newTab" label="Mở trong tab mới" />

          <div class="flex justify-end gap-2">
            <UButton variant="ghost" color="neutral" @click="buttonOpen = false">
              Huỷ
            </UButton>
            <UButton type="submit" :disabled="!buttonForm.label.trim() || !buttonForm.href.trim()">
              {{ editingButton ? 'Lưu' : 'Thêm nút' }}
            </UButton>
          </div>
        </form>
      </template>
    </UModal>
  </div>
</template>
