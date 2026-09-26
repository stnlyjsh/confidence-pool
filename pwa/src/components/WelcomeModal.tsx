import { useState } from 'react'

const SEEN_KEY = 'confidence-pool.seenWelcome'

export function WelcomeModal() {
  const [visible, setVisible] = useState(() => !localStorage.getItem(SEEN_KEY))

  function dismiss() {
    localStorage.setItem(SEEN_KEY, 'true')
    setVisible(false)
  }

  if (!visible) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
      <div className="w-full max-w-sm rounded-lg bg-white p-5 dark:bg-slate-900">
        <p className="text-sm text-slate-700 dark:text-slate-300">
          This is a hobby project and Josh is not really a developer. Any and all feedback is appreciated & can be
          submitted in the settings page.
        </p>
        <button
          onClick={dismiss}
          className="mt-4 w-full rounded-lg bg-indigo-600 py-2 text-sm font-medium text-white"
        >
          Got it
        </button>
      </div>
    </div>
  )
}
