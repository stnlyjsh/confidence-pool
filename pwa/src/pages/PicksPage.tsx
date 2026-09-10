// Weekly picks screen — drag-reorder list of games where position derives
// confidence value. Built out in Phase 3 once /api/weeks/{season}/{week}/picks
// exists; this is the routing/layout placeholder from Phase 0.
export function PicksPage() {
  return (
    <div className="flex-1 px-4 py-6">
      <h1 className="text-xl font-semibold">This week's picks</h1>
      <p className="mt-2 text-sm text-slate-500">Picks will show here once the schedule sync is wired up.</p>
    </div>
  )
}
