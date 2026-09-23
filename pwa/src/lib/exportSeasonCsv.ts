import type { LedgerEntry } from '../hooks/useLedger'
import type { StandingsRow } from '../hooks/useStandings'

function csvCell(value: string | number): string {
  const str = String(value)
  return /[",\n]/.test(str) ? `"${str.replace(/"/g, '""')}"` : str
}

export function buildSeasonSummaryCsv(standings: StandingsRow[], ledger: LedgerEntry[]): string {
  const rows = standings.map((row) => {
    const userEntries = ledger.filter((e) => e.user_id === row.user_id)
    const netBalanceCents = userEntries.filter((e) => !e.is_paid).reduce((sum, e) => sum + e.amount_cents, 0)
    const buyInPaid = userEntries.some((e) => e.type === 'buy_in' && e.is_paid)

    return [
      row.name,
      row.total_points,
      row.correct_count,
      buyInPaid ? 'Paid' : 'Unpaid',
      (netBalanceCents / 100).toFixed(2),
    ]
  })

  const header = ['Name', 'Season Points', 'Correct Picks', 'Buy-in', 'Net Balance ($, + = owes, - = owed)']

  return [header, ...rows].map((row) => row.map(csvCell).join(',')).join('\n')
}

export function downloadCsv(filename: string, contents: string): void {
  const blob = new Blob([contents], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}
