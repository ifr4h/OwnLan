<template>
  <section class="page">
    <NuxtLink to="/pupils/new" class="back">← Add a pupil</NuxtLink>

    <header class="page__header">
      <p class="page__eyebrow">Pupil link</p>
      <h1 class="page__title">Let them fill their details</h1>
      <p class="page__copy">
        Optional extras help them feel expected — everything can stay blank.
      </p>
    </header>

    <template v-if="!invite">
      <form class="form" @submit.prevent="onCreate">
        <label class="field">
          <span class="field__label">
            First name
            <span class="field__optional">optional</span>
          </span>
          <input
            v-model="firstName"
            class="field__input"
            type="text"
            autocomplete="given-name"
            autofocus
          >
        </label>

        <label class="field">
          <span class="field__label">
            Mobile
            <span class="field__optional">optional</span>
          </span>
          <input
            v-model="mobile"
            class="field__input"
            type="tel"
            inputmode="tel"
            autocomplete="tel"
            placeholder="07…"
          >
        </label>

        <label class="field">
          <span class="field__label">
            Email
            <span class="field__optional">optional</span>
          </span>
          <input
            v-model="email"
            class="field__input"
            type="email"
            autocomplete="email"
          >
        </label>

        <p class="hint">Add mobile or email if you want — or skip and just create the link.</p>

        <p v-if="error" class="error" role="alert">{{ error }}</p>

        <button class="btn" type="submit" :disabled="pending">
          {{ pending ? 'Creating…' : 'Create link' }}
          <span aria-hidden="true">→</span>
        </button>
      </form>
    </template>

    <template v-else>
      <div class="share">
        <p class="share__message">{{ invite.message }}</p>
        <div class="share__url-wrap">
          <p class="share__url">{{ shareUrl }}</p>
        </div>
        <div class="share__actions">
          <button class="btn" type="button" @click="onCopy">
            {{ copied ? 'Copied' : 'Copy link' }}
          </button>
          <button
            v-if="canShare"
            class="btn btn--ghost"
            type="button"
            @click="onShare"
          >
            Share
          </button>
        </div>
        <p class="hint">
          Link expires
          {{ formatExpiry(invite.invite_expires_at) }}.
        </p>
        <div class="share__done">
          <NuxtLink to="/pupils/intake" class="btn btn--ghost">View open links</NuxtLink>
          <NuxtLink to="/pupils" class="text-link">Done · back to pupils</NuxtLink>
        </div>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { IntakeInviteResult } from '~/composables/useIntake'

useHead({ title: 'Pupil link · OwnLane' })

const { createIntake } = useIntake()

const firstName = ref('')
const mobile = ref('')
const email = ref('')
const pending = ref(false)
const error = ref('')
const invite = ref<IntakeInviteResult | null>(null)
const copied = ref(false)
const canShare = ref(false)

const shareUrl = computed(() => {
  if (!invite.value) return ''
  if (import.meta.client) {
    return `${window.location.origin}${invite.value.invite_path}`
  }
  return invite.value.invite_path
})

function formatExpiry(iso: string): string {
  const d = new Date(iso.endsWith('Z') ? iso : `${iso}Z`)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

async function onCreate() {
  pending.value = true
  error.value = ''
  try {
    invite.value = await createIntake({
      first_name: firstName.value.trim() || undefined,
      mobile: mobile.value.trim() || undefined,
      email: email.value.trim() || undefined,
    })
  } catch (e) {
    error.value = extractApiError(e, 'Could not create this link.')
  } finally {
    pending.value = false
  }
}

async function onCopy() {
  if (!shareUrl.value) return
  try {
    await navigator.clipboard.writeText(shareUrl.value)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    error.value = 'Could not copy — select the link instead.'
  }
}

async function onShare() {
  if (!shareUrl.value || !navigator.share) return
  try {
    await navigator.share({
      title: 'Driving lessons details',
      text: 'Fill in a few details for your instructor.',
      url: shareUrl.value,
    })
  } catch {
    // User cancelled share sheet — ignore.
  }
}

onMounted(() => {
  canShare.value = typeof navigator !== 'undefined' && typeof navigator.share === 'function'
})
</script>

<style scoped>
.page {
  max-width: 560px;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-20);
}

.back {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  align-self: flex-start;
}

.page__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
  margin-bottom: var(--spacing-8);
}

.page__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  line-height: var(--leading-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.page__copy {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  max-width: 40ch;
  opacity: 0.8;
}

.form,
.share {
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--card-padding);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.field__label {
  font-size: var(--text-body-sm);
  display: flex;
  align-items: baseline;
  gap: var(--spacing-8);
}

.field__optional {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.55;
}

.field__input {
  width: 100%;
  min-height: 48px;
  padding: 12px 20px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-inputs);
  background: var(--surface-canvas);
  font: inherit;
}

.field__input:focus {
  outline: 2px solid var(--color-ownlane-green);
  outline-offset: 2px;
}

.hint {
  font-size: var(--text-body-sm);
  opacity: 0.65;
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
  align-self: flex-start;
  font: inherit;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn--ghost {
  background: transparent;
  color: var(--color-ink-black);
  box-shadow: none;
  border: 1px solid var(--color-frost-green);
}

.share__message {
  font-size: var(--text-body-sm);
}

.share__url-wrap {
  background: var(--color-chalk-green);
  border-radius: var(--radius-small);
  padding: var(--spacing-16);
  word-break: break-all;
}

.share__url {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
}

.share__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.share__done {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--spacing-12);
  padding-top: var(--spacing-8);
  border-top: 1px solid var(--color-frost-green);
}

.text-link {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
}

.error {
  color: var(--color-marker-red);
  font-size: var(--text-body-sm);
}
</style>
