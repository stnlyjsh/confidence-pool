import { createContext, useContext, useState, type ReactNode } from 'react'
import { api, getToken, setToken } from '../lib/api'
import { resetEcho } from '../lib/echo'

interface User {
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
  login: (email: string, password: string) => Promise<void>
  register: (name: string, email: string, password: string) => Promise<void>
  joinPool: (inviteCode: string, name: string, email: string, password: string) => Promise<void>
  logout: () => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)

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
    setToken(null)
    setUser(null)
    resetEcho()
  }

  return (
    <AuthContext.Provider
      value={{ user, isAuthenticated: Boolean(user ?? getToken()), login, register, joinPool, logout }}
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
