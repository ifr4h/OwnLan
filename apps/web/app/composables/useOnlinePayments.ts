import { apiFetch } from '~/composables/useApi'

export type PaymentAccountSettings = {
  configured: boolean
  account: {
    status: string
    status_label: string
    charges_enabled: boolean
    payouts_enabled: boolean
    details_submitted: boolean
    ready: boolean
  } | null
  fee_notice: string | null
  stripe_publishable_key: string | null
  booking_payment_policy: 'none' | 'request_after' | 'require_to_confirm'
  booking_payment_options: Array<{ value: string; label: string }>
}

export type PackageOffering = {
  id: number
  label: string
  purchased_minutes: number
  hours_label: string
  price_pence: number
  price_label: string
}

export type PortalPaymentHistoryItem = {
  id: number
  amount_label: string
  method_label: string
  status: string
  date_label: string
  purpose: string
}

export type PortalPaymentsContext = {
  online_payments_available: boolean
  amount_owed_pence?: number
  amount_owed_label?: string
  can_pay_balance?: boolean
  package_offerings?: PackageOffering[]
  payment_history?: PortalPaymentHistoryItem[]
  fee_notice?: string | null
  stripe_publishable_key?: string | null
}

export type CheckoutSession = {
  token: string
  amount_pence: number
  amount_label: string
  purpose: string
  checkout_url: string | null
  client_secret: string | null
  stripe_publishable_key: string | null
  expires_at: string | null
  payment_url?: string
}

export type GuestPaymentView = {
  token: string
  amount_label: string
  description: string
  business_name: string
  status: string
  checkout_url: string | null
  stripe_publishable_key: string | null
}

export type PaymentConfirmResult = {
  status: 'succeeded' | 'processing' | 'failed' | 'fulfilled' | 'already_fulfilled'
  message?: string
  checkout?: Record<string, unknown>
  payment?: Record<string, unknown>
}

export function useOnlinePayments() {
  async function fetchAccountSettings(): Promise<PaymentAccountSettings> {
    return await apiFetch<PaymentAccountSettings>('/payments/account')
  }

  async function startOnboarding(): Promise<{ onboarding_url?: string; account: PaymentAccountSettings['account'] }> {
    return await apiFetch('/payments/account/onboard', { method: 'POST', body: {} })
  }

  async function refreshAccount(): Promise<{ account: PaymentAccountSettings['account'] }> {
    return await apiFetch('/payments/account/refresh', { method: 'POST', body: {} })
  }

  async function updateBookingPolicy(policy: string): Promise<PaymentAccountSettings> {
    return await apiFetch<PaymentAccountSettings>('/payments/account/booking-policy', {
      method: 'POST',
      body: { booking_payment_policy: policy },
    })
  }

  async function listOfferings(): Promise<{ items: PackageOffering[] }> {
    return await apiFetch('/payments/offerings')
  }

  async function createOffering(payload: {
    label: string
    purchased_minutes: number
    price_pence: number
    portal_visible?: boolean
  }): Promise<PackageOffering> {
    return await apiFetch('/payments/offerings', { method: 'POST', body: payload })
  }

  async function requestPayment(learnerId: number, lessonChargeId?: number): Promise<CheckoutSession> {
    return await apiFetch(`/learners/${learnerId}/payment-request`, {
      method: 'POST',
      body: lessonChargeId ? { lesson_charge_id: lessonChargeId } : {},
    })
  }

  async function refundPayment(paymentId: number): Promise<Record<string, unknown>> {
    return await apiFetch(`/payments/${paymentId}/refund`, { method: 'POST', body: {} })
  }

  async function payOutstandingBalance(lessonChargeId?: number): Promise<CheckoutSession> {
    return await apiFetch('/portal/pay/balance', {
      method: 'POST',
      body: lessonChargeId ? { lesson_charge_id: lessonChargeId } : {},
    })
  }

  async function payPackage(offeringId: number): Promise<CheckoutSession> {
    return await apiFetch('/portal/pay/package', {
      method: 'POST',
      body: { package_offering_id: offeringId },
    })
  }

  async function payBookingHold(holdId: number): Promise<CheckoutSession> {
    return await apiFetch('/portal/pay/booking-hold', {
      method: 'POST',
      body: { hold_id: holdId },
    })
  }

  async function confirmPortalPayment(token: string): Promise<PaymentConfirmResult> {
    return await apiFetch(`/portal/pay/${token}/confirm`, { method: 'POST', body: {} })
  }

  async function fetchGuestPayment(token: string): Promise<GuestPaymentView> {
    return await apiFetch(`/pay/${token}`)
  }

  async function startGuestPayment(token: string): Promise<CheckoutSession> {
    return await apiFetch(`/pay/${token}/start`, { method: 'POST', body: {} })
  }

  async function confirmGuestPayment(token: string): Promise<PaymentConfirmResult> {
    return await apiFetch(`/pay/${token}/confirm`, { method: 'POST', body: {} })
  }

  function redirectToCheckout(session: CheckoutSession): void {
    if (session.checkout_url) {
      window.location.href = session.checkout_url
      return
    }
    throw new Error('Checkout is not available right now.')
  }

  return {
    fetchAccountSettings,
    startOnboarding,
    refreshAccount,
    updateBookingPolicy,
    listOfferings,
    createOffering,
    requestPayment,
    refundPayment,
    payOutstandingBalance,
    payPackage,
    payBookingHold,
    confirmPortalPayment,
    fetchGuestPayment,
    startGuestPayment,
    confirmGuestPayment,
    redirectToCheckout,
  }
}
