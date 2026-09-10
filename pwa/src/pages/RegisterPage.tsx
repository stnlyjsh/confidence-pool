import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { ApiError } from '../lib/api'

// For the commissioner's initial account only — everyone else joins via
// an invite link (see JoinViaInvitePage).
export function RegisterPage() {
  const { register } = useAuth()
  const navigate = useNavigate()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setSubmitting(true)
    try {
      await register(name, email, password)
      navigate('/settings')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Something went wrong')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-6 px-6 py-12">
      <h1 className="text-2xl font-semibold">Create the pool</h1>
      <p className="text-sm text-slate-500">
        This creates your commissioner account. Everyone else joins with the invite link you'll get after.
      </p>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <input
          required
          placeholder="Your name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          className="rounded-lg border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-800"
        />
        <input
          type="email"
          required
          placeholder="Email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className="rounded-lg border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-800"
        />
        <input
          type="password"
          required
          placeholder="Password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          className="rounded-lg border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-800"
        />
        {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
        <button
          type="submit"
          disabled={submitting}
          className="rounded-lg bg-indigo-600 py-3 font-medium text-white disabled:opacity-50"
        >
          {submitting ? 'Creating…' : 'Create pool'}
        </button>
      </form>
      <p className="text-center text-sm text-slate-500">
        Already have an account? <Link to="/login" className="text-indigo-600 dark:text-indigo-400">Log in</Link>
      </p>
    </div>
  )
}
