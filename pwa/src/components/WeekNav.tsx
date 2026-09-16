interface WeekNavProps {
  season: number
  week: number
  onChangeWeek: (week: number) => void
}

export function WeekNav({ season, week, onChangeWeek }: WeekNavProps) {
  return (
    <div className="flex items-center gap-3 text-sm">
      <button onClick={() => onChangeWeek(Math.max(1, week - 1))} disabled={week <= 1} className="disabled:opacity-30">
        ←
      </button>
      <span>
        {season} · Week {week}
      </span>
      <button onClick={() => onChangeWeek(Math.min(22, week + 1))} disabled={week >= 22} className="disabled:opacity-30">
        →
      </button>
    </div>
  )
}
