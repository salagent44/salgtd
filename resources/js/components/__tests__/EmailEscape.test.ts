import { describe, it, expect } from 'vitest'

/**
 * Tests for the escape key priority logic in the email viewer modal.
 *
 * When the email viewer is open on top of the clarify dialog, pressing Escape
 * should ONLY close the email viewer. The clarify dialog should remain open.
 * A second Escape press then closes the clarify dialog.
 *
 * The logic is extracted from the onKeydown handler in GtdInbox.vue.
 */

interface ModalState {
  emailViewerOpen: boolean
  selectedIds: number
  exportModalOpen: boolean
  processingInbox: boolean
  quickCapture: boolean
  quickNextAction: boolean
  quickWaiting: boolean
  searchOpen: boolean
  reviewLanding: boolean
  reviewOpen: boolean
  settingsOpen: boolean
  dialogOpen: boolean
  activePill: string
}

function defaultState(): ModalState {
  return {
    emailViewerOpen: false,
    selectedIds: 0,
    exportModalOpen: false,
    processingInbox: false,
    quickCapture: false,
    quickNextAction: false,
    quickWaiting: false,
    searchOpen: false,
    reviewLanding: false,
    reviewOpen: false,
    settingsOpen: false,
    dialogOpen: false,
    activePill: 'clarified',
  }
}

/**
 * Simulates the escape key handler logic from GtdInbox.vue.
 * Returns the name of the modal/state that was closed, or null if nothing happened.
 */
function handleEscape(state: ModalState): string | null {
  if (state.emailViewerOpen) { state.emailViewerOpen = false; return 'emailViewer' }
  if (state.selectedIds > 0) { state.selectedIds = 0; return 'selectedIds' }
  if (state.exportModalOpen) { state.exportModalOpen = false; return 'exportModal' }
  if (state.processingInbox) { state.processingInbox = false; return 'processingInbox' }
  if (state.quickCapture) { state.quickCapture = false; return 'quickCapture' }
  if (state.quickNextAction) { state.quickNextAction = false; return 'quickNextAction' }
  if (state.quickWaiting) { state.quickWaiting = false; return 'quickWaiting' }
  if (state.searchOpen) { state.searchOpen = false; return 'searchOpen' }
  if (state.reviewLanding) { state.reviewLanding = false; return 'reviewLanding' }
  if (state.reviewOpen) { state.reviewOpen = false; return 'reviewOpen' }
  if (state.settingsOpen) { state.settingsOpen = false; return 'settingsOpen' }
  if (state.activePill !== 'clarified') { state.activePill = 'clarified'; return 'activePill' }
  return null
}

describe('Email viewer escape key priority', () => {
  it('escape closes email viewer first, not the clarify dialog', () => {
    const state = { ...defaultState(), emailViewerOpen: true, dialogOpen: true }
    const closed = handleEscape(state)
    expect(closed).toBe('emailViewer')
    expect(state.emailViewerOpen).toBe(false)
    expect(state.dialogOpen).toBe(true) // clarify dialog stays open
  })

  it('second escape does NOT close email viewer again', () => {
    const state = { ...defaultState(), emailViewerOpen: false, dialogOpen: true }
    const closed = handleEscape(state)
    // Should fall through to something else, not emailViewer
    expect(closed).not.toBe('emailViewer')
  })

  it('escape with no email viewer open falls through to other modals', () => {
    const state = { ...defaultState(), exportModalOpen: true }
    const closed = handleEscape(state)
    expect(closed).toBe('exportModal')
    expect(state.exportModalOpen).toBe(false)
  })

  it('email viewer takes priority over selection clear', () => {
    const state = { ...defaultState(), emailViewerOpen: true, selectedIds: 3 }
    const closed = handleEscape(state)
    expect(closed).toBe('emailViewer')
    expect(state.selectedIds).toBe(3) // selection untouched
  })

  it('email viewer takes priority over all other modals', () => {
    const state: ModalState = {
      emailViewerOpen: true,
      selectedIds: 5,
      exportModalOpen: true,
      processingInbox: true,
      quickCapture: true,
      quickNextAction: true,
      quickWaiting: true,
      searchOpen: true,
      reviewLanding: true,
      reviewOpen: true,
      settingsOpen: true,
      dialogOpen: true,
      activePill: 'inbox',
    }
    const closed = handleEscape(state)
    expect(closed).toBe('emailViewer')
    expect(state.emailViewerOpen).toBe(false)
    // Everything else still open
    expect(state.selectedIds).toBe(5)
    expect(state.exportModalOpen).toBe(true)
    expect(state.settingsOpen).toBe(true)
  })

  it('with no modals open and clarified pill active, escape does nothing', () => {
    const state = defaultState()
    const closed = handleEscape(state)
    expect(closed).toBeNull()
  })

  it('with non-clarified pill active, escape returns to clarified', () => {
    const state = { ...defaultState(), activePill: 'inbox' }
    const closed = handleEscape(state)
    expect(closed).toBe('activePill')
    expect(state.activePill).toBe('clarified')
  })
})
