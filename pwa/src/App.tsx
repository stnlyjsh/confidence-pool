import type { ReactNode } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import { BottomNav } from './components/BottomNav'
import { useAuth } from './context/AuthContext'
import { JoinViaInvitePage } from './pages/JoinViaInvitePage'
import { LedgerPage } from './pages/LedgerPage'
import { LoginPage } from './pages/LoginPage'
import { PicksPage } from './pages/PicksPage'
import { PoolSettingsPage } from './pages/PoolSettingsPage'
import { RegisterPage } from './pages/RegisterPage'
import { ScoreboardPage } from './pages/ScoreboardPage'
import { StandingsPage } from './pages/StandingsPage'

function RequireAuth({ children }: { children: ReactNode }) {
  const { isAuthenticated } = useAuth()
  if (!isAuthenticated) return <Navigate to="/login" replace />
  return (
    <div className="flex min-h-svh flex-1 flex-col">
      <div className="flex flex-1 flex-col">{children}</div>
      <BottomNav />
    </div>
  )
}

export function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/join/:code?" element={<JoinViaInvitePage />} />
      <Route
        path="/picks"
        element={
          <RequireAuth>
            <PicksPage />
          </RequireAuth>
        }
      />
      <Route
        path="/scoreboard"
        element={
          <RequireAuth>
            <ScoreboardPage />
          </RequireAuth>
        }
      />
      <Route
        path="/standings"
        element={
          <RequireAuth>
            <StandingsPage />
          </RequireAuth>
        }
      />
      <Route
        path="/ledger"
        element={
          <RequireAuth>
            <LedgerPage />
          </RequireAuth>
        }
      />
      <Route
        path="/settings"
        element={
          <RequireAuth>
            <PoolSettingsPage />
          </RequireAuth>
        }
      />
      <Route path="*" element={<Navigate to="/picks" replace />} />
    </Routes>
  )
}
