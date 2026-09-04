<template>
  <section class="card">
    <p class="card__eyebrow">Join</p>

    <template v-if="peekLoading">
      <p class="muted">Checking your invite…</p>
    </template>

    <template v-else-if="peekError">
      <h1 class="card__title">Invite unavailable</h1>
      <p class="card__copy">{{ peekError }}</p>
      <NuxtLink to="/login" class="text-link">Already set up? Sign in</NuxtLink>
    </template>

    <template v-else-if="peek?.state === 'already_connected'">
      <h1 class="card__title">Already connected</h1>
      <p class="card__copy">
        Your learner account is already set up. Sign in, or reset your password if you need to.
      </p>
      <div class="actions">
        <NuxtLink to="/login" class="btn">Sign in</NuxtLink>
        <NuxtLink to="/portal/forgot-password" class="text-link">Forgot password?</NuxtLink>
      </div>
    </template>

    <template v-else-if="peek?.state === 'expired'">
      <h1 class="card__title">Invite expired</h1>
      <p class="card__copy">
        This invite link has expired. Ask your instructor to send a new invite.
      </p>
    </template>

    <template v-else-if="peek">
      <h1 class="card__title">Hi {{ peek.learner_first_name }}</h1>
      <p class="card__copy">
        Set a password to see your lessons with your instructor. You'll sign in with
        {{ peek.email }}.
      </p>

      <form class="form" @submit.prevent="onSubmit">
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
          {{ pending ? 'Saving…' : 'Open my lessons' }}
          <span aria-hidden="true">→</span>
        </button>
      </form>
    </template>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'portal' })
useHead({ title: 'Join · OwnLane' })

const route = useRoute()
const { peekInvite, activate } = usePortalAuth()

const token = computed(() => {
  const raw = route.query.token
  return typeof raw === 'string' ? raw : ''
})

type Peek = {
  state: 'valid' | 'expired' | 'already_connected' | 'invalid'
  learner_first_name: string
  email?: string
}

const peek = ref<Peek | null>(null)
const peekLoading = ref(true)
const peekError = ref('')
const password = ref('')
const confirm = ref('')
const pending = ref(false)
const error = ref('')

async function loadPeek() {
  peekLoading.value = true
  peekError.value = ''
  peek.value = null
  if (!token.value) {
    peekError.value = 'This join link is missing its invite code. Ask your instructor for a new link.'
    peekLoading.value = false
    return
  }
  try {
    const res = await peekInvite(token.value)
    if (res.state === 'invalid') {
      peekError.value = 'This invite link is invalid or has already been used.'
      return
    }
    peek.value = res as Peek
  } catch (e) {
    peekError.value = extractApiError(e, 'This invite link is invalid or has expired.')
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
  if (password.value.length < 8) {
    error.value = 'Password must be at least 8 characters.'
    return
  }
  pending.value = true
  try {
    await activate({ token: token.value, password: password.value })
    await navigateTo('/portal')
  } catch (e) {
    error.value = extractApiError(e, 'Could not activate your access.')
  } finally {
    pending.value = false
  }
}

onMounted(() => {
  void loadPeek()
})

watch(token, () => {
  void loadPeek()
})
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
  line-height: var(--leading-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.card__copy {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
}

.muted {
  font-size: var(--text-body-sm);
  opacity: 0.7;
}

.form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
  margin-top: var(--spacing-8);
}

.field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.field__label {
  font-size: var(--text-body-sm);
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
  gap: var(--spacing-8);
  min-height: 48px;
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  box-shadow: var(--shadow-button);
  cursor: pointer;
  text-decoration: none;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  align-items: flex-start;
}

.text-link {
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
  text-decoration: underline;
}
</style>
