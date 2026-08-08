import type { AdminUser, Envelope } from '~/types/api'

/**
 * Sanctum cookie-based session for the admin panel.
 *
 * `/admin/**` renders client-side only, so every call here runs in the
 * browser and the session cookie travels with it automatically.
 */
export function useAuth() {
  const api = useApi()
  const config = useRuntimeConfig()
  const user = useState<AdminUser | null>('auth:user', () => null)

  /** Laravel needs the XSRF cookie in place before any stateful POST. */
  async function primeCsrf(): Promise<void> {
    await $fetch('/sanctum/csrf-cookie', {
      baseURL: config.public.apiBase,
      credentials: 'include',
    })
  }

  async function login(email: string, password: string): Promise<void> {
    await primeCsrf()

    // The API wraps resources in a `data` envelope; store the user itself.
    const { data } = await api<Envelope<AdminUser>>('/auth/login', {
      method: 'POST',
      body: { email, password },
    })

    user.value = data
  }

  async function logout(): Promise<void> {
    await api('/auth/logout', { method: 'POST' }).catch(() => undefined)
    user.value = null
    await navigateTo('/admin/login')
  }

  /** Resolves the signed-in user, returning null instead of throwing on 401. */
  async function fetchUser(): Promise<AdminUser | null> {
    try {
      user.value = (await api<Envelope<AdminUser>>('/auth/me')).data
    }
    catch {
      user.value = null
    }
    return user.value
  }

  return { user, login, logout, fetchUser, primeCsrf }
}
