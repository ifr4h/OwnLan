import { formatDuration, parseHm } from '~/utils/calendar/timeGrid'
import type { DiaryBlock } from '~/composables/useLessons'

/** Grid-friendly shape for private diary blocks (lavender stripe). */
export type DiaryBreak = {
  id: string
  date: string
  start_minutes: number
  end_minutes: number
  label: string
  duration_minutes?: number
  starts_at_local?: string
}

export function diaryBlockToBreak(block: DiaryBlock): DiaryBreak {
  const start = parseHm(block.starts_at_time || block.starts_at_local?.slice(11, 16) || '00:00')
  const end = block.ends_at_time
    ? parseHm(block.ends_at_time)
    : start + (block.duration_minutes || 60)
  return {
    id: String(block.id),
    date: block.date,
    start_minutes: start,
    end_minutes: end,
    label: block.label || 'Private',
    duration_minutes: block.duration_minutes,
    starts_at_local: block.starts_at_local,
  }
}

export function useDiaryBreaks() {
  function breaksFromDiaryBlocks(blocks: DiaryBlock[] | undefined | null): DiaryBreak[] {
    if (!blocks?.length) return []
    return blocks.map(diaryBlockToBreak)
  }

  function breaksForDates(blocks: DiaryBlock[] | undefined | null, dates: string[]): DiaryBreak[] {
    const set = new Set(dates)
    return breaksFromDiaryBlocks(blocks).filter(b => set.has(b.date))
  }

  async function createBlock(input: {
    starts_at_local: string
    duration_minutes: number
    label?: string
  }): Promise<DiaryBlock> {
    const starts = input.starts_at_local.length === 16
      ? `${input.starts_at_local}:00`
      : input.starts_at_local
    return await apiFetch<DiaryBlock>('/diary-blocks', {
      method: 'POST',
      body: {
        starts_at_local: starts,
        duration_minutes: Math.max(15, input.duration_minutes),
        label: (input.label || 'Private').trim() || 'Private',
      },
    })
  }

  async function updateBlock(
    id: number | string,
    payload: {
      starts_at_local?: string
      duration_minutes?: number
      label?: string
    },
  ): Promise<DiaryBlock> {
    const body: Record<string, unknown> = { ...payload }
    if (typeof body.starts_at_local === 'string' && body.starts_at_local.length === 16) {
      body.starts_at_local = `${body.starts_at_local}:00`
    }
    return await apiFetch<DiaryBlock>(`/diary-blocks/${id}`, {
      method: 'PUT',
      body,
    })
  }

  async function deleteBlock(id: number | string): Promise<void> {
    await apiFetch(`/diary-blocks/${id}`, { method: 'DELETE' })
  }

  function labelForBreak(b: DiaryBreak): string {
    return `${b.label} · ${formatDuration(b.end_minutes - b.start_minutes)}`
  }

  return {
    breaksFromDiaryBlocks,
    breaksForDates,
    createBlock,
    updateBlock,
    deleteBlock,
    labelForBreak,
  }
}
