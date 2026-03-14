import { describe, it, expect } from 'vitest'

/**
 * Tests for escape key behavior across ALL modals and sub-views.
 *
 * Two escape systems exist:
 * 1. Global onKeydown handler — handles top-level modals (search, settings, etc.)
 * 2. guardDialogDismiss — handles sub-views inside the clarify/edit dialog
 *
 * The logic is extracted from GtdInbox.vue to test priority ordering.
 */

// ─── Global escape handler state ───

interface GlobalState {
  emailViewerOpen: boolean
  selectedIds: number
  exportModalOpen: boolean
  processingInbox: boolean
  quickCapture: boolean
  quickNextAction: boolean
  quickWaiting: boolean
  searchOpen: boolean
  settingsOpen: boolean
  hotkeysOpen: boolean
  dialogOpen: boolean
  activePill: string
  activeContextFilter: string | null
  activeTagFilter: string | null
}

function defaultGlobalState(): GlobalState {
  return {
    emailViewerOpen: false,
    selectedIds: 0,
    exportModalOpen: false,
    processingInbox: false,
    quickCapture: false,
    quickNextAction: false,
    quickWaiting: false,
    searchOpen: false,
    settingsOpen: false,
    hotkeysOpen: false,
    dialogOpen: false,
    activePill: 'next-actions',
    activeContextFilter: null,
    activeTagFilter: null,
  }
}

/**
 * Simulates the global escape handler from GtdInbox.vue onKeydown.
 * Returns the name of the state that was closed, or null if nothing happened.
 */
function handleGlobalEscape(state: GlobalState): string | null {
  if (state.emailViewerOpen) { state.emailViewerOpen = false; return 'emailViewer' }
  if (state.selectedIds > 0) { state.selectedIds = 0; return 'selectedIds' }
  if (state.exportModalOpen) { state.exportModalOpen = false; return 'exportModal' }
  if (state.processingInbox) { state.processingInbox = false; return 'processingInbox' }
  if (state.quickCapture) { state.quickCapture = false; return 'quickCapture' }
  if (state.quickNextAction) { state.quickNextAction = false; return 'quickNextAction' }
  if (state.quickWaiting) { state.quickWaiting = false; return 'quickWaiting' }
  if (state.searchOpen) { state.searchOpen = false; return 'searchOpen' }
  if (state.settingsOpen) { state.settingsOpen = false; return 'settingsOpen' }
  if (state.hotkeysOpen) { state.hotkeysOpen = false; return 'hotkeysOpen' }
  if (state.dialogOpen) { return 'dialogOpen-deferred' } // defers to dialog's own handler
  if (state.activePill !== 'next-actions' || state.activeContextFilter !== null || state.activeTagFilter !== null) {
    state.activePill = 'next-actions'
    state.activeContextFilter = null
    state.activeTagFilter = null
    return 'activePill'
  }
  return null
}

// ─── Dialog escape handler state ───

interface DialogSubState {
  emailViewerOpen: boolean
  pickingProject: boolean
  pickingProjectGoal: boolean
  pickingContext: boolean
  pickingWaiting: boolean
  pickingTickler: boolean
  pickingEvent: boolean
}

function defaultDialogSubState(): DialogSubState {
  return {
    emailViewerOpen: false,
    pickingProject: false,
    pickingProjectGoal: false,
    pickingContext: false,
    pickingWaiting: false,
    pickingTickler: false,
    pickingEvent: false,
  }
}

/**
 * Simulates guardDialogDismiss from GtdInbox.vue.
 * Returns: 'prevented' if escape was blocked (sub-view still open or overlay active),
 *          the name of the sub-view that was closed, or 'close-dialog' if dialog should close.
 */
function handleDialogEscape(state: DialogSubState): string {
  if (state.emailViewerOpen || state.pickingProject) { return 'prevented' }
  if (state.pickingProjectGoal) { state.pickingProjectGoal = false; return 'pickingProjectGoal' }
  if (state.pickingContext) { state.pickingContext = false; return 'pickingContext' }
  if (state.pickingWaiting) { state.pickingWaiting = false; return 'pickingWaiting' }
  if (state.pickingTickler) { state.pickingTickler = false; return 'pickingTickler' }
  if (state.pickingEvent) { state.pickingEvent = false; return 'pickingEvent' }
  return 'close-dialog'
}

