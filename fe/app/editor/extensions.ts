import type { Editor, JSONContent } from '@tiptap/vue-3'
import Image from '@tiptap/extension-image'
import { mergeAttributes, Node } from '@tiptap/vue-3'

/**
 * Các khối bố cục của trình soạn bài.
 *
 * Mọi khối đều ra HTML thường với thuộc tính `data-*`, không có class Tailwind:
 * bài được lưu thành HTML trong CSDL và hiển thị bằng `v-html`, nên cách khối
 * trông ra sao nằm hết trong `.prose` ở main.css — dùng chung cho cả editor lẫn
 * trang ngoài site. Đổi giao diện sau này chỉ cần sửa CSS, không phải sửa lại
 * từng bài đã viết.
 */

/** Tỉ lệ các cột: `1-2` là cột trái hẹp, cột phải rộng gấp đôi. */
export type ColumnsLayout = '1-1' | '1-2' | '2-1' | '1-1-1'

export const Column = Node.create({
  name: 'column',
  content: 'block+',
  isolating: true,

  parseHTML() {
    return [{ tag: 'div[data-type="column"]' }]
  },

  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-type': 'column' }), 0]
  },
})

export const Columns = Node.create({
  name: 'columns',
  group: 'block',
  content: 'column{2,3}',
  defining: true,
  isolating: true,

  addAttributes() {
    return {
      layout: {
        default: '1-1',
        parseHTML: element => element.getAttribute('data-layout') ?? '1-1',
        renderHTML: attributes => ({ 'data-layout': attributes.layout }),
      },
      /** Đóng cả khối trong một khung viền, như thẻ giới thiệu dịch vụ. */
      boxed: {
        default: false,
        parseHTML: element => element.hasAttribute('data-boxed'),
        renderHTML: attributes => (attributes.boxed ? { 'data-boxed': '' } : {}),
      },
    }
  },

  parseHTML() {
    return [{ tag: 'div[data-type="columns"]' }]
  },

  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-type': 'columns' }), 0]
  },
})

export type ImageAlign = 'full' | 'center' | 'left' | 'right'

/**
 * Ảnh trong bài. `align`: `full` rộng hết khung (hoặc hết cột nếu nằm trong
 * cột), `center` giữ kích thước thật và căn giữa, `left`/`right` nằm một bên
 * để chữ chảy quanh.
 */
export const ArticleImage = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      align: {
        default: 'full',
        parseHTML: element => element.getAttribute('data-align') ?? 'full',
        renderHTML: attributes => ({ 'data-align': attributes.align }),
      },
      // Ảnh trong bài hầu như luôn nằm dưới màn hình đầu tiên.
      loading: {
        default: 'lazy',
        parseHTML: () => 'lazy',
        renderHTML: () => ({ loading: 'lazy' }),
      },
    }
  },
})

export type ButtonVariant = 'primary' | 'accent' | 'outline'

export interface ButtonAttrs {
  href: string
  label: string
  variant: ButtonVariant
  newTab: boolean
}

/**
 * Nút kêu gọi hành động ("Liên hệ ngay", "Quét mã"…). Là một node nguyên khối
 * nằm trong dòng chữ, nên đặt nhiều nút cạnh nhau trong cùng một đoạn là chúng
 * xếp thành hàng.
 */
export const CtaButton = Node.create({
  name: 'ctaButton',
  group: 'inline',
  inline: true,
  atom: true,
  selectable: true,

  addAttributes() {
    return {
      href: { default: '' },
      label: { default: '' },
      variant: { default: 'primary' },
      newTab: { default: false },
    }
  },

  parseHTML() {
    return [{
      tag: 'a[data-type="button"]',
      // Cao hơn mark Link, vốn cũng bắt mọi thẻ `a[href]`.
      priority: 1000,
      getAttrs: element => ({
        href: element.getAttribute('href') ?? '',
        label: element.textContent ?? '',
        variant: element.getAttribute('data-variant') ?? 'primary',
        newTab: element.getAttribute('target') === '_blank',
      }),
    }]
  },

  renderHTML({ node }) {
    const attrs = node.attrs as ButtonAttrs

    return ['a', {
      'data-type': 'button',
      'data-variant': attrs.variant,
      'href': attrs.href,
      ...(attrs.newTab ? { target: '_blank', rel: 'noopener noreferrer' } : {}),
    }, attrs.label]
  },
})

