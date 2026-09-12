<script setup lang="ts">
import type { LearnerLocation, LocationIconName } from '~/composables/useLearnerLocations'
import { LOCATION_ICONS } from '~/composables/useLearnerLocations'
import LocationIcon from '~/components/locations/LocationIcon.vue'

const props = withDefaults(defineProps<{
  locations: LearnerLocation[]
  audience?: 'instructor' | 'learner'
  busy?: boolean
}>(), {
  audience: 'instructor',
  busy: false,
})

const emit = defineEmits<{
  create: [payload: { label: string; icon: LocationIconName; address: string; is_default?: boolean }]
  update: [id: number, payload: { label: string; icon: LocationIconName; address: string }]
  remove: [id: number]
  setDefault: [id: number]
}>()

const editingId = ref<number | null>(null)
const draftLabel = ref('')
const draftAddress = ref('')
const draftIcon = ref<LocationIconName>('pin')
const creating = ref(false)
const newLabel = ref('')
const newAddress = ref('')
const newIcon = ref<LocationIconName>('home')
const makeDefault = ref(false)

const shareHint = computed(() =>
  props.audience === 'learner'
    ? 'Shared with your instructor'
    : 'Visible to this pupil’s instructor',
)

function startEdit(loc: LearnerLocation) {
  editingId.value = loc.id
  draftLabel.value = loc.label
  draftAddress.value = loc.address
  draftIcon.value = (LOCATION_ICONS.includes(loc.icon as LocationIconName)
    ? loc.icon
    : 'pin') as LocationIconName
}

function cancelEdit() {
  editingId.value = null
}

function saveEdit() {
  if (editingId.value == null) return
  const label = draftLabel.value.trim()
  const address = draftAddress.value.trim()
  if (!label || !address) return
  emit('update', editingId.value, {
    label,
    icon: draftIcon.value,
    address,
  })
  editingId.value = null
}

function submitCreate() {
  const label = newLabel.value.trim()
  const address = newAddress.value.trim()
  if (!label || !address) return
  emit('create', {
    label,
    icon: newIcon.value,
    address,
    is_default: makeDefault.value || props.locations.length === 0,
  })
  newLabel.value = ''
  newAddress.value = ''
  newIcon.value = 'home'
  makeDefault.value = false
  creating.value = false
}
</script>

<template>
  <div class="pm">
    <p class="pm__hint">{{ shareHint }}</p>

    <ul v-if="locations.length" class="pm__list">
      <li v-for="loc in locations" :key="loc.id" class="pm__item">
        <template v-if="editingId === loc.id">
          <div class="pm__icons" role="group" aria-label="Icon">
            <button
              v-for="icon in LOCATION_ICONS"
              :key="icon"
              type="button"
              class="pm__icon-btn"
              :class="{ 'pm__icon-btn--on': draftIcon === icon }"
              :disabled="busy"
              @click="draftIcon = icon"
            >
              <LocationIcon :name="icon" :size="16" />
            </button>
          </div>
          <input v-model="draftLabel" class="ol-input" type="text" maxlength="64" :disabled="busy">
          <textarea v-model="draftAddress" class="ol-textarea" rows="2" :disabled="busy" />
          <div class="pm__row-actions">
            <button class="ol-btn ol-btn--sm" type="button" :disabled="busy" @click="saveEdit">
              Save
            </button>
            <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="busy" @click="cancelEdit">
              Cancel
            </button>
          </div>
        </template>
        <template v-else>
          <div class="pm__row">
            <span class="pm__icon-wrap" aria-hidden="true">
              <LocationIcon :name="loc.icon" :size="18" />
            </span>
            <div class="pm__text">
              <p class="pm__label">
                {{ loc.label }}
                <span v-if="loc.is_default" class="pm__default">Usual</span>
              </p>
              <p class="pm__meta">{{ loc.usage_label || 'Pickup & drop-off' }}</p>
              <p class="pm__addr">{{ loc.address }}</p>
            </div>
          </div>
          <div class="pm__row-actions">
            <button
              v-if="!loc.is_default"
              class="pm__link"
              type="button"
              :disabled="busy"
              @click="emit('setDefault', loc.id)"
            >
              Make usual
            </button>
            <button class="pm__link" type="button" :disabled="busy" @click="startEdit(loc)">
              Edit
            </button>
            <button class="pm__link pm__link--danger" type="button" :disabled="busy" @click="emit('remove', loc.id)">
              Remove
            </button>
          </div>
        </template>
      </li>
    </ul>
    <p v-else class="pm__empty">No places saved yet.</p>

    <button
      v-if="!creating"
      class="pm__add-open"
      type="button"
      :disabled="busy"
      @click="creating = true"
    >
      Add a place
    </button>
    <div v-else class="pm__create">
      <p class="ol-field__label">New place</p>
      <div class="pm__icons" role="group" aria-label="Icon">
        <button
          v-for="icon in LOCATION_ICONS"
          :key="icon"
          type="button"
          class="pm__icon-btn"
          :class="{ 'pm__icon-btn--on': newIcon === icon }"
          :disabled="busy"
          @click="newIcon = icon"
        >
          <LocationIcon :name="icon" :size="16" />
        </button>
      </div>
      <input
        v-model="newLabel"
        class="ol-input"
        type="text"
        maxlength="64"
        placeholder="Label (Home, Work…)"
        :disabled="busy"
      >
      <textarea
        v-model="newAddress"
        class="ol-textarea"
        rows="2"
        placeholder="Full address"
        :disabled="busy"
      />
      <label class="pm__check">
        <input v-model="makeDefault" type="checkbox" :disabled="busy">
        Usual pickup
      </label>
      <div class="pm__row-actions">
        <button
          class="ol-btn ol-btn--sm"
          type="button"
          :disabled="busy || !newLabel.trim() || !newAddress.trim()"
          @click="submitCreate"
        >
          Save place
        </button>
        <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="busy" @click="creating = false">
          Cancel
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pm {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pm__hint {
  margin: 0;
  font-size: 12px;
  color: var(--color-muted);
}

.pm__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pm__item {
  padding: 12px;
  border-radius: var(--radius-small);
  border: 1px solid var(--color-border);
  background: var(--color-parchment);
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pm__row {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.pm__icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: var(--color-paper-white);
  color: var(--color-bark);
  flex-shrink: 0;
}

.pm__text {
  min-width: 0;
}

.pm__label {
  margin: 0;
  font-size: var(--text-body-sm);
  font-weight: 600;
  color: var(--color-ink-black);
}

.pm__meta {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--color-muted);
}

.pm__default {
  margin-left: 6px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-ownlane-green);
}

.pm__addr {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--color-muted);
  line-height: 1.35;
}

.pm__row-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.pm__link {
  border: none;
  background: transparent;
  color: var(--color-ownlane-green);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}

.pm__link--danger {
  color: var(--color-danger);
}

.pm__empty {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.pm__add-open {
  align-self: flex-start;
  border: none;
  background: transparent;
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}

.pm__create {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border-radius: var(--radius-small);
  border: 1px solid var(--color-border);
  background: var(--color-parchment);
}

.pm__icons {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.pm__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  background: var(--color-paper-white);
  color: var(--color-bark);
  cursor: pointer;
}

.pm__icon-btn--on {
  border-color: var(--color-ownlane-green);
  color: var(--color-ownlane-green);
  background: color-mix(in srgb, var(--color-ownlane-green) 10%, var(--color-paper-white));
}

.pm__check {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: var(--text-body-sm);
  color: var(--color-bark);
}
</style>
