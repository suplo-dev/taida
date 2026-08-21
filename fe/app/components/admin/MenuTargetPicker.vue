<script setup lang="ts">
import type { AdminMenuItem, MenuTargetOption, MenuTargetType, SiteRoute } from '~/types/api'

/**
 * Chọn nơi một mục menu trỏ tới.
 *
 * Thay cho ô nhập URL tự do từng có ở đây. Ô đó lưu một địa chỉ riêng cho từng
 * ngôn ngữ, gõ tay, không ràng buộc gì — nên quên tiền tố `/zh` hay dán nhầm
 * slug là chuyện thường, và cái giá không phải một link hỏng trên một trang mà
 * là cả lần publish thất bại, vì bản build tĩnh đi theo chính menu này. Chọn từ
 * danh sách thì địa chỉ được dựng lại lúc render, và sai không còn là một khả
 * năng.
 */
type Target = Pick<AdminMenuItem, 'target_type' | 'target_route' | 'target_id' | 'external_url'>

const props = defineProps<{
  target: Target
  options: MenuTargetOption[]
  size?: 'sm' | 'md'
}>()

/*
 * Phát ra cả cụm bốn cột chứ không sửa thẳng vào prop, và cũng không dùng
 * `defineModel`: nơi gọi đang lặp qua một mảng, mà `v-model` trên biến lặp thì
 * không hợp lệ. Bốn cột luôn đi cùng nhau — đổi loại đích mà chỉ ghi một cột là
 * để lại bản ghi tự mâu thuẫn.
 */
const emit = defineEmits<{ update: [Target] }>()

const target = computed(() => props.target)

function apply(patch: Partial<Target>) {
  emit('update', {
    target_type: 'route',
    target_route: null,
    target_id: null,
    external_url: null,
    ...patch,
  })
}

/** Nhóm hiển thị trong danh sách, theo đúng thứ tự người sửa menu hay cần. */
const GROUPS: { type: MenuTargetType, label: string }[] = [
  { type: 'route', label: 'Trang cố định' },
  { type: 'page', label: 'Trang tĩnh' },
  { type: 'service', label: 'Dịch vụ' },
  { type: 'industry', label: 'Ngành nghề' },
  { type: 'post', label: 'Tin tức' },
]

/**
 * Một lựa chọn trong danh sách. Cố tình không có trường `type`: `SelectMenuItem`
 * của Nuxt UI đã dùng tên đó cho `label`/`separator`, và loại đích đã nằm sẵn
 * trong `value` rồi.
 */
interface Choice {
  /** Khoá gộp loại và id, vì id chỉ duy nhất trong phạm vi một bảng. */
  value: string
  label: string
}

function keyOf(type: MenuTargetType, route: SiteRoute | null, id: number | null): string {
  return `${type}:${route ?? id ?? ''}`
}

/**
 * Nhóm theo loại nội dung, mỗi nhóm một tiêu đề. `USelectMenu` nhận mảng lồng
 * mảng và tự vẽ dải phân cách giữa các nhóm.
 */
const groups = computed(() => GROUPS
  .map((group) => {
    const options = props.options
      .filter(option => option.type === group.type)
      .map(option => ({
        value: keyOf(option.type, option.route, option.id),
        // Dán thẳng vào nhãn thay vì vẽ bằng slot: nhãn còn hiện ở ô đã chọn,
        // và đó mới là chỗ người sửa menu cần thấy cảnh báo.
        label: option.published ? option.label : `${option.label} (nháp)`,
      }))

    // `value: ''` cho cả dòng tiêu đề: nó không chọn được, nhưng thiếu khoá đó
    // thì kiểu chung của mảng không còn trường `value` và `value-key` gãy.
    return options.length > 0
      ? [{ value: '', label: group.label, type: 'label' as const }, ...options]
      : []
  })
  .filter(group => group.length > 0),
)

const choices = computed<Choice[]>(() =>
  groups.value.flat().filter((item): item is Choice => item.value !== ''),
)

const selected = computed<string | undefined>(() => {
  if (target.value.target_type === 'external') {
    return undefined
  }

  const key = keyOf(target.value.target_type, target.value.target_route, target.value.target_id)

  return choices.value.some(choice => choice.value === key) ? key : undefined
})

function choose(value: string | Choice) {
  const key = typeof value === 'string' ? value : value.value

  if (key === '') {
    return
  }

  const [type, rest] = key.split(':') as [MenuTargetType, string]

  apply({
    target_type: type,
    target_route: type === 'route' ? (rest as SiteRoute) : null,
    target_id: type === 'route' ? null : Number(rest),
  })
}

/** `UInput` không nhận `null`, còn cột thì nullable. */
const externalUrl = computed({
  get: () => target.value.external_url ?? '',
  set: (value: string) => apply({ target_type: 'external', external_url: value || null }),
})

const isExternal = computed({
  get: () => target.value.target_type === 'external',
  set: (value: boolean) => apply({ target_type: value ? 'external' : 'route' }),
})

/** Bản ghi đang chọn đã bị chuyển về nháp: mục này sẽ không hiện trên site. */
const draft = computed(() => props.options.find(option =>
  keyOf(option.type, option.route, option.id) === selected.value && !option.published,
))
</script>

<template>
  <div class="space-y-1.5">
    <div class="flex items-center gap-2">
      <USelectMenu
        v-if="!isExternal"
        :model-value="selected"
        :items="groups"
        value-key="value"
        :size="size ?? 'md'"
        class="w-full"
        placeholder="Chọn nơi liên kết tới"
        :search-input="{ placeholder: 'Tìm trang, dịch vụ, bài viết…' }"
        @update:model-value="choose"
      />

      <UInput
        v-else
        v-model="externalUrl"
        :size="size ?? 'md'"
        class="w-full"
        placeholder="https://…"
      />

      <UTooltip :text="isExternal ? 'Đang trỏ ra ngoài site' : 'Trỏ ra một địa chỉ ngoài site'">
        <UButton
          :size="size === 'sm' ? 'xs' : 'sm'"
          :variant="isExternal ? 'solid' : 'ghost'"
          color="neutral"
          icon="i-lucide-external-link"
          :aria-label="isExternal ? 'Dùng liên kết trong site' : 'Dùng liên kết ngoài'"
          @click="isExternal = !isExternal"
        />
      </UTooltip>
    </div>

    <p v-if="!isExternal && !selected" class="text-xs text-amber-600">
      Chưa chọn đích — mục này sẽ không hiện trên site.
    </p>
    <p v-else-if="draft" class="text-xs text-amber-600">
      "{{ draft.label }}" đang là bản nháp, nên mục này chưa hiện trên site.
    </p>
  </div>
</template>
