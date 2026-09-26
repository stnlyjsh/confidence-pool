import { useMutation } from '@tanstack/react-query'
import { api } from '../lib/api'

export type FeedbackType = 'bug' | 'idea'

export interface Feedback {
  id: number
  type: FeedbackType
  message: string
  screenshot_url: string | null
  github_issue_url: string | null
}

export function useSubmitFeedback() {
  return useMutation({
    mutationFn: (payload: { type: FeedbackType; message: string; screenshot: File | null }) => {
      const formData = new FormData()
      formData.set('type', payload.type)
      formData.set('message', payload.message)
      if (payload.screenshot) formData.set('screenshot', payload.screenshot)

      return api.post<{ data: Feedback }>('/api/feedback', formData).then((res) => res.data)
    },
  })
}
