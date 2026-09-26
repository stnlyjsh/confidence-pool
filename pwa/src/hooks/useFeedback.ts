import { useMutation } from '@tanstack/react-query'
import { api } from '../lib/api'

export type FeedbackType = 'bug' | 'idea'

export interface Feedback {
  id: number
  type: FeedbackType
  message: string
  github_issue_url: string | null
}

export function useSubmitFeedback() {
  return useMutation({
    mutationFn: (payload: { type: FeedbackType; message: string }) =>
      api.post<{ data: Feedback }>('/api/feedback', payload).then((res) => res.data),
  })
}
