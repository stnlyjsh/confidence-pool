import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { api, getToken, setToken } from '../lib/api'
import { resetEcho } from '../lib/echo'

export interface User {
  id: number
  name: string
  email: string
  role: 'commissioner' | 'player'
}

interface AuthResponse {
  token: string
  user: User
}

interface AuthContextValue {
  user: User | null
  isAuthenticated: boolean
  isLoading: boolean
  login: (email: string, password: string) => Promise<void>
  register: (name: string, email: string, password: string) => Promise<void>
  joinPool: (inviteCode: string, name: string, email: string, password: string) => Promise<void>
  logout: () => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [isLoading, setIsLoading] = useState(Boolean(getToken()))

  // On first load we only have a bearer token in localStorage, not the user
  // object — hydrate it so role-gated UI (e.g. the commissioner-only tab)
  // knows what to show without waiting for the first navigation.
  useEffect(() => {
    if (!getToken()) return
    api
      .get<{ data: User }>('/api/user')
      .then((res) => setUser(res.data))
      .catch(() => setToken(null))
      .finally(() => setIsLoading(false))
  }, [])

  function handleAuthResponse(res: AuthResponse) {
    setToken(res.token)
    setUser(res.user)
    resetEcho()
  }

  async function login(email: string, password: string) {
    const res = await api.post<AuthResponse>('/api/login', { email, password })
    handleAuthResponse(res)
  }

  async function register(name: string, email: string, password: string) {
    const res = await api.post<AuthResponse>('/api/register', { name, email, password })
    handleAuthResponse(res)
  }

  async function joinPool(inviteCode: string, name: string, email: string, password: string) {
    const res = await api.post<AuthResponse>('/api/pool/join', {
      invite_code: inviteCode,
      name,
      email,
      password,
    })
    handleAuthResponse(res)
  }

  function logout() {
    api.post('/api/logout').catch(() => {})
    setToken(null)
    setUser(null)
    resetEcho()
  }

  return (
    <AuthContext.Provider
      value={{
        user,
        isAuthenticated: Boolean(user ?? getToken()),
        isLoading,
        login,
        register,
        joinPool,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
