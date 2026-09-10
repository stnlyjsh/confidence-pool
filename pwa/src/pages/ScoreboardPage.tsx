// Live scoreboard — patched in real time from the GameScoreUpdated Reverb
// event once Phase 2 (ESPN sync) and Phase 6 (Reverb) land.
export function ScoreboardPage() {
  return (
    <div className="flex-1 px-4 py-6">
      <h1 className="text-xl font-semibold">Scoreboard</h1>
      <p className="mt-2 text-sm text-slate-500">Live scores will show here once ESPN sync is wired up.</p>
    </div>
  )
}
