<script setup lang="ts">
import type { Media } from '~/types/api'

/**
 * Hero trang chủ: một lớp media phủ kín phía sau, chữ và nút nằm trên.
 *
 * Cả ba trạng thái đều là trạng thái hợp lệ, không phải "đang thiếu":
 *
 *   không có gì  → nền trắng + vệt sáng như bản đầu tiên của site, chữ mực đậm
 *   có ảnh       → ảnh phủ kín, phủ thêm một lớp navy chuyển dần sang trong suốt
 *   có video     → video tự chạy, ảnh làm poster lúc chưa tải xong
 *
 * Khi có media thì hero lật sang "chữ sáng trên nền tối": lớp phủ kem trước đây
 * làm ảnh bạc trắng đi mới đủ đỡ chữ mực, còn navy thì càng đậm ảnh càng nổi.
 *
 * Chọn ảnh/video ở CMS → Cấu hình. Không có bước deploy nào ở giữa; site tĩnh
 * nên nó lên sau lần build kế tiếp (tự động, xem deploy/shared-hosting/README).
 */
const props = defineProps<{
  title: string
  subtitle?: string | null
  /** Ảnh nền, và cũng là poster của video khi có cả hai. */
  image?: Media | null
  /** URL video ngoài (mp4/webm). Trống thì chỉ dùng ảnh. */
  video?: string | null
}>()

const hasMedia = computed(() => Boolean(props.image || props.video))
</script>

<template>
  <section
    class="relative isolate overflow-hidden"
    :class="hasMedia ? 'bg-primary-950' : 'border-b border-primary-100 bg-white'"
  >
    <!--
      Lớp media. `aria-hidden` vì nó là nền trang trí: tiêu đề ngay bên cạnh đã
      nói đủ nội dung, còn trình đọc màn hình đọc thêm mô tả ảnh chỉ tạo nhiễu.
    -->
    <div v-if="hasMedia" class="absolute inset-0 -z-10" aria-hidden="true">
      <!--
        `motion-reduce:hidden` — người đã đặt "giảm chuyển động" trong hệ điều
        hành thì thấy ảnh tĩnh thay vì một vòng lặp video chạy mãi. Ảnh vẫn được
        render bên dưới nên không có khoảng trống.

        `muted` + `playsinline` là bắt buộc chứ không phải tuỳ chọn: thiếu
        `muted` thì mọi trình duyệt chặn autoplay, thiếu `playsinline` thì Safari
        trên iPhone mở video toàn màn hình đè lên trang.
      -->
      <video
        v-if="video"
        :key="video"
        class="absolute inset-0 size-full object-cover motion-reduce:hidden"
        :poster="image?.url"
        autoplay
        loop
        muted
        playsinline
        preload="metadata"
      >
        <source :src="video">
      </video>

      <NuxtImg
        v-if="image"
        :src="image.url"
        :alt="image.alt ?? ''"
        class="absolute inset-0 size-full object-cover"
        :class="video ? 'motion-safe:hidden' : ''"
        sizes="sm:100vw md:100vw lg:100vw xl:100vw"
        preload
        fetchpriority="high"
      />

      <!--
        Chữ trên media là chữ trắng, nên nó cần nền tối để đọc được — ảnh của
        khách có thể tối, sáng, hay lổn nhổn cả hai. Lớp phủ navy đặc ở mép
        trái (nơi có chữ) và loãng dần sang phải (nơi chỉ có ảnh) giữ được cả
        độ tương phản lẫn phần ảnh đáng nhìn.

        Navy thay cho lớp kem cũ: kem là màu sáng nên muốn đỡ được chữ mực nó
        phải đục tới mức làm ảnh bạc hẳn đi. Tối dần thì ngược lại — ảnh càng
        rõ khi lớp phủ càng đậm, nên chỗ chữ vừa dễ đọc vừa không phí ảnh.
      -->
      <div class="absolute inset-0 bg-gradient-to-r from-primary-950 via-primary-950/75 to-primary-950/25" />
      <div class="absolute inset-0 bg-gradient-to-t from-primary-950/75 to-transparent lg:hidden" />
    </div>

    <!-- Không có media: giữ nguyên vệt sáng của bản gốc. -->
    <div v-else class="absolute inset-0 -z-10 opacity-25" aria-hidden="true">
      <div class="absolute -right-24 top-1/2 size-[32rem] -translate-y-1/2 rounded-full bg-brand-300 blur-3xl" />
    </div>

    <div class="relative mx-auto max-w-8xl px-4 py-24 sm:px-6 lg:px-8 lg:py-32 xl:px-12">
      <h1
        class="max-w-3xl text-4xl font-bold leading-tight tracking-tight sm:text-5xl lg:text-6xl"
        :class="hasMedia ? 'text-white' : 'text-primary-900'"
      >
        {{ title }}
      </h1>
      <p
        v-if="subtitle"
        class="mt-6 max-w-2xl text-lg leading-relaxed"
        :class="hasMedia ? 'text-primary-100' : 'text-primary-500'"
      >
        {{ subtitle }}
      </p>

      <div class="mt-10 flex flex-wrap items-center gap-4">
        <slot name="actions" />
      </div>
    </div>
  </section>
</template>