// ─── Global escape tests ───

describe('Global escape key priority', () => {
  it('emailViewer takes top priority', () => {
    const state = { ...defaultGlobalState(), emailViewerOpen: true, searchOpen: true, settingsOpen: true }
    expect(handleGlobalEscape(state)).toBe('emailViewer')
    expect(state.emailViewerOpen).toBe(false)
    expect(state.searchOpen).toBe(true)
    expect(state.settingsOpen).toBe(true)
  })

  it('selectedIds cleared before modals', () => {
    const state = { ...defaultGlobalState(), selectedIds: 3, exportModalOpen: true }
    expect(handleGlobalEscape(state)).toBe('selectedIds')
    expect(state.selectedIds).toBe(0)
    expect(state.exportModalOpen).toBe(true)
  })

  it('exportModal closes', () => {
    const state = { ...defaultGlobalState(), exportModalOpen: true }
    expect(handleGlobalEscape(state)).toBe('exportModal')
    expect(state.exportModalOpen).toBe(false)
  })

  it('processingInbox closes', () => {
    const state = { ...defaultGlobalState(), processingInbox: true }
    expect(handleGlobalEscape(state)).toBe('processingInbox')
    expect(state.processingInbox).toBe(false)
  })

  it('quickCapture closes', () => {
    const state = { ...defaultGlobalState(), quickCapture: true }
    expect(handleGlobalEscape(state)).toBe('quickCapture')
    expect(state.quickCapture).toBe(false)
  })

  it('quickNextAction closes', () => {
    const state = { ...defaultGlobalState(), quickNextAction: true }
    expect(handleGlobalEscape(state)).toBe('quickNextAction')
    expect(state.quickNextAction).toBe(false)
  })

  it('quickWaiting closes', () => {
    const state = { ...defaultGlobalState(), quickWaiting: true }
    expect(handleGlobalEscape(state)).toBe('quickWaiting')
    expect(state.quickWaiting).toBe(false)
  })

  it('searchOpen closes', () => {
    const state = { ...defaultGlobalState(), searchOpen: true }
    expect(handleGlobalEscape(state)).toBe('searchOpen')
    expect(state.searchOpen).toBe(false)
  })

  it('settingsOpen closes', () => {
    const state = { ...defaultGlobalState(), settingsOpen: true }
    expect(handleGlobalEscape(state)).toBe('settingsOpen')
    expect(state.settingsOpen).toBe(false)
  })

  it('hotkeysOpen closes', () => {
    const state = { ...defaultGlobalState(), hotkeysOpen: true }
    expect(handleGlobalEscape(state)).toBe('hotkeysOpen')
    expect(state.hotkeysOpen).toBe(false)
  })

  it('dialogOpen defers to dialog handler', () => {
    const state = { ...defaultGlobalState(), dialogOpen: true }
    expect(handleGlobalEscape(state)).toBe('dialogOpen-deferred')
    expect(state.dialogOpen).toBe(true) // not closed by global handler
  })

  it('non-default pill resets to next-actions', () => {
    const state = { ...defaultGlobalState(), activePill: 'projects' }
    expect(handleGlobalEscape(state)).toBe('activePill')
    expect(state.activePill).toBe('next-actions')
  })

  it('active context filter resets', () => {
    const state = { ...defaultGlobalState(), activeContextFilter: '@phone' }
    expect(handleGlobalEscape(state)).toBe('activePill')
    expect(state.activeContextFilter).toBeNull()
  })

  it('active tag filter resets', () => {
    const state = { ...defaultGlobalState(), activeTagFilter: 'work' }
    expect(handleGlobalEscape(state)).toBe('activePill')
    expect(state.activeTagFilter).toBeNull()
  })

  it('no modals, default pill — escape does nothing', () => {
    const state = defaultGlobalState()
    expect(handleGlobalEscape(state)).toBeNull()
  })

  it('priority order: each modal only closes when higher-priority modals are closed', () => {
    const allOpen: GlobalState = {
      emailViewerOpen: true,
      selectedIds: 5,
      exportModalOpen: true,
      processingInbox: true,
      quickCapture: true,
      quickNextAction: true,
      quickWaiting: true,
      searchOpen: true,
      settingsOpen: true,
      hotkeysOpen: true,
      dialogOpen: true,
      activePill: 'inbox',
      activeContextFilter: '@phone',
      activeTagFilter: 'work',
    }

    const expectedOrder = [
      'emailViewer', 'selectedIds', 'exportModal', 'processingInbox',
      'quickCapture', 'quickNextAction', 'quickWaiting', 'searchOpen',
      'settingsOpen', 'hotkeysOpen', 'dialogOpen-deferred',
    ]

    const state = { ...allOpen }
    for (const expected of expectedOrder) {
      const result = handleGlobalEscape(state)
      expect(result).toBe(expected)
    }
  })
})

