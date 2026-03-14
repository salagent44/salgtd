import { describe, it, expect } from 'vitest'

/**
 * Tests for the weekly review checklist logic extracted from ReviewView.vue.
 *
 * The key bug: after completing a review, the checked state persisted because
 * Inertia's preserveState:true kept stale review_progress in page props.
 * The fix uses a justCompleted flag to skip stale props on reopen.
 */

const reviewStepKeys = [
  'collect', 'inbox', 'next-actions', 'projects',
  'waiting', 'someday', 'calendar', 'goals',
]

// Simulates loadProgress() from ReviewView.vue
function loadProgress(
  reviewProgressRaw: string | null,
  justCompleted: boolean,
): Record<string, boolean> {
  if (justCompleted) return {}
  if (!reviewProgressRaw) return {}
  try {
    const data = JSON.parse(reviewProgressRaw)
    return data.checked ?? {}
  } catch {
    return {}
  }
}

function allComplete(checked: Record<string, boolean>): boolean {
  return reviewStepKeys.every(k => checked[k])
}

function progressPercent(checked: Record<string, boolean>): number {
  const done = reviewStepKeys.filter(k => checked[k]).length
  return Math.round((done / reviewStepKeys.length) * 100)
}

describe('Review checklist loadProgress', () => {
  it('loads saved progress from JSON', () => {
    const raw = JSON.stringify({ checked: { collect: true, inbox: true } })
    const result = loadProgress(raw, false)
    expect(result).toEqual({ collect: true, inbox: true })
  })

  it('returns empty when no saved progress', () => {
    expect(loadProgress(null, false)).toEqual({})
  })

  it('returns empty on invalid JSON', () => {
    expect(loadProgress('not-json', false)).toEqual({})
  })

  it('returns empty when JSON has no checked key', () => {
    expect(loadProgress('{}', false)).toEqual({})
  })

  it('returns empty when justCompleted is true even if props have stale data', () => {
    const staleProgress = JSON.stringify({
      checked: { collect: true, inbox: true, 'next-actions': true, projects: true,
        waiting: true, someday: true, calendar: true, goals: true },
    })
    const result = loadProgress(staleProgress, true)
    expect(result).toEqual({})
  })

  it('loads normally after justCompleted is cleared', () => {
    const progress = JSON.stringify({ checked: { collect: true } })
    const result = loadProgress(progress, false)
    expect(result).toEqual({ collect: true })
  })
})

describe('Review completion state', () => {
  it('allComplete is false when no items checked', () => {
    expect(allComplete({})).toBe(false)
  })

  it('allComplete is false when partially checked', () => {
    expect(allComplete({ collect: true, inbox: true })).toBe(false)
  })

  it('allComplete is true when all 8 steps checked', () => {
    const checked: Record<string, boolean> = {}
    for (const key of reviewStepKeys) checked[key] = true
    expect(allComplete(checked)).toBe(true)
  })

  it('allComplete is false if one step unchecked', () => {
    const checked: Record<string, boolean> = {}
    for (const key of reviewStepKeys) checked[key] = true
    checked['goals'] = false
    expect(allComplete(checked)).toBe(false)
  })
})

describe('Review progress percentage', () => {
  it('0% when nothing checked', () => {
    expect(progressPercent({})).toBe(0)
  })

  it('100% when all checked', () => {
    const checked: Record<string, boolean> = {}
    for (const key of reviewStepKeys) checked[key] = true
    expect(progressPercent(checked)).toBe(100)
  })

  it('50% when 4 of 8 checked', () => {
    const checked: Record<string, boolean> = {}
    for (const key of reviewStepKeys.slice(0, 4)) checked[key] = true
    expect(progressPercent(checked)).toBe(50)
  })

  it('13% when 1 of 8 checked', () => {
    expect(progressPercent({ collect: true })).toBe(13)
  })
})

describe('Full review lifecycle', () => {
  it('start fresh → check all → complete → reopen shows clean slate', () => {
    // 1. Start fresh
    let checked = loadProgress(null, false)
    expect(checked).toEqual({})
    expect(allComplete(checked)).toBe(false)

    // 2. Check all items
    for (const key of reviewStepKeys) checked[key] = true
    expect(allComplete(checked)).toBe(true)

    // 3. Complete review — clear checked, set justCompleted
    checked = {}
    const savedProgress = null // server clears it
    const justCompleted = true

    // 4. Reopen — stale props may still have old data due to preserveState
    const staleProps = JSON.stringify({
      checked: Object.fromEntries(reviewStepKeys.map(k => [k, true])),
    })
    const reopened = loadProgress(staleProps, justCompleted)
    expect(reopened).toEqual({})
    expect(allComplete(reopened)).toBe(false)
    expect(progressPercent(reopened)).toBe(0)

    // 5. After justCompleted clears (user checks first item of new review)
    const freshStart = loadProgress(savedProgress, false)
    expect(freshStart).toEqual({})
  })
})
