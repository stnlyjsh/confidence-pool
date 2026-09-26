import { ANNOUNCEMENTS } from '../data/announcements'

function formatDate(dateString: string): string {
  return new Date(`${dateString}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

export function Announcements() {
  const recent = ANNOUNCEMENTS.slice(0, 5)

  if (recent.length === 0) return null

  return (
    <section className="mb-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
      <h2 className="mb-2 text-sm font-medium text-slate-500">What's new</h2>
      <ul className="space-y-1.5">
        {recent.map((item) => (
          <li key={item.text} className="text-sm">
            <span className="mr-2 text-xs text-slate-400">{formatDate(item.date)}</span>
            {item.text}
          </li>
        ))}
      </ul>
    </section>
  )
}
