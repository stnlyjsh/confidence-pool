import { closestCenter, DndContext, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core'
import { arrayMove, SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { useEffect, useState } from 'react'
import { Announcements } from '../components/Announcements'
import { WeekNav } from '../components/WeekNav'
import { useGames, type Game, type Team } from '../hooks/useGames'
import { usePicks, useSavePicks, type Pick } from '../hooks/usePicks'
import { useWeekSelection } from '../hooks/useWeekSelection'
import { ApiError } from '../lib/api'

// Mirrors the server's PickValidationService: the remaining values in
// 1..N (minus whatever locked picks already claimed) reflow across the
// still-open, ranked picks in order — this is only a preview for instant
// drag feedback, the server recomputes authoritatively on save.
function computePreviewValues(totalGames: number, reserved: number[], count: number): number[] {
  const available: number[] = []
  for (let value = totalGames; value >= 1 && available.length < count; value--) {
    if (!reserved.includes(value)) available.push(value)
  }
  return available
}

function kickoffLabel(game: Game): string {
  return new Date(game.kickoff_at).toLocaleString(undefined, {
    weekday: 'short',
    hour: 'numeric',
    minute: '2-digit',
  })
}

function TeamOption({
  team,
  record,
  selected,
  onClick,
}: {
  team: Team
  record: string | null
  selected: boolean
  onClick: () => void
}) {
  return (
    <button
      onClick={onClick}
      className={`flex flex-1 flex-col items-center gap-1 rounded-md px-1 py-1.5 ${
        selected ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800'
      }`}
    >
      {team.logo_url && <img src={team.logo_url} alt="" className="h-7 w-7 object-contain" />}
      <span className="text-xs font-medium">{team.city ?? team.abbreviation}</span>
      {record && (
        <span className={`text-[10px] ${selected ? 'text-indigo-100' : 'text-slate-500'}`}>{record}</span>
      )}
    </button>
  )
}

function SortableRow({
  game,
  teamId,
  value,
  onPickTeam,
  onRemove,
}: {
  game: Game
  teamId: number
  value: number | undefined
  onPickTeam: (teamId: number) => void
  onRemove: () => void
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: game.id })
  const style = { transform: CSS.Transform.toString(transform), transition, opacity: isDragging ? 0.6 : 1 }

  return (
    <div
      ref={setNodeRef}
      style={style}
      className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-2 dark:border-slate-700 dark:bg-slate-900"
    >
      <button {...attributes} {...listeners} className="cursor-grab touch-none px-1 text-lg text-slate-400">
        ⠿
      </button>
      <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-semibold text-white">
        {value ?? '–'}
      </span>
      <div className="flex flex-1 gap-1">
        <TeamOption
          team={game.away_team}
          record={game.away_team_record}
          selected={teamId === game.away_team.id}
          onClick={() => onPickTeam(game.away_team.id)}
        />
        <TeamOption
          team={game.home_team}
          record={game.home_team_record}
          selected={teamId === game.home_team.id}
          onClick={() => onPickTeam(game.home_team.id)}
        />
      </div>
      <button onClick={onRemove} className="px-1 text-xs text-slate-400">
        ✕
      </button>
    </div>
  )
}

function UnpickedRow({ game, onPick }: { game: Game; onPick: (teamId: number) => void }) {
  return (
    <div className="rounded-lg border border-dashed border-slate-300 px-3 py-2 dark:border-slate-700">
      <div className="mb-2 text-xs text-slate-500">{kickoffLabel(game)}</div>
      <div className="flex gap-2">
        <TeamOption
          team={game.away_team}
          record={game.away_team_record}
          selected={false}
          onClick={() => onPick(game.away_team.id)}
        />
        <TeamOption
          team={game.home_team}
          record={game.home_team_record}
          selected={false}
          onClick={() => onPick(game.home_team.id)}
        />
      </div>
    </div>
  )
}

function LockedRow({ game, pick }: { game: Game; pick: Pick | undefined }) {
  const team =
    pick?.picked_team_id === game.home_team.id
      ? game.home_team
      : pick?.picked_team_id === game.away_team.id
        ? game.away_team
        : null
  const record =
    pick?.picked_team_id === game.home_team.id
      ? game.home_team_record
      : pick?.picked_team_id === game.away_team.id
        ? game.away_team_record
        : null

  return (
    <div className="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 opacity-70 dark:border-slate-800 dark:bg-slate-800/50">
      <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-300 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
        {pick?.confidence_value ?? '–'}
      </span>
      {team?.logo_url && <img src={team.logo_url} alt="" className="h-6 w-6 shrink-0 object-contain" />}
      <div className="flex-1">
        <div className="text-sm font-medium">
          {team ? (
            <>
              {team.city ?? team.abbreviation}
              {record && <span className="ml-1 text-xs font-normal text-slate-400">({record})</span>}
            </>
          ) : (
            <span className="text-slate-400">No pick</span>
          )}
        </div>
        <div className="text-xs text-slate-500">
          {game.away_team.abbreviation} @ {game.home_team.abbreviation} · Locked
        </div>
      </div>
    </div>
  )
}

export function PicksPage() {
  const { season, week, setWeek, isLoadingCurrentWeek } = useWeekSelection()
  const { data: games, isLoading: gamesLoading } = useGames(season, week)
  const { data: picks, isLoading: picksLoading } = usePicks(season, week)
  const savePicks = useSavePicks(season, week)

  const [order, setOrder] = useState<number[]>([])
  const [selections, setSelections] = useState<Record<number, number>>({})
  const [justSaved, setJustSaved] = useState(false)

  useEffect(() => {
    if (!picks) return
    const unlockedPicked = picks
      .filter((p) => !p.is_locked && p.picked_team_id !== null)
      .sort((a, b) => b.confidence_value - a.confidence_value)
    setOrder(unlockedPicked.map((p) => p.game_id))
    setSelections(Object.fromEntries(unlockedPicked.map((p) => [p.game_id, p.picked_team_id as number])))
  }, [picks])

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 4 } }))

  if (isLoadingCurrentWeek || gamesLoading || picksLoading || !games || !picks) {
    return (
      <div className="flex-1 px-4 py-6">
        <h1 className="text-xl font-semibold">This week's picks</h1>
        <p className="mt-2 text-sm text-slate-500">Loading…</p>
      </div>
    )
  }

  const gamesById = new Map(games.map((g) => [g.id, g]))
  const picksByGame = new Map(picks.map((p) => [p.game_id, p]))
  const lockedGames = games.filter((g) => g.is_locked)
  const unpickedGames = games.filter((g) => !g.is_locked && !order.includes(g.id))
  const reservedValues = picks.filter((p) => p.is_locked).map((p) => p.confidence_value)
  const previewValues = computePreviewValues(games.length, reservedValues, order.length)

  function handleDragEnd(event: DragEndEvent) {
    const { active, over } = event
    if (!over || active.id === over.id) return
    setOrder((current) => {
      const oldIndex = current.indexOf(Number(active.id))
      const newIndex = current.indexOf(Number(over.id))
      return arrayMove(current, oldIndex, newIndex)
    })
    setJustSaved(false)
  }

  function pickTeam(gameId: number, teamId: number) {
    setSelections((s) => ({ ...s, [gameId]: teamId }))
    setOrder((current) => (current.includes(gameId) ? current : [...current, gameId]))
    setJustSaved(false)
  }

  function removePick(gameId: number) {
    setOrder((current) => current.filter((id) => id !== gameId))
    setSelections((s) => {
      const next = { ...s }
      delete next[gameId]
      return next
    })
    setJustSaved(false)
  }

  function save() {
    savePicks.mutate(
      order.map((gameId) => ({ game_id: gameId, picked_team_id: selections[gameId] })),
      {
        onSuccess: () => {
          setJustSaved(true)
          setTimeout(() => setJustSaved(false), 2000)
        },
      },
    )
  }

  return (
    <div className="flex-1 px-4 py-6">
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold">Picks</h1>
        <WeekNav season={season} week={week} onChangeWeek={setWeek} />
      </div>

      <Announcements />

      {games.length === 0 && <p className="text-sm text-slate-500">No games synced for this week yet.</p>}

      {/* Side by side once there's room for it (md+); stacked on mobile. */}
      <div className="md:grid md:grid-cols-2 md:items-start md:gap-6">
        {order.length > 0 && (
          <section className="mb-6 md:mb-0">
            <h2 className="mb-2 text-sm font-medium text-slate-500">Your ranking (most confident first)</h2>
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
              <SortableContext items={order} strategy={verticalListSortingStrategy}>
                <div className="space-y-2">
                  {order.map((gameId, index) => {
                    const game = gamesById.get(gameId)
                    if (!game) return null
                    return (
                      <SortableRow
                        key={gameId}
                        game={game}
                        teamId={selections[gameId]}
                        value={previewValues[index]}
                        onPickTeam={(teamId) => pickTeam(gameId, teamId)}
                        onRemove={() => removePick(gameId)}
                      />
                    )
                  })}
                </div>
              </SortableContext>
            </DndContext>
          </section>
        )}

        {unpickedGames.length > 0 && (
          <section className="mb-6 md:mb-0">
            <h2 className="mb-2 text-sm font-medium text-slate-500">Not picked yet</h2>
            <div className="space-y-2">
              {unpickedGames.map((game) => (
                <UnpickedRow key={game.id} game={game} onPick={(teamId) => pickTeam(game.id, teamId)} />
              ))}
            </div>
          </section>
        )}
      </div>

      {lockedGames.length > 0 && (
        <section className="mb-6">
          <h2 className="mb-2 text-sm font-medium text-slate-500">Locked</h2>
          <div className="space-y-2">
            {lockedGames.map((game) => (
              <LockedRow key={game.id} game={game} pick={picksByGame.get(game.id)} />
            ))}
          </div>
        </section>
      )}

      {order.length > 0 && (
        <>
          <button
            onClick={save}
            disabled={savePicks.isPending}
            className="w-full rounded-lg bg-indigo-600 py-3 text-center text-sm font-medium text-white disabled:opacity-50"
          >
            {savePicks.isPending ? 'Saving…' : justSaved ? 'Saved!' : 'Save picks'}
          </button>
          {savePicks.isError && (
            <p className="mt-2 text-center text-sm text-red-600 dark:text-red-400">
              {savePicks.error instanceof ApiError ? savePicks.error.message : 'Something went wrong'}
            </p>
          )}
        </>
      )}
    </div>
  )
}
