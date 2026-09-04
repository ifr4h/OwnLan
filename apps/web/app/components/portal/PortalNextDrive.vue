<template>
  <section class="nextdrive" :class="`nextdrive--${tone}`">
    <div class="nextdrive__head">
      <p class="nextdrive__eyebrow">{{ eyebrow }}</p>

      <template v-if="lesson">
        <div class="nextdrive__row">
          <h2 class="nextdrive__title">{{ lesson.starts_at_day || lesson.starts_at_display }}</h2>
          <p class="nextdrive__when">
            {{ lesson.starts_at_time }}–{{ lesson.ends_at_time }}
            <span v-if="lesson.duration_label" class="nextdrive__dur"> · {{ lesson.duration_label }}</span>
          </p>
        </div>
        <p v-if="focus" class="nextdrive__focus">{{ focus }}</p>
        <p v-if="lesson.pickup_short || lesson.pickup_address" class="nextdrive__pickup">
          {{ lesson.pickup_short || lesson.pickup_address }}
        </p>
        <div class="nextdrive__actions">
          <NuxtLink to="/portal/prepare" class="nextdrive__cta">
            {{ t('nextDrive.prepare') }}
            <span aria-hidden="true">→</span>
          </NuxtLink>
          <NuxtLink
            v-if="bookingCta && lesson"
            :to="bookingCta.path"
            class="nextdrive__cta nextdrive__cta--secondary"
          >
            {{ bookingCta.label }}
          </NuxtLink>
        </div>
      </template>

      <template v-else-if="kind === 'new_recap' && recapLessonId">
        <h2 class="nextdrive__title">{{ t('nextDrive.newRecap') }}</h2>
        <div class="nextdrive__actions">
          <NuxtLink :to="`/portal/recap/${recapLessonId}`" class="nextdrive__cta">
            {{ t('home.viewRecap') }}
            <span aria-hidden="true">→</span>
          </NuxtLink>
        </div>
      </template>

      <template v-else-if="kind === 'test_approaching'">
        <h2 class="nextdrive__title">{{ t('nextDrive.testApproaching') }}</h2>
        <p v-if="testLabel" class="nextdrive__when">{{ testLabel }}</p>
        <div class="nextdrive__actions">
          <NuxtLink to="/portal/test" class="nextdrive__cta">
            {{ t('home.openTest') }}
            <span aria-hidden="true">→</span>
          </NuxtLink>
        </div>
      </template>

      <template v-else-if="kind === 'package_low'">
        <h2 class="nextdrive__title">{{ t('nextDrive.packageLow') }}</h2>
        <p class="nextdrive__when">
          {{ t('nextDrive.packageLowHint', { credit: creditLabel || '' }) }}
        </p>
        <div class="nextdrive__actions">
          <NuxtLink to="/portal/money" class="nextdrive__cta">
            {{ t('home.openMoney') }}
            <span aria-hidden="true">→</span>
          </NuxtLink>
        </div>
      </template>

      <template v-else>
        <h2 class="nextdrive__title">{{ t('nextDrive.noBooking') }}</h2>
        <p class="nextdrive__when">{{ t('nextDrive.noBookingHint') }}</p>
        <div v-if="bookingCta" class="nextdrive__actions">
          <NuxtLink :to="bookingCta.path" class="nextdrive__cta">
            {{ bookingCta.label }}
            <span aria-hidden="true">→</span>
          </NuxtLink>
        </div>
      </template>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { PortalLesson, PortalPriority } from '~/composables/usePortal'

const props = defineProps<{
  priority: PortalPriority
  lesson: PortalLesson | null
  focus?: string | null
  testLabel?: string | null
  creditLabel?: string | null
  recapLessonId?: number | null
  bookingCta?: { label: string; path: string } | null
}>()

const { t } = usePortalI18n()

const kind = computed(() => props.priority.kind)

const eyebrow = computed(() => {
  switch (kind.value) {
    case 'lesson_today':
      return t('nextDrive.today')
    case 'lesson_tomorrow':
      return t('nextDrive.tomorrow')
    case 'lesson_upcoming':
      return t('nextDrive.upcoming')
    case 'new_recap':
      return t('home.recentRecap')
    case 'test_approaching':
      return t('test.practical')
    case 'package_low':
      return t('money.creditTitle')
    default:
      return t('nextDrive.upcoming')
  }
})

const tone = computed(() => {
  if (kind.value.startsWith('lesson_')) return 'drive'
  if (kind.value === 'test_approaching') return 'test'
  if (kind.value === 'new_recap') return 'recap'
  return 'calm'
})
</script>

<style scoped>
.nextdrive {
  padding: var(--spacing-20);
  background: linear-gradient(
    165deg,
    var(--color-frost-green) 0%,
    #d8ecdf 48%,
    var(--color-chalk-green) 100%
  );
  border-radius: var(--radius-panel);
  border: 1px solid color-mix(in srgb, var(--color-ownlane-green) 12%, var(--color-border));
}

.nextdrive--test {
  background: linear-gradient(165deg, #eef3f0 0%, var(--color-frost-green) 100%);
}

.nextdrive--recap {
  background: linear-gradient(165deg, var(--color-frost-green) 0%, var(--color-chalk-green) 100%);
}

.nextdrive--calm {
  background: var(--color-frost-green);
}

.nextdrive__head {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.nextdrive__row {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
}

.nextdrive__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-ownlane-green);
}

.nextdrive__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  line-height: var(--leading-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.nextdrive__when,
.nextdrive__pickup {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
  color: rgba(17, 17, 24, 0.78);
}

.nextdrive__focus {
  font-size: var(--text-body);
  line-height: var(--leading-body);
  color: var(--color-ink-black);
}

.nextdrive__actions {
  margin-top: var(--spacing-8);
}

.nextdrive__cta {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-8);
  min-height: 44px;
  padding: 10px 18px;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  text-decoration: none;
  font-size: var(--text-body-sm);
  box-shadow: var(--shadow-button);
}

.nextdrive__cta--secondary {
  background: transparent;
  color: var(--color-ownlane-green);
  border: 1px solid color-mix(in srgb, var(--color-ownlane-green) 30%, var(--color-border));
  box-shadow: none;
}

@media (min-width: 1024px) {
  .nextdrive .nextdrive__row {
    flex-direction: row;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--spacing-16);
    flex-wrap: wrap;
  }

  .nextdrive .nextdrive__title {
    font-size: var(--text-heading-md);
    letter-spacing: var(--tracking-heading-md);
  }

  .nextdrive .nextdrive__head {
    display: grid;
    grid-template-columns: 1fr auto;
    grid-template-areas:
      'eyebrow action'
      'row action'
      'focus action'
      'pickup action';
    gap: var(--spacing-8) var(--spacing-24);
    align-items: start;
  }

  .nextdrive .nextdrive__eyebrow {
    grid-area: eyebrow;
  }

  .nextdrive .nextdrive__row {
    grid-area: row;
  }

  .nextdrive .nextdrive__focus {
    grid-area: focus;
  }

  .nextdrive .nextdrive__pickup {
    grid-area: pickup;
  }

  .nextdrive .nextdrive__actions {
    grid-area: action;
    margin-top: 0;
    align-self: center;
  }
}
</style>
