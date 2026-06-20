import { QueryClient } from '@tanstack/react-query'

/** App-wide React Query client. Shared so tests can reset cache between runs. */
export const queryClient = new QueryClient()
