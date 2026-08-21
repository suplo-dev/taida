<script setup lang="ts">
const { t } = useI18n()
const localePath = useLocalePath()
const { data: chrome } = await useSiteData()

const year = new Date().getFullYear()

/**
 * Endpoint công khai đã bỏ những mạng bị tắt hoặc chưa có địa chỉ, nên ở đây chỉ
 * còn việc kiểm tra danh sách có rỗng không — rỗng thì cả hàng icon biến mất chứ
 * không để lại một khoảng trống giữa chân trang.
 */
const socialLinks = computed(() => Object.entries(chrome.value?.settings.social ?? {}))
</script>

<template>
  <footer class="mt-20 bg-primary-600 text-primary-200">
    <div class="mx-auto max-w-8xl px-4 py-14 sm:px-6 lg:px-8 xl:px-12">
      <div class="grid gap-10 md:grid-cols-4">
        <div class="md:col-span-2">
          <SiteLogo :logo="chrome?.settings.logo" class="text-white" />
          <p v-if="chrome?.settings.address" class="mt-4 max-w-sm text-sm leading-relaxed">
            {{ chrome.settings.address }}
          </p>

          <ul class="mt-4 space-y-1.5 text-sm">
            <li v-if="chrome?.settings.hotline" class="flex items-center gap-2">
              <UIcon name="i-lucide-phone" class="size-4 text-accent-400" />
              <a :href="`tel:${chrome.settings.hotline.replace(/\s/g, '')}`" class="hover:text-white">
                {{ chrome.settings.hotline }}
              </a>
            </li>
            <li v-if="chrome?.settings.email" class="flex items-center gap-2">
              <UIcon name="i-lucide-mail" class="size-4 text-accent-400" />
              <a :href="`mailto:${chrome.settings.email}`" class="hover:text-white">{{ chrome.settings.email }}</a>
            </li>
          </ul>

          <!--
            Mã QR đứng cạnh hotline và email vì nó cũng là một cách liên hệ:
            người đọc trên máy tính quét bằng điện thoại, người đọc trên điện
            thoại chạm để phóng to. Nhãn nằm dưới ảnh chứ không nằm trong ảnh —
            ảnh QR nào cũng chỉ là ô đen trắng, không tự nói nó là Zalo hay WeChat.
          -->
          <ul v-if="chrome?.settings.contactQr?.length" class="mt-6 flex flex-wrap gap-4">
            <li v-for="qr in chrome.settings.contactQr" :key="qr.label" class="text-center">
              <NuxtImg
                :src="qr.media.url"
                :alt="qr.media.alt ?? qr.label"
                width="96"
                height="96"
                loading="lazy"
                class="size-24 rounded bg-white object-contain p-1"
              />
              <p class="mt-1.5 text-xs">{{ qr.label }}</p>
            </li>
          </ul>
        </div>

        <nav>
          <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-white">{{ t('nav.services') }}</h2>
          <ul class="space-y-2 text-sm">
            <li><NuxtLink :to="localePath('dich-vu')" class="hover:text-white">{{ t('nav.services') }}</NuxtLink></li>
            <li><NuxtLink :to="localePath('nganh-nghe')" class="hover:text-white">{{ t('nav.industries') }}</NuxtLink></li>
            <li><NuxtLink :to="localePath('tin-tuc')" class="hover:text-white">{{ t('nav.insights') }}</NuxtLink></li>
          </ul>
        </nav>

        <nav v-if="chrome?.footer.length">
          <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-white">{{ t('nav.about') }}</h2>
          <ul class="space-y-2 text-sm">
            <li v-for="link in chrome.footer" :key="link.id">
              <NuxtLink :to="link.href" class="hover:text-white">{{ link.label }}</NuxtLink>
            </li>
          </ul>
        </nav>
      </div>

      <div class="mt-12 flex flex-col gap-4 border-t border-primary-500/40 pt-6 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs">© {{ year }} TAIDA. {{ t('footer.rights') }}</p>

        <div v-if="socialLinks.length" class="flex items-center gap-3">
          <a
            v-for="[network, url] in socialLinks"
            :key="network"
            :href="url"
            target="_blank"
            rel="noopener noreferrer"
            :aria-label="network"
            class="text-primary-300 transition hover:text-white"
          >
            <SiteSocialIcon :network="network" class="size-5" />
          </a>
        </div>
      </div>
    </div>
  </footer>
</template>
