import { useState } from 'react'
import { useAuth } from '../context/AuthContext'
import {
  useAllLedger,
  useCloseSeason,
  useCloseWeek,
  useMarkPaid,
  useMyLedger,
  type LedgerEntry,
} from '../hooks/useLedger'

function formatCents(cents: number): string {
  return `$${(Math.abs(cents) / 100).toFixed(2)}`
}

function typeLabel(entry: LedgerEntry): string {
  switch (entry.type) {
    case 'buy_in':
      return 'Buy-in'
    case 'weekly_payout':
      return `Week ${entry.week} payout`
    case 'season_payout':
      return 'Season payout'
    default:
      return 'Adjustment'
  }
}

function EntryRow({ entry, action }: { entry: LedgerEntry; action?: React.ReactNode }) {
  const owed = entry.amount_cents > 0

  return (
    <div className="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-700">
      <div>
        {entry.user_name && <div className="text-sm font-medium">{entry.user_name}</div>}
        <div className="text-xs text-slate-500">{typeLabel(entry)}</div>
      </div>
      <div className="flex items-center gap-3">
        <div className="text-right">
          <div className={`text-sm font-semibold ${owed ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
            {owed ? 'owes' : 'owed'} {formatCents(entry.amount_cents)}
          </div>
          <div className="text-xs text-slate-500">{entry.is_paid ? 'Settled' : 'Unsettled'}</div>
        </div>
        {action}
      </div>
    </div>
  )
}

function MyLedger() {
  const { data: entries, isLoading } = useMyLedger()

  if (isLoading || !entries) {
    return <p className="text-sm text-slate-500">Loading…</p>
  }

  const balance = entries.filter((e) => !e.is_paid).reduce((sum, e) => sum + e.amount_cents, 0)

  return (
    <div className="space-y-4">
      <div className="rounded-lg bg-slate-100 px-4 py-3 text-sm dark:bg-slate-800">
        {balance === 0 && "You're all settled up."}
        {balance > 0 && (
          <span className="font-medium text-red-600 dark:text-red-400">You owe {formatCents(balance)}</span>
        )}
        {balance < 0 && (
          <span className="font-medium text-emerald-600 dark:text-emerald-400">
            You're owed {formatCents(balance)}
          </span>
        )}
      </div>
      {entries.length === 0 && <p className="text-sm text-slate-500">No ledger activity yet.</p>}
      <div className="space-y-2">
        {entries.map((entry) => (
          <EntryRow key={entry.id} entry={entry} />
        ))}
      </div>
    </div>
  )
}

function AllLedger() {
  const { data: entries, isLoading } = useAllLedger()
  const markPaid = useMarkPaid()
  const closeWeek = useCloseWeek()
  const closeSeason = useCloseSeason()
  const [week, setWeek] = useState(1)

  return (
    <div className="space-y-4">
      <div className="space-y-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
        <h2 className="text-sm font-medium text-slate-500">Close out a period</h2>
        <div className="flex flex-wrap items-center gap-2">
          <input
            type="number"
            min={1}
            value={week}
            onChange={(e) => setWeek(Number(e.target.value))}
            className="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800"
          />
          <button
            onClick={() => closeWeek.mutate(week)}
            disabled={closeWeek.isPending}
            className="rounded-lg bg-slate-200 px-3 py-1.5 text-xs font-medium disabled:opacity-50 dark:bg-slate-700"
          >
            {closeWeek.isPending ? 'Closing…' : `Close week ${week}`}
          </button>
          <button
            onClick={() => closeSeason.mutate()}
            disabled={closeSeason.isPending}
            className="rounded-lg bg-slate-200 px-3 py-1.5 text-xs font-medium disabled:opacity-50 dark:bg-slate-700"
          >
            {closeSeason.isPending ? 'Closing…' : 'Close season'}
          </button>
        </div>
        {(closeWeek.isError || closeSeason.isError) && (
          <p className="text-xs text-red-600 dark:text-red-400">
            Couldn't close that period — make sure every game is final first.
          </p>
        )}
      </div>

      {isLoading || !entries ? (
        <p className="text-sm text-slate-500">Loading…</p>
      ) : (
        <div className="space-y-2">
          {entries.map((entry) => (
            <EntryRow
              key={entry.id}
              entry={entry}
              action={
                <button
                  onClick={() => markPaid.mutate(entry.id)}
                  className="rounded-lg bg-indigo-600 px-2 py-1 text-xs font-medium text-white"
                >
                  {entry.is_paid ? 'Unmark' : 'Mark paid'}
                </button>
              }
            />
          ))}
        </div>
      )}
    </div>
  )
}

export function LedgerPage() {
  const { user } = useAuth()
  const [tab, setTab] = useState<'mine' | 'all'>('mine')
  const isCommissioner = user?.role === 'commissioner'

  return (
    <div className="flex-1 px-4 py-6">
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold">Ledger</h1>
        {isCommissioner && (
          <div className="flex overflow-hidden rounded-lg border border-slate-300 text-xs dark:border-slate-700">
            <button onClick={() => setTab('mine')} className={`px-3 py-1.5 ${tab === 'mine' ? 'bg-indigo-600 text-white' : ''}`}>
              Mine
            </button>
            <button onClick={() => setTab('all')} className={`px-3 py-1.5 ${tab === 'all' ? 'bg-indigo-600 text-white' : ''}`}>
              Everyone
            </button>
          </div>
        )}
      </div>

      {tab === 'mine' || !isCommissioner ? <MyLedger /> : <AllLedger />}
    </div>
  )
}
