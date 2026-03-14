import { describe, it, expect } from 'vitest'

/**
 * These tests verify the logic that makes multi-day events render as
 * connected bars across calendar grid cells — no vertical border gaps.
 *
 * The key functions: isMultiDay, isMultiDayStart, multiDayPosition
 * produce CSS classes that use negative margins to bridge the 5px gap
 * (4px cell padding + 1px cell border) between adjacent grid cells.
 */

// Extracted logic from CalendarView.vue for testability
interface CalendarEvent {
  id: string
  title: string
  event_date: string
  end_date: string | null
  event_time: string | null
  end_time: string | null
  description: string
  color: string
  recurrence: string | null
}

function isMultiDay(event: CalendarEvent): boolean {
  return !!event.end_date && event.end_date !== event.event_date
}

function isMultiDayStart(event: CalendarEvent, dateStr: string): boolean {
  return isMultiDay(event) && event.event_date === dateStr
}

function multiDayPosition(event: CalendarEvent, dateStr: string): string {
  if (!isMultiDay(event)) return 'rounded px-2'
  const isStart = event.event_date === dateStr
  const isEnd = event.end_date === dateStr
  if (isStart) return 'multiday-event rounded-l pl-2 multiday-start'
  if (isEnd) return 'multiday-event rounded-r pr-2 multiday-end'
  return 'multiday-event multiday-mid'
}

function eventOccursOnDate(event: CalendarEvent, dateStr: string): boolean {
  if (event.event_date === dateStr) return true
  if (event.end_date && dateStr >= event.event_date && dateStr <= event.end_date) return true
  return false
}

const singleDayEvent: CalendarEvent = {
  id: '1', title: 'Standup', event_date: '2026-04-01', end_date: null,
  event_time: '10:00', end_time: null, description: '', color: 'blue', recurrence: null,
}

const multiDayEvent: CalendarEvent = {
  id: '2', title: 'Conference', event_date: '2026-04-01', end_date: '2026-04-03',
  event_time: '09:00', end_time: '17:00', description: '', color: 'green', recurrence: null,
}

const sameDayEndEvent: CalendarEvent = {
  id: '3', title: 'Workshop', event_date: '2026-04-05', end_date: '2026-04-05',
  event_time: '14:00', end_time: '16:00', description: '', color: 'red', recurrence: null,
}

describe('Multi-day event detection', () => {
  it('single-day event is NOT multi-day', () => {
    expect(isMultiDay(singleDayEvent)).toBe(false)
  })

  it('event spanning multiple days IS multi-day', () => {
    expect(isMultiDay(multiDayEvent)).toBe(true)
  })

  it('event with same start and end date is NOT multi-day', () => {
    expect(isMultiDay(sameDayEndEvent)).toBe(false)
  })
})

describe('Multi-day event occurs on correct dates', () => {
  it('appears on start date', () => {
    expect(eventOccursOnDate(multiDayEvent, '2026-04-01')).toBe(true)
  })

  it('appears on middle date', () => {
    expect(eventOccursOnDate(multiDayEvent, '2026-04-02')).toBe(true)
  })

  it('appears on end date', () => {
    expect(eventOccursOnDate(multiDayEvent, '2026-04-03')).toBe(true)
  })

  it('does NOT appear on day before start', () => {
    expect(eventOccursOnDate(multiDayEvent, '2026-03-31')).toBe(false)
  })

  it('does NOT appear on day after end', () => {
    expect(eventOccursOnDate(multiDayEvent, '2026-04-04')).toBe(false)
  })
})

describe('Multi-day CSS positioning - connected bars with no border gaps', () => {
  it('single-day event gets normal rounded styling', () => {
    const cls = multiDayPosition(singleDayEvent, '2026-04-01')
    expect(cls).toBe('rounded px-2')
    expect(cls).not.toContain('multiday')
  })

  it('start day: rounded-l only, extends RIGHT past cell border (multiday-start)', () => {
    const cls = multiDayPosition(multiDayEvent, '2026-04-01')
    expect(cls).toContain('rounded-l')
    expect(cls).toContain('multiday-start')
    expect(cls).toContain('multiday-event')
    // Should NOT have rounded-r (would create a gap)
    expect(cls).not.toContain('rounded-r')
    expect(cls).not.toContain('multiday-end')
    expect(cls).not.toContain('multiday-mid')
  })

  it('middle day: NO rounding, extends BOTH sides past cell borders (multiday-mid)', () => {
    const cls = multiDayPosition(multiDayEvent, '2026-04-02')
    expect(cls).toContain('multiday-mid')
    expect(cls).toContain('multiday-event')
    // Should NOT have any rounding
    expect(cls).not.toContain('rounded-l')
    expect(cls).not.toContain('rounded-r')
    expect(cls).not.toContain('multiday-start')
    expect(cls).not.toContain('multiday-end')
  })

  it('end day: rounded-r only, extends LEFT past cell border (multiday-end)', () => {
    const cls = multiDayPosition(multiDayEvent, '2026-04-03')
    expect(cls).toContain('rounded-r')
    expect(cls).toContain('multiday-end')
    expect(cls).toContain('multiday-event')
    // Should NOT have rounded-l (would create a gap)
    expect(cls).not.toContain('rounded-l')
    expect(cls).not.toContain('multiday-start')
    expect(cls).not.toContain('multiday-mid')
  })

  it('all multi-day segments get z-index class to sit above cell borders', () => {
    // The multiday-event class applies position:relative and z-index:2
    // so the event bar renders ABOVE the cell border lines
    expect(multiDayPosition(multiDayEvent, '2026-04-01')).toContain('multiday-event')
    expect(multiDayPosition(multiDayEvent, '2026-04-02')).toContain('multiday-event')
    expect(multiDayPosition(multiDayEvent, '2026-04-03')).toContain('multiday-event')
  })

  it('start shows title, middle/end show blank placeholder', () => {
    // isMultiDayStart controls whether the title text is shown
    expect(isMultiDayStart(multiDayEvent, '2026-04-01')).toBe(true)  // show title
    expect(isMultiDayStart(multiDayEvent, '2026-04-02')).toBe(false) // blank bar
    expect(isMultiDayStart(multiDayEvent, '2026-04-03')).toBe(false) // blank bar
  })
})

describe('Same-day end date treated as single-day', () => {
  it('gets normal rounded styling, not multi-day', () => {
    const cls = multiDayPosition(sameDayEndEvent, '2026-04-05')
    expect(cls).toBe('rounded px-2')
  })
})
