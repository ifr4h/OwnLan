<template>
  <PortalPage>
    <template #header>
      <header>
        <p class="portal-page__meta">Review</p>
        <h1 class="portal-page__title">{{ confirmLabel }}</h1>
      </header>
    </template>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <div v-else-if="slot" class="confirm ol-stack">
      <section class="ol-card ol-stack">
        <p class="confirm__day">{{ slot.dayLabel }}</p>
        <p class="confirm__time">{{ slot.timeLabel }}</p>
        <p class="confirm__meta">{{ durationLabel }} · Pickup: {{ pickupLabel }}</p>
        <p v-if="slot.includedInPackage" class="confirm__price">Included in your package</p>
        <p v-else class="confirm__price">{{ slot.priceLabel }}</p>
        <p v-if="slot.packageAfter" class="confirm__hint">
          Your package balance after this lesson: {{ slot.packageAfter }}
        </p>
      </section>

      <p v-if="mode === 'request'" class="ol-meta">
        Your instructor will confirm it.
      </p>

      <p v-if="requiresPayment && mode === 'instant' && slot && !slot.includedInPackage" class="confirm__hint">
        This time is held for 10 minutes while you pay.
      </p>

      <div class="ol-actions">
        <button class="ol-btn" type="button" :disabled="submitting" @click="onConfirm">
          {{ submitting ? 'Working…' : confirmLabel }}
        </button>
        <NuxtLink class="ol-btn ol-btn--ghost" to="/portal/book">Back</NuxtLink>
      </div>

      <p v-if="submitError" class="ol-error" role="alert">{{ submitError }}</p>
    </div>
  </PortalPage>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'portal' })
useHead({ title: 'Confirm · OwnLane' })

const route = useRoute()
const { fetchAvailability, fetchSettings, submitBooking, createBookingHold } = usePortalBooking()
const { payBookingHold, redirectToCheckout } = useOnlinePayments()
const { durationLabel: formatDuration } = useLessons()

const loading = ref(true)
const error = ref('')
const submitError = ref('')
const submitting = ref(false)
const mode = ref<'request' | 'instant'>('request')
const requiresPayment = ref(false)

const slot = ref<{
  dayLabel: string
  timeLabel: string
  durationMinutes: number
  pickup: string | null
  priceLabel: string
  includedInPackage: boolean
  packageAfter: string
  startsAtLocal: string
} | null>(null)

const lessonId = computed(() => {
  const raw = route.query.lesson_id
  return raw ? Number(raw) : null
})

const confirmLabel = computed(() => {
  if (lessonId.value) {
    return mode.value === 'instant' ? 'Move lesson' : 'Request this time'
  }
  if (mode.value === 'instant' && requiresPayment.value && slot.value && !slot.value.includedInPackage) {
    return submitting.value ? 'Starting…' : `Pay & book · ${slot.value.priceLabel}`
  }
  return mode.value === 'instant' ? 'Book this time' : 'Request this time'
})

const durationLabel = computed(() =>
  slot.value ? formatDuration(slot.value.durationMinutes) : '',
)

const pickupLabel = computed(() => slot.value?.pickup || 'Home')

onMounted(async () => {
  const startsAtLocal = String(route.query.starts_at_local || '')
  if (!startsAtLocal) {
    error.value = 'Choose a time first.'
    loading.value = false
    return
  }
  try {
    const settings = await fetchSettings()
    mode.value = lessonId.value
      ? (settings.reschedule_mode === 'instant' ? 'instant' : 'request')
      : (settings.booking_mode === 'instant' ? 'instant' : 'request')
    requiresPayment.value = Boolean(settings.requires_payment_to_confirm)

    const availability = await fetchAvailability({
      exclude_lesson_id: lessonId.value ?? undefined,
      duration_minutes: route.query.duration_minutes
        ? Number(route.query.duration_minutes)
        : undefined,
      pickup_address: typeof route.query.pickup_address === 'string'
        ? route.query.pickup_address
        : undefined,
    })

    const durationMinutes = route.query.duration_minutes
      ? Number(route.query.duration_minutes)
      : availability.duration_minutes

    let dayLabel = ''
    let timeLabel = ''
    for (const week of availability.days) {
      for (const day of week.days) {
        for (const time of day.times) {
          if (time.starts_at_local === startsAtLocal) {
            dayLabel = day.date_label
            timeLabel = `${time.starts_at_time}–${time.ends_at_time}`
          }
        }
      }
    }
    if (!dayLabel && availability.suggested?.starts_at_local === startsAtLocal) {
      dayLabel = availability.suggested.date_label
      timeLabel = `${availability.suggested.starts_at_time}–${availability.suggested.ends_at_time}`
    }

    slot.value = {
      dayLabel: dayLabel || startsAtLocal.split(' ')[0] || 'Selected time',
      timeLabel: timeLabel || startsAtLocal.split(' ')[1] || '',
      durationMinutes,
      pickup: availability.pickup_address,
      priceLabel: availability.price.amount_label,
      includedInPackage: availability.price.included_in_package,
      packageAfter: availability.price.package_balance_after_label,
      startsAtLocal,
    }
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Could not load booking details.'
  } finally {
    loading.value = false
  }
})

async function onConfirm() {
  if (!slot.value) return
  submitting.value = true
  submitError.value = ''
  try {
    if (
      !lessonId.value
      && mode.value === 'instant'
      && requiresPayment.value
      && !slot.value.includedInPackage
    ) {
      const hold = await createBookingHold({
        starts_at_local: slot.value.startsAtLocal,
        duration_minutes: slot.value.durationMinutes,
        pickup_address: slot.value.pickup,
      })
      const session = await payBookingHold(hold.hold_id)
      redirectToCheckout(session)
      return
    }

    const result = await submitBooking({
      type: lessonId.value ? 'reschedule' : 'book',
      lesson_id: lessonId.value ?? undefined,
      starts_at_local: slot.value.startsAtLocal,
      duration_minutes: slot.value.durationMinutes,
      pickup_address: slot.value.pickup,
      client_mutation_id: crypto.randomUUID(),
    })
    await navigateTo({
      path: '/portal/book/done',
      query: {
        outcome: result.outcome,
        day: slot.value.dayLabel,
        time: slot.value.timeLabel,
      },
    })
  } catch (e: unknown) {
    submitError.value = e instanceof Error ? e.message : 'Could not complete booking.'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.confirm__day {
  font-weight: 600;
  font-size: var(--text-heading-sm);
}

.confirm__time {
  font-size: var(--text-body-lg);
}

.confirm__meta,
.confirm__hint {
  color: rgba(17, 17, 24, 0.75);
  font-size: var(--text-body-sm);
}

.confirm__price {
  font-weight: 600;
}
</style>
