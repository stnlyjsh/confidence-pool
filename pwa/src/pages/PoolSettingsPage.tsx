import { useEffect, useState } from 'react'
import { Toast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'
import { useSubmitFeedback, type FeedbackType } from '../hooks/useFeedback'
import { useUpdatePool, useRegenerateInvite, usePool } from '../hooks/usePool'
import { ApiError } from '../lib/api'

const feedbackTypeLabels: Record<FeedbackType, string> = {
  bug: 'Issue',
  idea: 'Improvement',
}

function centsToDollarsInput(cents: number): string {
  return (cents / 100).toFixed(2)
}

function dollarsInputToCents(value: string): number {
  return Math.round(parseFloat(value || '0') * 100)
}

export function PoolSettingsPage() {
  const { user, logout, updateName } = useAuth()
  const { data: pool, isLoading } = usePool()
  const updatePool = useUpdatePool()
  const regenerateInvite = useRegenerateInvite()

  const [name, setName] = useState('')
  const [buyIn, setBuyIn] = useState('0.00')
  const [copied, setCopied] = useState(false)

  const [displayName, setDisplayName] = useState('')
  const [savingDisplayName, setSavingDisplayName] = useState(false)
  const [displayNameError, setDisplayNameError] = useState<string | null>(null)
  const [displayNameSaved, setDisplayNameSaved] = useState(false)

  const [feedbackType, setFeedbackType] = useState<FeedbackType>('bug')
  const [feedbackMessage, setFeedbackMessage] = useState('')
  const [toastMessage, setToastMessage] = useState<string | null>(null)
  const submitFeedback = useSubmitFeedback()

  useEffect(() => {
    if (!pool) return
    setName(pool.name)
    setBuyIn(centsToDollarsInput(pool.buy_in_amount_cents))
  }, [pool])

  useEffect(() => {
    if (user) setDisplayName(user.name)
  }, [user])

  async function saveDisplayName() {
    setDisplayNameError(null)
    setSavingDisplayName(true)
    try {
      await updateName(displayName)
      setDisplayNameSaved(true)
      setTimeout(() => setDisplayNameSaved(false), 2000)
    } catch (err) {
      setDisplayNameError(err instanceof ApiError ? err.message : 'Something went wrong')
    } finally {
      setSavingDisplayName(false)
    }
  }

  if (isLoading || !pool) {
    return (
      <div className="flex-1 px-4 py-6">
        <h1 className="text-xl font-semibold">Pool settings</h1>
        <p className="mt-2 text-sm text-slate-500">Loading…</p>
      </div>
    )
  }

  const isCommissioner = user?.role === 'commissioner'
  const inviteLink = pool.invite_code ? `${window.location.origin}/join/${pool.invite_code}` : null

  function saveSettings() {
    updatePool.mutate({ name, buy_in_amount_cents: dollarsInputToCents(buyIn) })
  }

  function copyInviteLink() {
    if (!inviteLink) return
    navigator.clipboard.writeText(inviteLink).then(() => {
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    })
  }

  function sendFeedback() {
    submitFeedback.mutate(
      { type: feedbackType, message: feedbackMessage },
      {
        onSuccess: () => {
          setFeedbackMessage('')
          setToastMessage('Much appreciated! :]')
          setTimeout(() => setToastMessage(null), 2500)
        },
      },
    )
  }

  return (
    <div className="flex-1 space-y-6 px-4 py-6">
      <div>
        <h1 className="text-xl font-semibold">Pool settings</h1>
        <p className="mt-1 text-sm text-slate-500">
          {pool.season_year} season · {pool.status}
        </p>
      </div>

      <section className="space-y-3">
        <h2 className="text-sm font-medium text-slate-500">Your account</h2>
        <label className="block text-sm">
          Display name
          <input
            value={displayName}
            onChange={(e) => setDisplayName(e.target.value)}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-800"
          />
        </label>
        {displayNameError && <p className="text-sm text-red-600 dark:text-red-400">{displayNameError}</p>}
        <button
          onClick={saveDisplayName}
          disabled={savingDisplayName || displayName === user?.name}
          className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
        >
          {savingDisplayName ? 'Saving…' : displayNameSaved ? 'Saved!' : 'Save name'}
        </button>
      </section>

      {inviteLink && (
        <section className="space-y-2">
          <h2 className="text-sm font-medium text-slate-500">Invite link</h2>
          <div className="flex gap-2">
            <input
              readOnly
              value={inviteLink}
              className="flex-1 truncate rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800"
            />
            <button
              onClick={copyInviteLink}
              className="rounded-lg bg-slate-200 px-3 py-2 text-sm font-medium dark:bg-slate-700"
            >
              {copied ? 'Copied!' : 'Copy'}
            </button>
          </div>
          <button
            onClick={() => regenerateInvite.mutate()}
            disabled={regenerateInvite.isPending}
            className="text-sm text-indigo-600 disabled:opacity-50 dark:text-indigo-400"
          >
            {regenerateInvite.isPending ? 'Regenerating…' : 'Regenerate invite link'}
          </button>
        </section>
      )}

      <section className="space-y-3">
        <h2 className="text-sm font-medium text-slate-500">Pool details</h2>
        <label className="block text-sm">
          Name
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            disabled={!isCommissioner}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800"
          />
        </label>
        <label className="block text-sm">
          Buy-in ($)
          <input
            type="number"
            step="0.01"
            min="0"
            value={buyIn}
            onChange={(e) => setBuyIn(e.target.value)}
            disabled={!isCommissioner}
            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800"
          />
        </label>
        {isCommissioner && (
          <button
            onClick={saveSettings}
            disabled={updatePool.isPending}
            className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
          >
            {updatePool.isPending ? 'Saving…' : 'Save changes'}
          </button>
        )}
      </section>

      <section className="space-y-3">
        <h2 className="text-sm font-medium text-slate-500">Feedback</h2>
        <p className="text-xs text-slate-500">No penny for your thoughts</p>
        <div className="flex gap-2">
          {(['bug', 'idea'] as const).map((type) => (
            <button
              key={type}
              onClick={() => setFeedbackType(type)}
              className={`rounded-lg px-3 py-1.5 text-sm ${
                feedbackType === type ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800'
              }`}
            >
              {feedbackTypeLabels[type]}
            </button>
          ))}
        </div>
        <textarea
          value={feedbackMessage}
          onChange={(e) => setFeedbackMessage(e.target.value)}
          rows={3}
          placeholder={feedbackType === 'bug' ? 'What went wrong?' : 'open to ANY input'}
          className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800"
        />
        {submitFeedback.isError && (
          <p className="text-sm text-red-600 dark:text-red-400">
            {submitFeedback.error instanceof ApiError ? submitFeedback.error.message : 'Something went wrong'}
          </p>
        )}
        <button
          onClick={sendFeedback}
          disabled={submitFeedback.isPending || feedbackMessage.trim() === ''}
          className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
        >
          {submitFeedback.isPending ? 'Sending…' : 'Send'}
        </button>
      </section>

      <button onClick={logout} className="text-sm text-red-600 dark:text-red-400">
        Log out
      </button>

      <Toast message={toastMessage} />
    </div>
  )
}
