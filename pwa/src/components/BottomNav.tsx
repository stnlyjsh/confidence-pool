import { NavLink } from 'react-router-dom'

const tabs = [
  { to: '/picks', label: 'Picks' },
  { to: '/scoreboard', label: 'Scores' },
  { to: '/standings', label: 'Standings' },
  { to: '/ledger', label: 'Ledger' },
  { to: '/settings', label: 'Settings' },
]

export function BottomNav() {
  return (
    <nav className="sticky bottom-0 border-t border-slate-200 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
      <div className="mx-auto flex w-full max-w-2xl">
        {tabs.map((tab) => (
          <NavLink
            key={tab.to}
            to={tab.to}
            className={({ isActive }) =>
              `flex-1 py-3 text-center text-xs font-medium ${
                isActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 dark:text-slate-400'
              }`
            }
          >
            {tab.label}
          </NavLink>
        ))}
      </div>
    </nav>
  )
}
