import type { TodayLesson, TodayResponse } from '~/composables/useToday'
import type { Lesson } from '~/composables/useLessons'
import type { Pupil } from '~/composables/usePupils'
import * as db from './db'
import type { CompleteLessonPayload, OutboxItem } from './types'
import { newMutationId } from './types'

export async function cacheTodaySnapshot(
  scope: string,
  today: TodayResponse,
  pupils: Pupil[],
): Promise<void> {
  const cachedAt = new Date().toISOString()
  await db.putToday({ scopeKey: scope, cachedAt, data: today })

  for (const lesson of today.lessons) {
    await db.putLesson({
      scopeKey: scope,
      id: lesson.id,
      cachedAt,
      data: lessonAsDetail(lesson),
    })
  }

  for (const pupil of pupils) {
    await db.putPupil({
      scopeKey: scope,
      id: pupil.id,
      cachedAt,
      data: pupil,
    })
  }
}

export async function readCachedToday(scope: string): Promise<TodayResponse | null> {
  const row = await db.getToday(scope)
  return (row?.data as TodayResponse | undefined) ?? null
}

export async function readCachedLesson(scope: string, id: number): Promise<Lesson | null> {
  const row = await db.getLesson(scope, id)
  return (row?.data as Lesson | undefined) ?? null
}

export async function readCachedPupil(scope: string, id: number): Promise<Pupil | null> {
  const row = await db.getPupil(scope, id)
  return (row?.data as Pupil | undefined) ?? null
}

export async function enqueueCompleteLesson(
  scope: string,
  payload: CompleteLessonPayload,
): Promise<OutboxItem> {
  const now = new Date().toISOString()
  const item: OutboxItem = {
    id: newMutationId(),
    scopeKey: scope,
    type: 'complete_lesson',
    status: 'pending',
    createdAt: now,
    updatedAt: now,
    attempts: 0,
    lastError: null,
    payload,
  }
  await db.putOutboxItem(item)
  await applyCompleteLocally(scope, item)
  return item
}

/** Immediate UI + cache update before server sync. */
export async function applyCompleteLocally(scope: string, item: OutboxItem): Promise<void> {
  const { lessonId, instructor_notes, learner_summary, next_focus } = item.payload
  const now = new Date().toISOString()

  const existing = await readCachedLesson(scope, lessonId)
  if (existing) {
    const updated: Lesson = {
      ...existing,
      status: 'completed',
      completed_at: now,
      instructor_notes: instructor_notes ?? existing.instructor_notes ?? null,
      learner_summary: learner_summary ?? existing.learner_summary ?? null,
      next_focus: next_focus ?? existing.next_focus ?? null,
      client_mutation_id: item.id,
    }
    await db.putLesson({
      scopeKey: scope,
      id: lessonId,
      cachedAt: now,
      data: updated,
      localOnly: true,
    })
  }

  const today = await readCachedToday(scope)
  if (!today) return

  const patchLesson = (lesson: TodayLesson): TodayLesson => {
    if (lesson.id !== lessonId) return lesson
    return {
      ...lesson,
      status: 'completed',
      can_complete: false,
      is_current: false,
      is_next: false,
      instructor_notes: instructor_notes ?? lesson.instructor_notes ?? null,
      learner_summary: learner_summary ?? lesson.learner_summary ?? null,
      next_focus: next_focus ?? lesson.next_focus ?? null,
      learner_next_focus: next_focus ?? lesson.learner_next_focus ?? null,
      learner_last_lesson_summary: learner_summary ?? lesson.learner_last_lesson_summary ?? null,
    }
  }

  const lessons = today.lessons.map(patchLesson)
  const scheduled = lessons.filter(l => l.status === 'scheduled')

  let focus: TodayLesson | null = null
  const current = scheduled.find(l => l.is_current)
  if (current) {
    focus = current
  } else if (scheduled[0]) {
    focus = { ...scheduled[0], is_next: true, is_current: false }
  }

  const remaining = focus
    ? scheduled.filter(l => l.id !== focus!.id)
    : []

  const updatedToday: TodayResponse = {
    ...today,
    focus,
    remaining,
    lessons,
    needs_attention: today.needs_attention ?? [],
  }

  await db.putToday({ scopeKey: scope, cachedAt: now, data: updatedToday })

  if (existing?.learner_id) {
    const pupil = await readCachedPupil(scope, existing.learner_id)
    if (pupil) {
      await db.putPupil({
        scopeKey: scope,
        id: pupil.id,
        cachedAt: now,
        data: {
          ...pupil,
          next_focus: next_focus ?? pupil.next_focus ?? null,
          last_lesson_summary: learner_summary ?? pupil.last_lesson_summary ?? null,
        },
      })
    }
  }
}

function lessonAsDetail(lesson: TodayLesson): Lesson {
  return {
    id: lesson.id,
    learner_id: lesson.learner_id,
    instructor_id: 0,
    learner_name: lesson.learner_name,
    starts_at: lesson.starts_at,
    starts_at_local: lesson.starts_at_local,
    starts_at_display: lesson.starts_at_display,
    timezone: lesson.timezone,
    duration_minutes: lesson.duration_minutes,
    pickup_address: lesson.pickup_address,
    status: lesson.status,
    instructor_notes: lesson.instructor_notes ?? null,
    learner_summary: lesson.learner_summary ?? null,
    next_focus: lesson.next_focus ?? null,
    client_mutation_id: null,
    cancelled_at: null,
    completed_at: lesson.status === 'completed' ? lesson.starts_at : null,
  }
}
