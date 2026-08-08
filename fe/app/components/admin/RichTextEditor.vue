<script setup lang="ts">
import StarterKit from '@tiptap/starter-kit'
import { Editor, EditorContent } from '@tiptap/vue-3'

const model = defineModel<string | null>({ default: '' })

const editor = shallowRef<Editor>()

const tools = [
  { icon: 'i-lucide-bold', action: 'toggleBold', name: 'bold', label: 'Đậm' },
  { icon: 'i-lucide-italic', action: 'toggleItalic', name: 'italic', label: 'Nghiêng' },
  { icon: 'i-lucide-heading-2', action: 'toggleHeading', args: { level: 2 }, name: 'heading', label: 'Tiêu đề 2' },
  { icon: 'i-lucide-heading-3', action: 'toggleHeading', args: { level: 3 }, name: 'heading', label: 'Tiêu đề 3' },
  { icon: 'i-lucide-list', action: 'toggleBulletList', name: 'bulletList', label: 'Danh sách' },
  { icon: 'i-lucide-list-ordered', action: 'toggleOrderedList', name: 'orderedList', label: 'Danh sách số' },
  { icon: 'i-lucide-quote', action: 'toggleBlockquote', name: 'blockquote', label: 'Trích dẫn' },
] as const

onMounted(() => {
  editor.value = new Editor({
    content: model.value ?? '',
    // StarterKit already bundles Link; registering it again duplicates the mark.
    extensions: [StarterKit.configure({ link: { openOnClick: false } })],
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
</script>

<template>
  <div class="overflow-hidden rounded border border-neutral-300 focus-within:border-primary-500">
    <div class="flex flex-wrap items-center gap-0.5 border-b border-neutral-200 bg-neutral-50 px-1.5 py-1.5">
      <button
        v-for="tool in tools"
        :key="tool.icon"
        type="button"
        :title="tool.label"
        class="rounded p-1.5 text-neutral-600 hover:bg-neutral-200"
        :class="editor?.isActive(tool.name, 'args' in tool ? tool.args : undefined) ? 'bg-neutral-200 text-primary-700' : ''"
        @click="run(tool)"
      >
        <UIcon :name="tool.icon" class="size-4" />
      </button>
      <button
        type="button"
        title="Liên kết"
        class="rounded p-1.5 text-neutral-600 hover:bg-neutral-200"
        :class="editor?.isActive('link') ? 'bg-neutral-200 text-primary-700' : ''"
        @click="setLink"
      >
        <UIcon name="i-lucide-link" class="size-4" />
      </button>
    </div>

    <EditorContent :editor="editor" />
  </div>
</template>