// ─── Dialog sub-view escape tests ───

describe('Dialog escape (guardDialogDismiss)', () => {
  it('emailViewer open — prevents close entirely', () => {
    const state = { ...defaultDialogSubState(), emailViewerOpen: true }
    expect(handleDialogEscape(state)).toBe('prevented')
    expect(state.emailViewerOpen).toBe(true) // not closed by this handler
  })

  it('pickingProject open — prevents close (project picker has its own esc)', () => {
    const state = { ...defaultDialogSubState(), pickingProject: true }
    expect(handleDialogEscape(state)).toBe('prevented')
    expect(state.pickingProject).toBe(true) // closed by project picker itself
  })

  it('pickingProjectGoal closes back to main bucket picker', () => {
    const state = { ...defaultDialogSubState(), pickingProjectGoal: true }
    expect(handleDialogEscape(state)).toBe('pickingProjectGoal')
    expect(state.pickingProjectGoal).toBe(false)
  })

  it('pickingContext closes back to main bucket picker', () => {
    const state = { ...defaultDialogSubState(), pickingContext: true }
    expect(handleDialogEscape(state)).toBe('pickingContext')
    expect(state.pickingContext).toBe(false)
  })

  it('pickingWaiting closes back to main bucket picker', () => {
    const state = { ...defaultDialogSubState(), pickingWaiting: true }
    expect(handleDialogEscape(state)).toBe('pickingWaiting')
    expect(state.pickingWaiting).toBe(false)
  })

  it('pickingTickler closes back to main bucket picker', () => {
    const state = { ...defaultDialogSubState(), pickingTickler: true }
    expect(handleDialogEscape(state)).toBe('pickingTickler')
    expect(state.pickingTickler).toBe(false)
  })

  it('pickingEvent closes back to main bucket picker', () => {
    const state = { ...defaultDialogSubState(), pickingEvent: true }
    expect(handleDialogEscape(state)).toBe('pickingEvent')
    expect(state.pickingEvent).toBe(false)
  })

  it('no sub-views open — allows dialog to close', () => {
    const state = defaultDialogSubState()
    expect(handleDialogEscape(state)).toBe('close-dialog')
  })

  it('multi-press: project goal → main → close dialog', () => {
    const state = { ...defaultDialogSubState(), pickingProjectGoal: true }
    expect(handleDialogEscape(state)).toBe('pickingProjectGoal')
    expect(state.pickingProjectGoal).toBe(false)
    expect(handleDialogEscape(state)).toBe('close-dialog')
  })

  it('multi-press: context → main → close dialog', () => {
    const state = { ...defaultDialogSubState(), pickingContext: true }
    expect(handleDialogEscape(state)).toBe('pickingContext')
    expect(handleDialogEscape(state)).toBe('close-dialog')
  })

  it('priority: emailViewer blocks even with sub-views open', () => {
    const state: DialogSubState = {
      emailViewerOpen: true,
      pickingProject: true,
      pickingProjectGoal: true,
      pickingContext: true,
      pickingWaiting: true,
      pickingTickler: true,
      pickingEvent: true,
    }
    expect(handleDialogEscape(state)).toBe('prevented')
  })
})
