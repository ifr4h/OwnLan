<template>
  <section class="card">
    <p class="card__eyebrow">Join as companion</p>

    <template v-if="peekLoading">
      <p class="muted">Checking your invite…</p>
    </template>

    <template v-else-if="peekError">
      <h1 class="card__title">Invite unavailable</h1>
      <p class="card__copy">{{ peekError }}</p>
      <NuxtLink to="/login" class="text-link">Already set up? Sign in</NuxtLink>
    </template>

    <template v-else-if="peek">
      <h1 class="card__title">
        {{
          peek.already_activated
            ? 'You’re already set up'
            : peek.learner_first_name
              ? `Help ${peek.learner_first_name} learn`
              : 'Help someone learn'
        }}
      </h1>
      <p class="card__copy">
        <template v-if="peek.already_activated">
          Sign in with {{ peek.email }} to continue.
        </template>
        <template v-else>
          Choose a password for {{ peek.email }}. You’ll only see what they’ve chosen to share.
        </template>
      </p>

      <template v-if="peek.already_activated">
        <NuxtLink to="/login" class="btn">Sign in <span aria-hidden="true">→</span></NuxtLink>
      </template>

      <form v-else class="form" @submit.prevent="onSubmit">
        <label class="field">
          <span class="field__label">Password</span>
          <input
            v-model="password"
            class="field__input"
            type="password"
            autocomplete="new-password"
            minlength="8"
            required
          >
        </label>
        <label class="field">
          <span class="field__label">Confirm password</span>
          <input
            v-model="confirm"
            class="field__input"
            type="password"
            autocomplete="new-password"
            minlength="8"
            required
          >
        </label>
        <p v-if="error" class="form__error" role="alert">{{ error }}</p>
        <button class="btn" type="submit" :disabled="pending || !token">
          {{ pending ? 'Saving…' : 'Open companion view' }}
          <span aria-hidden="true">→</span>
        </button>
      </form>
    </template>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'companion' })
useHead({ title: 'Join as companion · OwnLane' })

const route = useRoute()
const { peekInvite, activate } = useCompanion()

const token = computed(() => {
  const raw = route.query.token
  return typeof raw === 'string' ? raw : ''
})

const peek = ref<{
  companion_name: string
  email: string
  already_activated: boolean
  learner_first_name: string | null
} | null>(null)
const peekLoading = ref(true)
const peekError = ref('')
const password = ref('')
const confirm = ref('')
const pending = ref(false)
const error = ref('')

async function loadPeek() {
  peekLoading.value = true
  peekError.value = ''
  if (!token.value) {
    peekError.value = 'This invite link is missing a token.'
    peekLoading.value = false
    return
  }
  try {
    peek.value = await peekInvite(token.value)
  } catch (e) {
    peek.value = null
    peekError.value = extractApiError(e, 'This invite is no longer available.')
  } finally {
    peekLoading.value = false
  }
}

async function onSubmit() {
  error.value = ''
  if (password.value !== confirm.value) {
    error.value = 'Passwords don’t match.'
    return
  }
  pending.value = true
  try {
    await activate({ token: token.value, password: password.value })
    await navigateTo('/companion')
  } catch (e) {
    error.value = extractApiError(e, 'Could not activate your account.')
  } finally {
    pending.value = false
  }
}

onMounted(() => {
  void loadPeek()
})
</script>

<style scoped>
.card {
  width: 100%;
  background: var(--color-paper-white);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--card-padding, 24px);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
  margin-top: var(--spacing-24);
}

.card__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
  margin: 0;
}

.card__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  margin: 0;
}

.card__copy {
  font-size: var(--text-body-sm);
  margin: 0;
}

.muted {
  color: var(--color-muted);
  font-size: var(--text-body-sm);
}

.text-link {
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
}

.form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field__label {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.field__input {
  min-height: 48px;
  padding: 10px 14px;
  border: 1px solid var(--color-border);
  border-radius: 14px;
  background: var(--color-frost-green);
  font: inherit;
}

.form__error {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
  margin: 0;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 48px;
  padding: 10px 18px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  font: inherit;
  font-size: var(--text-body-sm);
  text-decoration: none;
  cursor: pointer;
  box-shadow: var(--shadow-button);
  width: fit-content;
}

.btn:disabled {
  opacity: 0.7;
}
</style>
