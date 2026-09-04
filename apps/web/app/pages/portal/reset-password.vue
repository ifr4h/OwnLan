<template>
  <section class="card">
    <p class="card__eyebrow">Reset password</p>

    <template v-if="peekLoading">
      <p class="muted">Checking your reset link…</p>
    </template>

    <template v-else-if="peekState === 'expired' || peekState === 'used' || peekState === 'invalid'">
      <h1 class="card__title">Reset link unavailable</h1>
      <p class="card__copy">{{ peekMessage }}</p>
      <NuxtLink to="/portal/forgot-password" class="btn">Request another</NuxtLink>
    </template>

    <template v-else-if="done">
      <h1 class="card__title">Password updated</h1>
      <p class="card__copy">You can sign in with your new password.</p>
      <NuxtLink to="/login" class="btn">Sign in</NuxtLink>
    </template>

    <template v-else>
      <h1 class="card__title">Choose a new password</h1>
      <form class="form" @submit.prevent="onSubmit">
        <label class="field">
          <span class="field__label">New password</span>
          <input
            v-model="password"
            class="field__input"
            type="password"
            name="new-password"
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
            name="confirm-password"
            autocomplete="new-password"
            minlength="8"
            required
          >
        </label>

        <p v-if="error" class="form__error" role="alert">{{ error }}</p>

        <button class="btn" type="submit" :disabled="pending || !token">
          {{ pending ? 'Saving…' : 'Update password' }}
        </button>
      </form>
    </template>

    <p class="footer">
      <NuxtLink to="/login" class="text-link">Back to sign in</NuxtLink>
    </p>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'portal' })
useHead({ title: 'Reset password · OwnLane' })

const route = useRoute()
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))

const peekLoading = ref(true)
const peekState = ref<'valid' | 'expired' | 'used' | 'invalid'>('invalid')
const peekMessage = ref('')
const password = ref('')
const confirm = ref('')
const pending = ref(false)
const error = ref('')
const done = ref(false)

async function loadPeek() {
  peekLoading.value = true
  if (!token.value) {
    peekState.value = 'invalid'
    peekMessage.value = 'This reset link is missing its code.'
    peekLoading.value = false
    return
  }
  try {
    const res = await apiFetch<{ state: string }>(
      `/portal/password-reset/peek?token=${encodeURIComponent(token.value)}`,
    )
    peekState.value = res.state as typeof peekState.value
    if (res.state === 'expired') peekMessage.value = 'This reset link has expired.'
    else if (res.state === 'used') peekMessage.value = 'This reset link has already been used.'
    else if (res.state === 'invalid') peekMessage.value = 'This reset link is invalid.'
  } catch {
    peekState.value = 'invalid'
    peekMessage.value = 'This reset link is invalid.'
  } finally {
    peekLoading.value = false
  }
}

async function onSubmit() {
  error.value = ''
  if (password.value !== confirm.value) {
    error.value = 'Passwords do not match.'
    return
  }
  pending.value = true
  try {
    await apiFetch('/portal/password-reset/confirm', {
      method: 'POST',
      body: { token: token.value, password: password.value },
    })
    done.value = true
  } catch (e) {
    error.value = extractApiError(e, 'Could not update password.')
  } finally {
    pending.value = false
  }
}

onMounted(() => { void loadPeek() })
watch(token, () => { void loadPeek() })
</script>

<style scoped>
.card {
  width: 100%;
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--card-padding);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
  margin-top: var(--spacing-24);
}

.card__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
}

.card__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
}

.card__copy, .muted {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
}

.muted { opacity: 0.7; }

.form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.field__input {
  min-height: 48px;
  padding: 12px 20px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-inputs);
  background: var(--surface-canvas);
}

.field__input:focus {
  outline: 2px solid var(--color-ownlane-green);
  outline-offset: 2px;
}

.form__error {
  color: var(--color-marker-red);
  font-size: var(--text-body-sm);
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  text-decoration: none;
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.text-link {
  color: var(--color-ownlane-green);
  text-decoration: underline;
  font-size: var(--text-body-sm);
}

.footer {
  margin-top: var(--spacing-8);
}
</style>
