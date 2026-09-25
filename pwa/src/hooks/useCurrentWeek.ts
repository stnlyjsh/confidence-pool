import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'

export function useCurrentWeek() {
  return useQuery({
    queryKey: ['weeks', 'current'],
    queryFn: () =>
      api.get<{ data: { season: number; week: number } }>('/api/weeks/current').then((res) => res.data),
    retry: false,
  })
}
