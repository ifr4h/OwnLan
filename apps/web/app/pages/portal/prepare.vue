<template>
  <div class="prepare">
    <header class="prepare__head">
      <h1 class="prepare__title">{{ t('prepare.title') }}</h1>
      <p class="prepare__hint">{{ t('prepare.calmHint') }}</p>
    </header>

    <template v-if="loading && !data">
      <PortalSkeleton variant="block" />
    </template>
    <p v-else-if="error" class="prepare__error" role="alert">{{ error }}</p>

    <template v-else-if="data">
      <template v-if="data.next_lesson">
        <section class="prepare__lesson">
          <p class="prepare__day">
            {{ data.next_lesson.starts_at_day || data.next_lesson.starts_at_display }}
          </p>
          <p class="prepare__when">
            {{ data.next_lesson.starts_at_time }}–{{ data.next_lesson.ends_at_time }}
            <template v-if="data.next_lesson.duration_label">
              · {{ data.next_lesson.duration_label }}
            </template>
          </p>
          <p v-if="data.next_lesson.pickup_address" class="prepare__pickup">
            {{ t('nextDrive.pickup') }}: {{ data.next_lesson.pickup_address }}
          </p>
        </section>

        <section v-if="data.focus" class="prepare__block">
          <h2 class="prepare__section">{{ t('prepare.focus') }}</h2>
          <p class="prepare__text">{{ data.focus }}</p>
        </section>

        <NuxtLink
          v-if="data.last_route_id"
          :to="`/portal/routes/${data.last_route_id}`"
          class="prepare__link"
        >
          {{ t('prepare.lastRoute') }}
          <span aria-hidden="true">→</span>
        </NuxtLink>

        <NuxtLink
          v-if="data.last_lesson_id"
          :to="`/portal/recap/${data.last_lesson_id}`"
          class="prepare__link"
        >
          {{ t('home.viewRecap') }}
          <span aria-hidden="true">→</span>
        </NuxtLink>
      </template>

      <p v-else class="prepare__empty">{{ t('prepare.noLesson') }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { PortalPrepare } from '~/composables/usePortal'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Prepare · OwnLane' })

const { t } = usePortalI18n()
const { fetchPrepare } = usePortal()

const data = ref<PortalPrepare | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await fetchPrepare()
  } catch (e) {
    error.value = extractApiError(e, t('common.errorGeneric'))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.prepare {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-24);
  padding-top: var(--spacing-8);
  max-width: 36rem;
}

.prepare__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-md);
  letter-spacing: var(--tracking-heading-md);
}

.prepare__hint {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  color: var(--color-muted);
  max-width: 32ch;
}

.prepare__error,
.prepare__empty {
  font-size: var(--text-body-sm);
}

.prepare__error {
  color: var(--color-danger);
}

.prepare__empty {
  color: var(--color-muted);
}

.prepare__lesson {
  padding: var(--spacing-24) 0;
  border-top: 1px solid var(--color-border);
  border-bottom: 1px solid var(--color-border);
}

.prepare__day {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.prepare__when,
.prepare__pickup {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  color: rgba(17, 17, 24, 0.78);
}

.prepare__section {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-8);
}

.prepare__text {
  font-size: var(--text-body);
  line-height: var(--leading-body);
}

.prepare__link {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-8);
  min-height: 44px;
  color: var(--color-ownlane-green);
  text-decoration: none;
  font-size: var(--text-body-sm);
}
</style>
