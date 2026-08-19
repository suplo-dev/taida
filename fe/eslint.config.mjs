// Preset do @nuxt/eslint sinh ra theo đúng bộ module trong nuxt.config.ts (Vue,
// TypeScript, thứ tự import, quy ước của Nuxt). File này chỉ nối thêm phần riêng
// của dự án.
import withNuxt from './.nuxt/eslint.config.mjs'

export default withNuxt(
  {
    ignores: ['dist/**', '.output/**', '.nuxt/**', 'fonts-src/**'],
  },
)
