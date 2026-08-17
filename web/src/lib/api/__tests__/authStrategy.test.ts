import { beforeEach, describe, expect, it } from 'vitest'
import { AxiosHeaders, type InternalAxiosRequestConfig } from 'axios'
import { credentialStrategy, isGuestPath, setBearerToken } from '../authStrategy'
import { clearBookingToken, getBookingToken, setBookingToken } from '../bookingToken'

function configFor(url: string): InternalAxiosRequestConfig {
  return { url, headers: new AxiosHeaders(), withCredentials: true } as InternalAxiosRequestConfig
}

beforeEach(() => {
  clearBookingToken()
  setBearerToken(null)
  sessionStorage.clear()
})

describe('isGuestPath', () => {
  it('recognises guest routes in relative and absolute form', () => {
    expect(isGuestPath('/guest/bookings/BK-1')).toBe(true)
    expect(isGuestPath('http://localhost:8000/api/v1/guest/payments/intents')).toBe(true)
    expect(isGuestPath('/bookings')).toBe(false)
    expect(isGuestPath('/me/addresses')).toBe(false)
    expect(isGuestPath(undefined)).toBe(false)
  })
})

describe('credentialStrategy', () => {
  it('attaches the booking token only on guest paths', () => {
    setBookingToken('BK-123', 'plaintext-token')

    const guest = credentialStrategy.applyAuth(configFor('/guest/bookings/BK-123'))
    expect(guest.headers.get('X-Booking-Token')).toBe('plaintext-token')

    const regular = credentialStrategy.applyAuth(configFor('/bookings'))
    expect(regular.headers.get('X-Booking-Token')).toBeUndefined()
  })

  it('never sends session cookies on a guest request', () => {
    setBookingToken('BK-123', 'plaintext-token')

    // Without this, a signed-in user's cookies would ride along and the
    // server would resolve them as that user instead of the token holder.
    const guest = credentialStrategy.applyAuth(configFor('/guest/bookings/BK-123'))
    expect(guest.withCredentials).toBe(false)
  })

  it('does not leak a bearer token onto the guest surface', () => {
    setBearerToken('sanctum-token')
    setBookingToken('BK-123', 'plaintext-token')

    const guest = credentialStrategy.applyAuth(configFor('/guest/bookings/BK-123'))
    expect(guest.headers.get('Authorization')).toBeUndefined()
    expect(guest.headers.get('X-Booking-Token')).toBe('plaintext-token')

    const regular = credentialStrategy.applyAuth(configFor('/bookings'))
    expect(regular.headers.get('Authorization')).toBe('Bearer sanctum-token')
  })

  it('picks the mode per-request rather than globally', () => {
    expect(credentialStrategy.modeFor('/bookings')).toBe('cookie')
    expect(credentialStrategy.modeFor('/guest/bookings/BK-1')).toBe('booking-token')

    setBearerToken('sanctum-token')
    expect(credentialStrategy.modeFor('/bookings')).toBe('bearer')
    // Still the token, even with a bearer credential installed.
    expect(credentialStrategy.modeFor('/guest/bookings/BK-1')).toBe('booking-token')
  })

  it('clears only the credential it was asked to clear', () => {
    setBearerToken('sanctum-token')
    setBookingToken('BK-123', 'plaintext-token')

    credentialStrategy.clear('booking-token')

    expect(getBookingToken()).toBeNull()
    expect(credentialStrategy.modeFor('/bookings')).toBe('bearer')
  })
})

describe('bookingToken storage', () => {
  it('persists to sessionStorage and never to localStorage', () => {
    setBookingToken('BK-123', 'plaintext-token')

    expect(sessionStorage.getItem('guest.booking-token')).toContain('plaintext-token')
    // A booking token grants payment and cancellation rights; it must not
    // outlive the tab or sit on disk for the next user of the machine.
    expect(localStorage.getItem('guest.booking-token')).toBeNull()
  })

  it('survives a page load through sessionStorage', () => {
    sessionStorage.setItem(
      'guest.booking-token',
      JSON.stringify({ bookingNumber: 'BK-9', token: 'restored' }),
    )
    clearBookingToken()
    sessionStorage.setItem(
      'guest.booking-token',
      JSON.stringify({ bookingNumber: 'BK-9', token: 'restored' }),
    )

    expect(getBookingToken()).toBe('restored')
  })

  it('ignores malformed stored values rather than throwing', () => {
    sessionStorage.setItem('guest.booking-token', 'not-json')
    clearBookingToken()
    sessionStorage.setItem('guest.booking-token', 'not-json')

    expect(getBookingToken()).toBeNull()
  })
})
