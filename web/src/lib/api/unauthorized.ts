type UnauthorizedHandler = () => void

let handler: UnauthorizedHandler | null = null

/** AuthProvider registers itself here so the axios interceptor can clear auth state on 401. */
export function onUnauthorized(nextHandler: UnauthorizedHandler): void {
  handler = nextHandler
}

export function triggerUnauthorized(): void {
  handler?.()
}