/** Cột rỗng, đủ để con trỏ đặt vào được. */
const emptyColumn = (): JSONContent => ({ type: 'column', content: [{ type: 'paragraph' }] })

/**
 * Chèn một khối cột. `filled` đặt sẵn nội dung vào một cột — dùng cho các mẫu
 * "ảnh một bên, chữ một bên", nơi ảnh được chọn trước rồi mới dựng khối.
 */
export function insertColumns(editor: Editor, layout: ColumnsLayout, filled?: { index: number, content: JSONContent[] }) {
  const count = layout === '1-1-1' ? 3 : 2
  const columns = Array.from({ length: count }, emptyColumn)

  if (filled) {
    columns[filled.index] = { type: 'column', content: filled.content }
  }

  // Thêm một đoạn trống phía sau, không thì khối cột ở cuối bài là chỗ con trỏ
  // không thoát ra được để viết tiếp.
  const content = [
    { type: 'columns', attrs: { layout }, content: columns },
    { type: 'paragraph' },
  ]

  // Không lồng cột trong cột: một lưới ba cột nằm trong cột 1/3 thì chữ chỉ còn
  // vài từ một dòng. Đang đứng trong khối cột thì chèn ngay sau khối đó.
  const parent = currentColumns(editor)
  const { $from } = editor.state.selection
  // Khối mới không thể bắt đầu trước điểm này: hoặc sau khối cột cha, hoặc tại
  // khối cấp cao nhất đang chứa con trỏ (có thể thay chỗ nó nếu nó rỗng).
  const start = parent ? parent.pos + parent.node.nodeSize : ($from.depth > 0 ? $from.before(1) : $from.pos)

  if (parent) {
    editor.chain().focus().insertContentAt(start, content).run()
  }
  else {
    editor.chain().focus().insertContent(content).run()
  }

  // Đưa con trỏ vào cột trống đầu tiên của khối vừa chèn.
  let target: number | null = null

  editor.state.doc.descendants((node, pos) => {
    if (target !== null) {
      return false
    }

    if (node.type.name !== 'columns' || pos < start) {
      return true
    }

    let offset = pos + 1

    node.forEach((column, _offset, index) => {
      if (target === null && index !== filled?.index) {
        // +1 vào trong cột, +1 vào trong đoạn văn đầu tiên của cột.
        target = offset + 2
      }
      offset += column.nodeSize
    })

    return false
  })

  if (target !== null) {
    editor.chain().setTextSelection(target).focus().run()
  }
}

/** Khối cột đang chứa con trỏ, hoặc `null`. */
function currentColumns(editor: Editor) {
  const { $from } = editor.state.selection

  for (let depth = $from.depth; depth > 0; depth--) {
    const node = $from.node(depth)

    if (node.type.name === 'columns') {
      return { node, pos: $from.before(depth) }
    }
  }

  return null
}

/** Hai cột có thể đổi tỉ lệ qua lại; ba cột thì không, vì số cột phải giữ nguyên. */
export function setColumnsLayout(editor: Editor, layout: Exclude<ColumnsLayout, '1-1-1'>) {
  editor.chain().focus().updateAttributes('columns', { layout }).run()
}

export function toggleColumnsBox(editor: Editor) {
  const boxed = !!currentColumns(editor)?.node.attrs.boxed
  editor.chain().focus().updateAttributes('columns', { boxed: !boxed }).run()
}

/** Bỏ khung cột, giữ lại nội dung: các cột được xếp lần lượt từ trên xuống. */
export function unsetColumns(editor: Editor) {
  const found = currentColumns(editor)

  if (!found) {
    return
  }

  const blocks: ReturnType<typeof found.node.child>[] = []
  found.node.forEach(column => column.forEach(block => blocks.push(block)))

  editor.view.dispatch(editor.state.tr.replaceWith(found.pos, found.pos + found.node.nodeSize, blocks))
  editor.commands.focus()
}

export function currentColumnsLayout(editor: Editor): ColumnsLayout | null {
  return (currentColumns(editor)?.node.attrs.layout as ColumnsLayout | undefined) ?? null
}

export function isColumnsBoxed(editor: Editor): boolean {
  return !!currentColumns(editor)?.node.attrs.boxed
}
