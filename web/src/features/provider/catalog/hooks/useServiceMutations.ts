import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  createService,
  deactivateService,
  updateService,
} from '../api/serviceApi'
import type { StoreServicePayload, UpdateServicePayload } from '../types'

export function useCreateService() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: StoreServicePayload) => createService(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['provider', 'services'] })
    },
  })
}

export function useUpdateService(serviceId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: UpdateServicePayload) => updateService(serviceId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['provider', 'services'] })
      queryClient.invalidateQueries({ queryKey: ['provider', 'services', 'detail', serviceId] })
    },
  })
}

export function useDeactivateService() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (serviceId: string) => deactivateService(serviceId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['provider', 'services'] })
    },
  })
}
