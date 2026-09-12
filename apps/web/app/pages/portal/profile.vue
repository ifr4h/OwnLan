<template>
  <PortalPage>
    <template #header>
      <h1 class="portal-page__title">{{ t('profile.title') }}</h1>
    </template>

    <template v-if="loading && !home">
      <PortalSkeleton variant="line" />
      <PortalSkeleton variant="block" />
    </template>

    <p v-else-if="error" class="profile__error" role="alert">{{ error }}</p>

    <PortalMainGrid v-else-if="home" variant="split">
      <div class="profile__main">
        <section class="profile__block" aria-labelledby="learner">
          <h2 id="learner" class="profile__section">{{ t('profile.account') }}</h2>
          <p class="profile__name">{{ home.learner.full_name }}</p>
        </section>

        <section class="profile__block" aria-labelledby="instructor">
          <h2 id="instructor" class="profile__section">{{ t('profile.instructor') }}</h2>
          <p class="profile__name">{{ home.instructor.display_name }}</p>
          <p v-if="home.instructor.business_name" class="profile__meta">
            {{ home.instructor.business_name }}
          </p>
          <p v-if="home.instructor.contact_phone" class="profile__meta">
            <a :href="`tel:${home.instructor.contact_phone}`">{{ home.instructor.contact_phone }}</a>
          </p>
          <p v-if="home.instructor.contact_email" class="profile__meta">
            <a :href="`mailto:${home.instructor.contact_email}`">{{ home.instructor.contact_email }}</a>
          </p>
          <p v-if="home.instructor.service_area" class="profile__meta">
            {{ home.instructor.service_area }}
          </p>
        </section>
      </div>

      <PortalContextRail>
        <PortalContextCard :title="t('nav.test')" action-to="/portal/test" :action-label="t('home.openTest')">
          <template v-if="home.practical_test || home.theory">
            <p v-if="home.practical_test">{{ home.practical_test.countdown_label }}</p>
            <p v-if="home.theory">{{ home.theory.label }}</p>
          </template>
          <p v-else class="profile__muted">{{ t('test.noPractical') }}</p>
        </PortalContextCard>

        <PortalContextCard :title="t('money.title')" action-to="/portal/money" :action-label="t('home.openMoney')">
          {{ home.package_and_balance.credit_label }}
        </PortalContextCard>

        <PortalContextCard
          :title="t('profile.places')"
          action-to="/portal/places"
          :action-label="t('profile.openPlaces')"
        >
          {{ t('profile.placesHint') }}
        </PortalContextCard>

        <button
          type="button"
          class="profile__signout"
          :disabled="loggingOut"
          @click="onLogout"
        >
          {{ loggingOut ? '…' : t('nav.signOut') }}
        </button>
      </PortalContextRail>
    </PortalMainGrid>
  </PortalPage>
</template>

<script setup lang="ts">
import type { PortalHome } from '~/composables/usePortal'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Profile · OwnLane' })

const { t } = usePortalI18n()
const { fetchHome, readCachedHome } = usePortal()
const { logout } = usePortalAuth()

const home = ref<PortalHome | null>(null)
const loading = ref(true)
const error = ref('')
const loggingOut = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  const cached = readCachedHome()
  if (cached) home.value = cached
  try {
    home.value = await fetchHome({ allowStale: true })
  } catch (e) {
    error.value = extractApiError(e, t('common.errorGeneric'))
  } finally {
    loading.value = false
  }
}

async function onLogout() {
  loggingOut.value = true
  try {
    await logout()
    await navigateTo('/login')
  } finally {
    loggingOut.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.profile__error {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

.profile__block {
  margin-bottom: var(--spacing-28);
}

.profile__section {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-12);
}

.profile__name {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.profile__meta {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.profile__meta a {
  color: var(--color-ownlane-green);
  text-decoration: none;
}

.profile__muted {
  color: var(--color-muted);
  font-size: var(--text-body-sm);
}

.profile__signout {
  width: 100%;
  min-height: 48px;
  padding: 0 var(--spacing-16);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-panel);
  background: var(--surface-card);
  font: inherit;
  font-size: var(--text-body-sm);
  cursor: pointer;
  text-align: left;
}

.profile__signout:hover:not(:disabled),
.profile__signout:focus-visible:not(:disabled) {
  border-color: var(--color-ownlane-green);
}
</style>
