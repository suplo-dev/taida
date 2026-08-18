<script setup lang="ts">
const { t } = useI18n()
const localePath = useLocalePath()
const { data: chrome } = await useSiteData()

const year = new Date().getFullYear()

const socialIcons: Record<string, string> = {
  linkedin: 'i-lucide-linkedin',
  facebook: 'i-lucide-facebook',
  youtube: 'i-lucide-youtube',
  x: 'i-lucide-twitter',
}
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
              <NuxtLink :to="link.url ?? '/'" class="hover:text-white">{{ link.label }}</NuxtLink>
            </li>
          </ul>
        </nav>
      </div>

      <div class="mt-12 flex flex-col gap-4 border-t border-primary-500/40 pt-6 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs">© {{ year }} TAIDA. {{ t('footer.rights') }}</p>

        <div v-if="chrome?.settings.social" class="flex items-center gap-3">
          <a
            v-for="(url, network) in chrome.settings.social"
            :key="network"
            :href="url"
            target="_blank"
            rel="noopener noreferrer"
            :aria-label="network"
            class="text-primary-300 transition hover:text-white"
          >
            <UIcon :name="socialIcons[network] ?? 'i-lucide-link'" class="size-5" />
          </a>
        </div>
      </div>
    </div>
  </footer>
</template>
