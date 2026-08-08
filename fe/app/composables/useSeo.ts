interface SeoInput {
  title: string
  description?: string | null
  image?: string | null
  /** Set for editorial pages so an Article schema is emitted. */
  publishedAt?: string | null
  type?: 'website' | 'article'
}

/**
 * Page metadata in one call: title, description, Open Graph, and the
 * structured data Google needs. hreflang and canonical come from
 * @nuxtjs/i18n via app.vue, so they are deliberately not repeated here.
 */
export function useSeo(input: MaybeRefOrGetter<SeoInput>) {
  const { locale } = useI18n()
  const config = useRuntimeConfig()
  const route = useRoute()

  const resolved = computed(() => toValue(input))

  // Pages without their own artwork get a generated share card; ones with a
  // cover image use it directly, which always beats a rendered template.
  if (!resolved.value.image) {
    defineOgImage('OgTemplate', {
      title: resolved.value.title,
      description: resolved.value.description ?? '',
    })
  }

  useSeoMeta({
    title: () => resolved.value.title,
    description: () => resolved.value.description ?? undefined,
    ogTitle: () => resolved.value.title,
    ogDescription: () => resolved.value.description ?? undefined,
    ogImage: () => resolved.value.image ?? undefined,
    ogType: () => (resolved.value.type === 'article' ? 'article' : 'website'),
    ogLocale: () => (locale.value === 'vi' ? 'vi_VN' : 'en_US'),
    twitterCard: 'summary_large_image',
  })

  useHead({
    script: () => [{
      type: 'application/ld+json',
      innerHTML: JSON.stringify(
        resolved.value.type === 'article'
          ? {
              '@context': 'https://schema.org',
              '@type': 'Article',
              'headline': resolved.value.title,
              'description': resolved.value.description ?? undefined,
              'image': resolved.value.image ?? undefined,
              'datePublished': resolved.value.publishedAt ?? undefined,
              'inLanguage': locale.value,
            }
          : {
              '@context': 'https://schema.org',
              '@type': 'WebPage',
              'name': resolved.value.title,
              'description': resolved.value.description ?? undefined,
              'url': config.public.siteUrl + route.path,
              'inLanguage': locale.value,
            },
      ),
    }],
  })
}

/**
 * Emits a BreadcrumbList alongside the visible breadcrumb trail.
 */
export function useBreadcrumbSchema(items: MaybeRefOrGetter<{ label: string, to: string }[]>) {
  const config = useRuntimeConfig()

  useHead({
    script: () => [{
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        'itemListElement': toValue(items).map((item, index) => ({
          '@type': 'ListItem',
          'position': index + 1,
          'name': item.label,
          'item': config.public.siteUrl + item.to,
        })),
      }),
    }],
  })
}
