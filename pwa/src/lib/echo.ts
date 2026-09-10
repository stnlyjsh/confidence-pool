import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { getToken } from './api'

const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

let echo: Echo<'reverb'> | null = null

// Lazily created (and recreated on login) so the private-channel auth
// request always carries the current Sanctum bearer token.
export function getEcho(): Echo<'reverb'> {
  if (echo) return echo

  window.Pusher = Pusher

  echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${API_URL}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
    },
  })

  return echo
}

export function resetEcho(): void {
  echo?.disconnect()
  echo = null
}
