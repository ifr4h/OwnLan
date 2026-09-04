<script setup lang="ts">
import type { DiaryLesson } from '~/composables/useLessons'
import type { DiaryOverviewModel } from '~/utils/diary/overviewModel'
import DiaryOverview from '~/components/calendar/overview/DiaryOverview.vue'
import DiaryLessonAppointment, {
  type InstructorBookingRequest,
  type SlotPickState,
} from '~/components/calendar/DiaryLessonAppointment.vue'

export type DiarySidePanelMode = 'welcome' | 'lesson' | 'request'

const props = defineProps<{
  mode: DiarySidePanelMode
  lesson: DiaryLesson | null
  request: InstructorBookingRequest | null
  overview: DiaryOverviewModel | null
  slotPick: SlotPickState
  flash: string | null
}>()

const emit = defineEmits<{
  close: []
  selectDay: [date: string]
  changed: [message?: string]
  startPickSlot: [kind: 'move' | 'suggest']
  cancelPickSlot: []
  confirmPickSlot: []
  bookNext: []
}>()

const appointmentRef = ref<InstanceType<typeof DiaryLessonAppointment> | null>(null)

defineExpose({
  applySuggest: (startsAtLocal: string) => appointmentRef.value?.applySuggest(startsAtLocal),
  applyMove: (startsAtLocal: string) => appointmentRef.value?.applyMove(startsAtLocal),
})

const showAppointment = computed(() =>
  (props.mode === 'lesson' && !!props.lesson)
  || (props.mode === 'request' && !!props.request),
)
</script>

<template>
  <aside
    class="panel"
    :data-mode="mode"
    :aria-label="mode === 'welcome' ? 'Diary overview' : (mode === 'request' ? 'Lesson request' : 'Lesson')"
  >
    <div v-if="mode === 'welcome'" class="panel__head">
      <p class="panel__eyebrow">Overview</p>
      <button class="panel__x" type="button" aria-label="Close panel" @click="emit('close')">
        <OlIcon name="close" :size="16" />
      </button>
    </div>

    <p v-if="flash" class="panel__flash" role="status">{{ flash }}</p>

    <div class="panel__body">
      <DiaryOverview
        v-if="mode === 'welcome' && overview"
        :model="overview"
        @select-day="emit('selectDay', $event)"
      />

      <template v-else-if="mode === 'welcome'">
        <p class="panel__quiet">Nothing to show for this period yet.</p>
      </template>

      <DiaryLessonAppointment
        v-else-if="showAppointment"
        ref="appointmentRef"
        :lesson="lesson"
        :request="request"
        :slot-pick="slotPick"
        @close="emit('close')"
        @changed="emit('changed', $event)"
        @start-pick-slot="emit('startPickSlot', $event)"
        @cancel-pick-slot="emit('cancelPickSlot')"
        @confirm-pick-slot="emit('confirmPickSlot')"
        @book-next="emit('bookNext')"
      />
    </div>
  </aside>
</template>

<style scoped>
.panel {
  display: flex;
  flex-direction: column;
  gap: 0;
  min-width: 0;
  min-height: 0;
  height: 100%;
  padding: 14px 16px 0;
  background: var(--color-parchment);
  color: var(--color-ink-black);
  outline: none;
  overflow: hidden;
}

.panel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex-shrink: 0;
  min-height: 32px;
  margin-bottom: 12px;
}

.panel__eyebrow {
  margin: 0;
  font-size: 10px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-weight: 600;
  color: var(--color-muted);
}

.panel__x {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
  margin-left: auto;
}

.panel__x:hover {
  background: rgba(255, 255, 255, 0.7);
  color: var(--color-ink-black);
}

.panel__flash {
  margin: 0 0 12px;
  padding: 8px 10px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--color-ownlane-green) 12%, white);
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
  font-weight: 600;
  flex-shrink: 0;
}

.panel__body {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.panel[data-mode='welcome'] .panel__body {
  overflow: auto;
  padding-bottom: 48px;
}

.panel__quiet {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}
</style>
