import { useEffect, useState } from 'react'
import { useCurrentWeek } from './useCurrentWeek'
import { defaultSeasonYear } from '../lib/nflSeason'

// Defaults to whatever the API considers the current week (derived from
// synced games — see Game::currentWeek()), then lets the user navigate
// freely without a later refetch of "current" yanking them back.
export function useWeekSelection() {
  const [season, setSeason] = useState(defaultSeasonYear())
  const [week, setWeek] = useState(1)
  const [hasAppliedCurrent, setHasAppliedCurrent] = useState(false)
  const currentWeek = useCurrentWeek()

  useEffect(() => {
    if (hasAppliedCurrent) return
    if (currentWeek.isLoading) return

    if (currentWeek.data) {
      setSeason(currentWeek.data.season)
      setWeek(currentWeek.data.week)
    }

    setHasAppliedCurrent(true)
  }, [currentWeek.data, currentWeek.isLoading, hasAppliedCurrent])

  return {
    season,
    week,
    setWeek,
    isLoadingCurrentWeek: currentWeek.isLoading && !hasAppliedCurrent,
  }
}
