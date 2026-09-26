import { useMutation } from '@tanstack/react-query'
import { api } from '../lib/api'

export type FeedbackType = 'bug' | 'idea'

export interface Feedback {
  id: number
  type: FeedbackType
  message: string
  screenshot_urls: string[]
  github_issue_url: string | null
}

export function useSubmitFeedback() {
  return useMutation({
    mutationFn: (payload: { type: FeedbackType; message: string; screenshots: File[] }) => {
      const formData = new FormData()
      formData.set('type', payload.type)
      formData.set('message', payload.message)
      payload.screenshots.forEach((file) => formData.append('screenshots[]', file))

      return api.post<{ data: Feedback }>('/api/feedback', formData).then((res) => res.data)
    },
  })
}
