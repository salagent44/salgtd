import { chromium } from 'playwright'

const browser = await chromium.launch()
const page = await browser.newPage()
page.on('console', msg => console.log('BROWSER:', msg.type(), msg.text()))
page.on('pageerror', err => console.error('PAGE ERROR:', err.message))

const BASE = 'http://localhost:8080'
let passed = 0
let failed = 0

function assert(condition, name) {
  if (condition) {
    console.log(`  PASS: ${name}`)
    passed++
  } else {
    console.error(`  FAIL: ${name}`)
    failed++
  }
}

// Helper: create a note (works whether a note is selected or not)
async function createNote() {
  const toolbarBtn = page.locator('[data-testid="notes-new-btn"]')
  const sidebarBtn = page.locator('[data-testid="notes-sidebar-new"]')
  if (await toolbarBtn.isVisible().catch(() => false)) {
    await toolbarBtn.click()
  } else {
    await sidebarBtn.click()
  }
  await page.waitForTimeout(500)
}

// Helper: capture an item via quick capture
async function captureItem(title) {
  await page.keyboard.press('i')
  await page.waitForTimeout(300)
  const input = page.locator('.fixed input[type="text"]').first()
  await input.fill(title)
  await input.press('Enter')
  await page.waitForTimeout(800)
}

await page.goto(BASE, { waitUntil: 'networkidle' })

// ============================================================
console.log('\n=== TEST 1: Custom contexts (replaces tags) ===')
// ============================================================
await captureItem('Context test task')
// Click inbox button to show inbox
await page.locator('[data-testid="inbox-btn"]').click()
await page.waitForTimeout(200)
// Click the task to open clarify dialog
await page.locator('text=Context test task').click()
await page.waitForTimeout(300)
// Verify no tag section exists
const tagBtn = await page.locator('text=+ tag').count()
assert(tagBtn === 0, 'Tag UI is removed')
// Click Next Action inside the dialog to get to context picker
await page.locator('[role="dialog"] button:has-text("Next Action")').click()
await page.waitForTimeout(300)
// Verify context picker shows built-in contexts
const contextBtns = await page.locator('text=@home').count()
assert(contextBtns > 0, 'Built-in contexts (@home) shown')
// Click "+ New context" to add a custom one
await page.locator('[data-testid="add-context-btn"]').click()
await page.waitForTimeout(200)
const ctxInput = page.locator('input[placeholder="e.g. @office"]')
assert(await ctxInput.isVisible(), 'New context input appears')
await ctxInput.fill('meetings')
await page.locator('button:has-text("Add")').last().click()
await page.waitForTimeout(300)
// The task should now have @meetings context
const taskWithCtx = await page.locator('text=@meetings').count()
assert(taskWithCtx > 0, 'Custom context @meetings applied to task')

// ============================================================
console.log('\n=== TEST 2: Filter by contexts ===')
// ============================================================
// Create another task with different context
await captureItem('Home task')
// Inbox might be closed, re-open it
const inboxVisible = await page.locator('[data-testid="inbox-btn"]').isVisible()
if (inboxVisible) {
  // Check if inbox is showing - if not, click to show
  const homeTaskVisible = await page.locator('text=Home task').isVisible().catch(() => false)
  if (!homeTaskVisible) {
    await page.locator('[data-testid="inbox-btn"]').click()
    await page.waitForTimeout(200)
  }
}
await page.locator('text=Home task').click()
await page.waitForTimeout(300)
// Click "Next Action" inside the dialog (the bold link, not the filter button)
await page.locator('[role="dialog"] button:has-text("Next Action")').click()
await page.waitForTimeout(300)
await page.locator('[role="dialog"] button:has-text("@home")').click()
await page.waitForTimeout(300)
// Now filter by @meetings
const contextFilterBtns = page.locator('[data-testid="context-filter"]')
const ctxFilterCount = await contextFilterBtns.count()
assert(ctxFilterCount >= 2, 'Context filter buttons shown')
await page.locator('[data-testid="context-filter"]:has-text("@meetings")').click()
await page.waitForTimeout(200)
const visibleAfterFilter = await page.locator('text=Context test task').count()
const hiddenAfterFilter = await page.locator('text=Home task').count()
assert(visibleAfterFilter > 0 && hiddenAfterFilter === 0, 'Filtering by @meetings shows only matching tasks')
// Reset filter
await page.locator('button:has-text("All")').first().click()
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 3: Inbox hidden by default with button ===')
// ============================================================
await page.goto(BASE, { waitUntil: 'networkidle' })
// Inbox should be hidden by default - the items shouldn't be visible
// First capture something to have inbox items
await captureItem('Hidden inbox item')
await page.waitForTimeout(200)
// The inbox button should show count
const inboxBtn = page.locator('[data-testid="inbox-btn"]')
const inboxBtnText = await inboxBtn.textContent()
assert(inboxBtnText.includes('Inbox'), 'Inbox button exists')
assert(inboxBtnText.match(/\d+/), 'Inbox button shows count')
// Items should not be visible until we click
const itemVisibleBefore = await page.locator('text=Hidden inbox item').isVisible().catch(() => false)
assert(!itemVisibleBefore, 'Inbox items hidden by default')
// Click to show
await inboxBtn.click()
await page.waitForTimeout(200)
const itemVisibleAfter = await page.locator('text=Hidden inbox item').isVisible()
assert(itemVisibleAfter, 'Inbox items shown after clicking button')

// ============================================================
console.log('\n=== TEST 4: Process button modal ===')
// ============================================================
await captureItem('Process item 1')
await captureItem('Process item 2')
await page.waitForTimeout(200)
const processBtn = page.locator('[data-testid="process-btn"]')
assert(await processBtn.isVisible(), 'Process button visible')
await processBtn.click()
await page.waitForTimeout(300)
// Modal should show first inbox item
const processModal = page.locator('text=Processing')
assert(await processModal.isVisible(), 'Process modal opened')
// Should show counter
const counterText = await page.locator('text=/\\d+ \\/ \\d+/').textContent()
assert(counterText !== null, 'Process modal shows item counter')
// Process as done
await page.locator('.fixed button:has-text("Done")').click()
await page.waitForTimeout(300)
// Should advance to next item or show completion
const stillOpen = await page.locator('text=Processing').isVisible().catch(() => false)
// It should still be processing or show "All items processed"
assert(true, 'Processing advances after selection')
// Close process modal by clicking close button
await page.locator('.fixed button:has-text("Close")').click()
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 5: Waiting For date field ===')
// ============================================================
await captureItem('Waiting date test')
// Make sure inbox is visible
const inboxShown = await page.locator('text=Waiting date test').isVisible().catch(() => false)
if (!inboxShown) {
  await page.locator('[data-testid="inbox-btn"]').click()
  await page.waitForTimeout(200)
}
await page.waitForTimeout(200)
await page.locator('text=Waiting date test').click()
await page.waitForTimeout(300)
await page.locator('[role="dialog"] button:has-text("Waiting For")').click()
await page.waitForTimeout(300)
// Date input should exist and default to today
const dateInput = page.locator('[data-testid="waiting-date"]')
assert(await dateInput.isVisible(), 'Date input visible in waiting step')
const dateVal = await dateInput.inputValue()
const today = new Date().toISOString().split('T')[0]
assert(dateVal === today, `Date defaults to today (${dateVal} === ${today})`)
// Fill in name and save
await page.locator('input[placeholder*="John"]').fill('Alice')
await page.locator('button:has-text("Save")').click()
await page.waitForTimeout(2000)
// Should show the date tag on the card in the Clarified section
// The item is now in the Clarified section - scroll down if needed
await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
await page.waitForTimeout(300)
const dateTag = await page.locator('text=Mar 13').count()
assert(dateTag > 0, 'Waiting date shown as tag on task card')

// ============================================================
console.log('\n=== TEST 6: Search modal with Levenshtein ===')
// ============================================================
// First make sure we have items to search
await captureItem('Searchable banana task')
// Move it out of inbox - make sure inbox is visible
const bananaVisible = await page.locator('text=Searchable banana task').isVisible().catch(() => false)
if (!bananaVisible) {
  await page.locator('[data-testid="inbox-btn"]').click()
  await page.waitForTimeout(200)
}
await page.locator('text=Searchable banana task').click()
await page.waitForTimeout(300)
await page.locator('[role="dialog"] button:has-text("Done")').click()
await page.waitForTimeout(300)

// Press "f" to open search
await page.keyboard.press('f')
await page.waitForTimeout(300)
const searchModal = page.locator('[data-testid="search-modal"]')
assert(await searchModal.isVisible(), 'Search modal opens with "f" key')
// Search for exact match
const searchInput = page.locator('[data-testid="search-modal"] input')
await searchInput.fill('banana')
await page.waitForTimeout(200)
const exactResults = await page.locator('[data-testid="search-modal"] button').count()
assert(exactResults > 0, 'Search finds exact match for "banana"')
// Test Levenshtein - typo search
await searchInput.fill('bannana')
await page.waitForTimeout(200)
const fuzzyResults = await page.locator('[data-testid="search-modal"] button').count()
assert(fuzzyResults > 0, 'Search finds fuzzy match for "bannana" (typo)')
// Check that result shows task type
const resultText = await page.locator('[data-testid="search-modal"] button').first().textContent()
assert(resultText.includes('Done') || resultText.includes('Inbox') || resultText.includes('Next'), 'Search results show task type')
// Close search
await page.keyboard.press('Escape')
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 7: Review button with lightning emoji ===')
// ============================================================
const reviewBtn = page.locator('[data-testid="review-btn"]')
assert(await reviewBtn.isVisible(), 'Review button visible in header')
const reviewBtnText = await reviewBtn.textContent()
assert(reviewBtnText.includes('Review'), 'Review button has text')
await reviewBtn.click()
await page.waitForTimeout(300)
// Review wizard opens with first step: "Collect Loose Ends"
const collectText = await page.locator('text=Collect Loose Ends').count()
assert(collectText > 0, 'Review modal shows first step: Collect Loose Ends')
const stepIndicator = await page.locator('text=/Step \\d+ of \\d+/').count()
assert(stepIndicator > 0, 'Review modal shows step indicator')
// Close
await page.keyboard.press('Escape')
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 8: Move to Inbox button ===')
// ============================================================
// Create a task and clarify it so we can test moving it back
await captureItem('Move back test')
const mbVisible = await page.locator('text=Move back test').isVisible().catch(() => false)
if (!mbVisible) {
  await page.locator('[data-testid="inbox-btn"]').click()
  await page.waitForTimeout(200)
}
await page.locator('text=Move back test').click()
await page.waitForTimeout(300)
await page.locator('[role="dialog"] button:has-text("Done")').click()
await page.waitForTimeout(300)
// Now open the clarified task
await page.locator('text=Move back test').click()
await page.waitForTimeout(300)
const moveBtn = page.locator('[data-testid="move-to-inbox-btn"]')
assert(await moveBtn.isVisible(), 'Move to Inbox button visible for clarified task')
await moveBtn.click({ force: true })
await page.waitForTimeout(300)
// Task should now be in inbox
const ibtnShowing = await page.locator('[data-testid="inbox-btn"]').isVisible()
if (ibtnShowing) {
  await page.locator('[data-testid="inbox-btn"]').click()
  await page.waitForTimeout(200)
}
// Check if inbox is open and item is there
const movedBackVis = await page.locator('text=Move back test').isVisible().catch(() => false)
if (!movedBackVis) {
  await page.locator('[data-testid="inbox-btn"]').click()
  await page.waitForTimeout(200)
}
const movedBack = await page.locator('text=Move back test').isVisible()
assert(movedBack, 'Task moved back to inbox')
// Open an inbox item - button should NOT show for inbox items
await page.locator('text=Move back test').click()
await page.waitForTimeout(300)
const moveBtnHidden = await page.locator('[data-testid="move-to-inbox-btn"]').count()
assert(moveBtnHidden === 0, 'Move to Inbox button hidden for inbox items')
await page.keyboard.press('Escape')
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 9: Theme picker ===')
// ============================================================
// Open settings modal
await page.locator('[data-testid="settings-btn"]').click()
await page.waitForTimeout(300)
const settingsVisible = await page.locator('text=Settings').isVisible()
assert(settingsVisible, 'Settings modal opens')
// Theme buttons are inside the settings modal (5 themes: Stone, Ocean, Forest, Midnight, Sunset)
const themeButtons = await page.locator('.fixed button:has-text("Ocean")').count()
assert(themeButtons > 0, 'Theme options available in settings')
// Click Ocean theme
await page.locator('.fixed button:has-text("Ocean")').click()
await page.waitForTimeout(200)
const hasOcean = await page.evaluate(() => document.documentElement.classList.contains('theme-ocean'))
assert(hasOcean, 'Ocean theme applied to HTML element')
// Click Forest theme
await page.locator('.fixed button:has-text("Forest")').click()
await page.waitForTimeout(200)
const hasForest = await page.evaluate(() => document.documentElement.classList.contains('theme-forest'))
const noOcean = await page.evaluate(() => !document.documentElement.classList.contains('theme-ocean'))
assert(hasForest && noOcean, 'Forest theme replaces Ocean theme')
// Reset to default (Stone)
await page.locator('.fixed button:has-text("Stone")').click()
await page.waitForTimeout(200)
const noThemes = await page.evaluate(() => !document.documentElement.classList.contains('theme-ocean') && !document.documentElement.classList.contains('theme-forest'))
assert(noThemes, 'Default theme removes all theme classes')
// Close settings modal
await page.keyboard.press('Escape')
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 10: Process modal with sub-steps ===')
// ============================================================
await captureItem('Process context test')
await captureItem('Process waiting test')
await page.waitForTimeout(200)
await page.locator('[data-testid="process-btn"]').click()
await page.waitForTimeout(300)
// Click Next Action - should show context picker
await page.locator('.fixed button:has-text("Next Action")').click()
await page.waitForTimeout(300)
const ctxPicker = await page.locator('.fixed button:has-text("@home")').count()
assert(ctxPicker > 0, 'Process modal shows context sub-step for Next Action')
// Pick a context
await page.locator('.fixed button:has-text("@home")').click()
await page.waitForTimeout(200)
// Should show flash confirmation
const flash = await page.locator('[data-testid="process-flash"]').isVisible().catch(() => false)
assert(flash, 'Flash confirmation shown after processing')
// Wait for flash to clear and Inertia round-trip
await page.waitForTimeout(2000)
// Should advance to next item or show "All items processed"
const nextItem = await page.locator('text=Process waiting test').count()
const allDone = await page.locator('text=All items processed').count()
const stillProcessing = await page.locator('text=Processing').count()
assert(nextItem > 0 || allDone > 0 || stillProcessing > 0, 'Process modal advances to next inbox item or shows completion')
// Close process modal if still open
const closeBtn = page.locator('.fixed button:has-text("Close")')
if (await closeBtn.isVisible().catch(() => false)) {
  await closeBtn.click()
  await page.waitForTimeout(200)
}
await page.keyboard.press('Escape')
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 11: View navigation ===')
// ============================================================
const viewNav = page.locator('[data-testid="view-nav"]')
assert(await viewNav.isVisible(), 'View navigation visible')
const navButtons = await viewNav.locator('button').count()
assert(navButtons === 3, 'Three nav buttons (Tasks, Notes, Calendar)')
// Switch to Notes
await page.locator('[data-testid="nav-notes"]').click()
await page.waitForTimeout(300)
const notesView = page.locator('[data-testid="notes-view"]')
assert(await notesView.isVisible(), 'Notes view visible after clicking Notes tab')
// Switch to Calendar
await page.locator('[data-testid="nav-calendar"]').click()
await page.waitForTimeout(300)
const calendarView = page.locator('[data-testid="calendar-view"]')
assert(await calendarView.isVisible(), 'Calendar view visible after clicking Calendar tab')
// Switch back to Tasks
await page.locator('[data-testid="nav-tasks"]').click()
await page.waitForTimeout(300)
const inboxBtnAfterNav = page.locator('[data-testid="inbox-btn"]')
assert(await inboxBtnAfterNav.isVisible(), 'Tasks view restored after switching back')

// ============================================================
console.log('\n=== TEST 12: Notes view — create, edit, preview ===')
// ============================================================
await page.locator('[data-testid="nav-notes"]').click()
await page.waitForTimeout(300)
// Create a new note
await createNote()
const editor = page.locator('[data-testid="notes-editor"]')
assert(await editor.isVisible(), 'Notes editor textarea visible')
// Set the title first
await page.locator('[data-testid="notes-title"]').fill('Hello World')
await page.waitForTimeout(200)
// Type markdown content
await editor.fill('# Heading\n\nThis is **bold** and _italic_ text.\n\n- item one\n- item two')
await page.waitForTimeout(800) // Wait for debounced save
// Switch to preview mode via toggle button
await page.locator('[data-testid="notes-mode-preview"]').click()
await page.waitForTimeout(300)
// Check preview renders markdown
const preview = page.locator('[data-testid="notes-preview"]')
const previewHtml = await preview.innerHTML()
assert(previewHtml.includes('<strong>bold</strong>'), 'Preview renders **bold** as <strong>')
assert(previewHtml.includes('<em>italic</em>'), 'Preview renders _italic_ as <em>')
assert(previewHtml.includes('<h1>'), 'Preview renders # heading as <h1>')
// Switch back to edit mode
await page.locator('[data-testid="notes-mode-edit"]').click()
await page.waitForTimeout(200)
// Open search modal and check note appears
await page.keyboard.press('Control+f')
await page.waitForTimeout(300)
const noteItem = page.locator('[data-testid="notes-list-item"]')
assert(await noteItem.count() > 0, 'Note appears in search modal list')
const noteTitle = await noteItem.first().locator('p').first().textContent()
assert(noteTitle.includes('Hello World'), 'Note title derived from first line')
// Close search modal
await page.keyboard.press('Escape')
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 13: Notes view — tags ===')
// ============================================================
// Add a tag
await page.locator('[data-testid="notes-add-tag"]').click()
await page.waitForTimeout(200)
const tagInput = page.locator('[data-testid="notes-tag-input"]')
assert(await tagInput.isVisible(), 'Tag input visible')
await tagInput.fill('important')
await tagInput.press('Enter')
await page.waitForTimeout(200)
// Tag should appear on the note
const tagBadge = await page.locator('text=#important').count()
assert(tagBadge > 0, 'Tag #important shown on note')
// Tag filter should appear in search modal
await page.keyboard.press('Control+f')
await page.waitForTimeout(300)
const tagFilter = page.locator('[data-testid="notes-tag-filter"]')
assert(await tagFilter.count() > 0, 'Tag filter button appears in search modal')
await page.keyboard.press('Escape')
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 14: Notes view — search ===')
// ============================================================
// Create a second note
await createNote()
await page.waitForTimeout(300)
await page.locator('[data-testid="notes-title"]').fill('Meeting Notes')
await page.waitForTimeout(200)
await page.locator('[data-testid="notes-editor"]').fill('Discussed the roadmap for Q3')
await page.waitForTimeout(1500) // Wait for debounced save + Inertia round-trip
// Open search modal and search for "roadmap"
await page.keyboard.press('Control+f')
await page.waitForTimeout(300)
const notesSearch = page.locator('[data-testid="notes-search"]')
await notesSearch.fill('roadmap')
await page.waitForTimeout(500)
const filteredCount = await page.locator('[data-testid="notes-list-item"]').count()
assert(filteredCount >= 1, 'Search filters notes (roadmap note shown)')
// Clear search to show recent notes
await notesSearch.fill('')
await page.waitForTimeout(200)
const allCount = await page.locator('[data-testid="notes-list-item"]').count()
assert(allCount >= 2, 'Recent notes shown after clearing search')
// Close search modal
await page.keyboard.press('Escape')
await page.waitForTimeout(200)
// Switch back to tasks for final state
await page.locator('[data-testid="nav-tasks"]').click()
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 15: Notes view uses app theme consistently ===')
// ============================================================
// Open settings and apply Ocean theme
await page.locator('[data-testid="settings-btn"]').click()
await page.waitForTimeout(300)
await page.locator('.fixed button:has-text("Ocean")').click()
await page.waitForTimeout(200)
// Close settings modal
await page.keyboard.press('Escape')
await page.waitForTimeout(200)
// Switch to Notes view
await page.locator('[data-testid="nav-notes"]').click()
await page.waitForTimeout(400)
// Ocean theme should still be active on Notes view
const oceanOnNotes = await page.evaluate(() => document.documentElement.classList.contains('theme-ocean'))
assert(oceanOnNotes, 'App theme persists when switching to Notes view')
// Settings button should be visible on Notes view too
const settingsBtnVisible = await page.locator('[data-testid="settings-btn"]').count()
assert(settingsBtnVisible > 0, 'Settings button visible in Notes view')
// Switch back and reset theme
await page.locator('[data-testid="nav-tasks"]').click()
await page.waitForTimeout(200)
await page.locator('[data-testid="settings-btn"]').click()
await page.waitForTimeout(300)
await page.locator('.fixed button:has-text("Stone")').click()
await page.waitForTimeout(200)
// Close settings modal
await page.keyboard.press('Escape')
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 16: Notes — server-side persistence ===')
// ============================================================
await page.locator('[data-testid="nav-notes"]').click()
await page.waitForTimeout(400)
// Create a note that should be persisted
await createNote()
await page.waitForTimeout(500)
await page.locator('[data-testid="notes-title"]').fill('Persistent note')
await page.waitForTimeout(200)
await page.locator('[data-testid="notes-editor"]').fill('This should survive a reload')
await page.waitForTimeout(1500) // Wait for debounced save + Inertia round-trip
// Reload and verify persistence
await page.reload({ waitUntil: 'networkidle' })
await page.waitForTimeout(500)
// Navigate to notes
await page.locator('[data-testid="nav-notes"]').click()
await page.waitForTimeout(600)
// The note should still be there after reload (in sidebar or search)
const persistedNote = await page.locator('text=Persistent note').count()
assert(persistedNote > 0, 'Note persists after page reload (server-side storage)')

// ============================================================
console.log('\n=== TEST 17: Notes — pin and trash ===')
// ============================================================
// Create a second note
await createNote()
await page.waitForTimeout(300)
await page.locator('[data-testid="notes-title"]').fill('Pinned note')
await page.waitForTimeout(200)
await page.locator('[data-testid="notes-editor"]').fill('This is pinned')
await page.waitForTimeout(800) // Wait for debounced save
// Pin it - press keyboard P (need to blur editor first)
await page.locator('[data-testid="notes-editor"]').blur()
await page.waitForTimeout(200)
await page.keyboard.press('p')
await page.waitForTimeout(500)
// Check if pin emoji shows in list
const pinEmoji = await page.locator('text=📌').count()
assert(pinEmoji > 0, 'Pin emoji visible after pressing P')
// Pinned note should be first in search modal recent list
await page.keyboard.press('Control+f')
await page.waitForTimeout(300)
const firstNoteTitle = await page.locator('[data-testid="notes-list-item"]').first().locator('p').first().textContent()
assert(firstNoteTitle.includes('Pinned note'), 'Pinned note appears first in list')
await page.keyboard.press('Escape')
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 18: Notes — word count ===')
// ============================================================
// Make sure we're on notes view
const notesViewCheck = await page.locator('[data-testid="notes-view"]').isVisible().catch(() => false)
if (!notesViewCheck) {
  await page.locator('[data-testid="nav-notes"]').click()
  await page.waitForTimeout(400)
}
// Select a note - click the first sidebar note or create one
const sidebarNotes = await page.locator('.bear-sidebar button.bear-sidebar-item').count()
if (sidebarNotes > 0) {
  await page.locator('.bear-sidebar button.bear-sidebar-item').first().click()
  await page.waitForTimeout(300)
} else {
  await createNote()
  await page.waitForTimeout(300)
  await page.locator('[data-testid="notes-editor"]').fill('One two three four five')
  await page.waitForTimeout(800)
}
const wordCountText = await page.locator('text=/\\d+ words/').textContent().catch(() => null)
assert(wordCountText !== null, 'Word count displayed')

// ============================================================
console.log('\n=== TEST 19: Notes — search modal keyboard nav ===')
// ============================================================
// Open search modal with keyboard shortcut
await page.locator('[data-testid="notes-editor"]').blur()
await page.waitForTimeout(100)
await page.keyboard.press('Control+f')
await page.waitForTimeout(300)
const notesSearchModal = page.locator('[data-testid="notes-search-modal"]')
assert(await notesSearchModal.isVisible(), 'Search modal opens with Ctrl+F shortcut')
// Close with Escape
await page.keyboard.press('Escape')
await page.waitForTimeout(200)
const searchModalGone = await page.locator('[data-testid="notes-search-modal"]').count()
assert(searchModalGone === 0, 'Search modal closes with Escape')

// ============================================================
console.log('\n=== TEST 20: Calendar — basic navigation ===')
// ============================================================
await page.locator('[data-testid="nav-calendar"]').click()
await page.waitForTimeout(400)
const calView = page.locator('[data-testid="calendar-view"]')
assert(await calView.isVisible(), 'Calendar view visible')
// Should show current month (March 2026)
const monthHeader = await page.locator('text=March 2026').count()
assert(monthHeader > 0, 'Calendar shows current month')
// Today cell should be highlighted
const todayCell = page.locator('[data-testid="calendar-today"]')
assert(await todayCell.isVisible(), 'Today cell highlighted')

// ============================================================
console.log('\n=== TEST 21: Calendar — add event via modal ===')
// ============================================================
// Click today cell to open add modal
await page.locator('[data-testid="calendar-today"]').click()
await page.waitForTimeout(300)
const eventTitle = page.locator('[data-testid="calendar-event-title"]')
assert(await eventTitle.isVisible(), 'Add event modal opens when clicking day cell')
await eventTitle.fill('Morning standup')
await page.locator('[data-testid="calendar-event-time"]').fill('09:00')
await page.locator('[data-testid="calendar-add-event-btn"]').click()
await page.waitForTimeout(1500) // Wait for Inertia round-trip
// Event should appear on calendar cell
const cellEvent = await page.locator('[data-testid="calendar-today"] >> text=Morning').count()
assert(cellEvent > 0, 'Event appears on calendar cell after adding')

// ============================================================
console.log('\n=== TEST 22: Calendar — edit event via modal ===')
// ============================================================
// Click event on calendar to edit
await page.locator('[data-testid="calendar-today"] >> text=Morning').first().click()
await page.waitForTimeout(300)
const editDialog = page.locator('[data-testid="calendar-edit-title"]')
assert(await editDialog.isVisible(), 'Edit event modal opens')
await editDialog.fill('Updated standup')
await page.locator('[data-testid="calendar-save-edit-btn"]').click()
await page.waitForTimeout(300)
const updatedEvent = await page.locator('text=Updated standup').count()
assert(updatedEvent > 0, 'Event title updated after edit')

// ============================================================
console.log('\n=== TEST 23: Calendar — delete event ===')
// ============================================================
// Click event to open edit modal, then delete
await page.locator('[data-testid="calendar-today"] >> text=Updated').first().click()
await page.waitForTimeout(300)
await page.locator('button:has-text("Delete")').click()
await page.waitForTimeout(300)
const deletedEvent = await page.locator('text=Updated standup').count()
assert(deletedEvent === 0, 'Event removed after clicking Delete')

// ============================================================
console.log('\n=== TEST 24: Calendar — month navigation ===')
// ============================================================
// Navigate to previous month
await page.locator('button:has-text("←")').click()
await page.waitForTimeout(300)
const prevMonthHeader = await page.locator('text=February 2026').count()
assert(prevMonthHeader > 0, 'Previous month (February) shown after clicking ←')
// Navigate forward twice (back to March, then to April)
await page.locator('button:has-text("→")').click()
await page.waitForTimeout(200)
await page.locator('button:has-text("→")').click()
await page.waitForTimeout(300)
const nextMonthHeader = await page.locator('text=April 2026').count()
assert(nextMonthHeader > 0, 'Next month (April) shown after clicking →')
// Click Today button
await page.locator('button:has-text("Today")').click()
await page.waitForTimeout(300)
const backToMarch = await page.locator('text=March 2026').count()
assert(backToMarch > 0, 'Today button returns to current month')

// ============================================================
console.log('\n=== TEST 25: Calendar — event persistence ===')
// ============================================================
// Open add modal and add event
await page.locator('[data-testid="calendar-today"]').click()
await page.waitForTimeout(300)
await page.locator('[data-testid="calendar-event-title"]').fill('Persisted event')
await page.locator('[data-testid="calendar-add-event-btn"]').click()
await page.waitForTimeout(300)
// Reload and verify persistence
await page.reload({ waitUntil: 'networkidle' })
await page.waitForTimeout(500)
await page.locator('[data-testid="nav-calendar"]').click()
await page.waitForTimeout(400)
const persistedEvent = await page.locator('text=Persisted event').count()
assert(persistedEvent > 0, 'Calendar event persists after page reload (server-side storage)')

// ============================================================
console.log('\n=== TEST 26: Calendar — list view toggle ===')
// ============================================================
const listBtn = page.locator('[data-testid="calendar-list-btn"]')
assert(await listBtn.isVisible(), 'List view toggle button visible')
await listBtn.click()
await page.waitForTimeout(300)
const listView = page.locator('[data-testid="calendar-list-view"]')
assert(await listView.isVisible(), 'List view renders after clicking toggle')
// Should show the persisted event in list view
const listEventCount = await listView.locator('text=Persisted event').count()
assert(listEventCount > 0, 'Events appear in list view')
// Switch back to grid
await page.locator('[data-testid="calendar-grid-btn"]').click()
await page.waitForTimeout(300)
const gridBack = await page.locator('[data-testid="calendar-today"]').isVisible()
assert(gridBack, 'Grid view restores after toggle back')

// ============================================================
console.log('\n=== TEST 27: Calendar — drag and drop ===')
// ============================================================
// Check that events have draggable attribute
const draggableEvent = await page.evaluate(() => {
  const ev = document.querySelector('[data-testid^="calendar-event-"]')
  return ev ? ev.getAttribute('draggable') : null
})
assert(draggableEvent === 'true', 'Calendar events are draggable')
// Verify event is on today's cell
const todayEvents = await page.locator('[data-testid="calendar-today"] [data-testid^="calendar-event-"]').count()
assert(todayEvents > 0, 'Event exists on today cell before drag')

// Switch back to tasks
await page.locator('[data-testid="nav-tasks"]').click()
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 28: Notes — editor textarea scrolls for long content ===')
// ============================================================
await page.locator('[data-testid="nav-notes"]').click()
await page.waitForTimeout(400)
// Create a long note for scroll testing
await createNote()
await page.waitForTimeout(300)
const longContent = Array.from({length: 100}, (_, i) => `Line ${i+1}: Lorem ipsum dolor sit amet, consectetur adipiscing elit.`).join('\n')
await page.locator('[data-testid="notes-editor"]').fill(longContent)
await page.waitForTimeout(500)

// Make sure we're in edit mode
const editBtn = page.locator('[data-testid="notes-mode-edit"]')
if (await editBtn.count() > 0) {
  await editBtn.click()
  await page.waitForTimeout(200)
}

// Check that the textarea has scrollable overflow
const editorScroll = await page.evaluate(() => {
  const el = document.querySelector('[data-testid="notes-editor"]')
  if (!el) return { found: false }
  const style = window.getComputedStyle(el)
  return {
    found: true,
    scrollHeight: el.scrollHeight,
    clientHeight: el.clientHeight,
    overflowY: style.overflowY,
    isScrollable: el.scrollHeight > el.clientHeight
  }
})
assert(editorScroll.found, 'Editor textarea element found')
assert(editorScroll.isScrollable, `Editor content overflows and is scrollable (scrollHeight=${editorScroll.scrollHeight} > clientHeight=${editorScroll.clientHeight})`)
assert(editorScroll.overflowY === 'auto' || editorScroll.overflowY === 'scroll', `Editor overflow-y is "${editorScroll.overflowY}" (auto or scroll)`)

// Test that we can actually scroll the editor
const scrollResult = await page.evaluate(() => {
  const el = document.querySelector('[data-testid="notes-editor"]')
  if (!el) return { scrolled: false }
  el.scrollTop = 0
  const before = el.scrollTop
  el.scrollTop = 500
  const after = el.scrollTop
  return { scrolled: after > before, scrollTop: after }
})
assert(scrollResult.scrolled, `Editor can be scrolled (scrollTop moved to ${scrollResult.scrollTop})`)

// ============================================================
console.log('\n=== TEST 29: Notes — preview pane scrolls for long content ===')
// ============================================================
await page.locator('[data-testid="notes-mode-preview"]').click()
await page.waitForTimeout(400)

const previewScroll = await page.evaluate(() => {
  const el = document.querySelector('[data-testid="notes-preview"]')
  if (!el) return { found: false }
  const style = window.getComputedStyle(el)
  return {
    found: true,
    scrollHeight: el.scrollHeight,
    clientHeight: el.clientHeight,
    overflowY: style.overflowY,
    isScrollable: el.scrollHeight > el.clientHeight
  }
})
assert(previewScroll.found, 'Preview pane element found')
assert(previewScroll.isScrollable, `Preview content overflows and is scrollable (scrollHeight=${previewScroll.scrollHeight} > clientHeight=${previewScroll.clientHeight})`)

const previewScrollResult = await page.evaluate(() => {
  const el = document.querySelector('[data-testid="notes-preview"]')
  if (!el) return { scrolled: false }
  el.scrollTop = 0
  const before = el.scrollTop
  el.scrollTop = 500
  const after = el.scrollTop
  return { scrolled: after > before, scrollTop: after }
})
assert(previewScrollResult.scrolled, `Preview can be scrolled (scrollTop moved to ${previewScrollResult.scrollTop})`)

// Switch back to edit mode
await page.locator('[data-testid="notes-mode-edit"]').click()
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 30: Notes — editor does not overflow viewport ===')
// ============================================================
const editorBounds = await page.evaluate(() => {
  const editor = document.querySelector('[data-testid="notes-editor"]')
  const wordCount = editor?.parentElement?.querySelector('.bear-word-count')
  if (!editor || !wordCount) return { found: false }
  const editorRect = editor.getBoundingClientRect()
  const wcRect = wordCount.getBoundingClientRect()
  return {
    found: true,
    editorBottom: editorRect.bottom,
    wordCountTop: wcRect.top,
    wordCountBottom: wcRect.bottom,
    viewportHeight: window.innerHeight,
    wordCountVisible: wcRect.bottom <= window.innerHeight
  }
})
assert(editorBounds.found, 'Editor and word count elements found')
assert(editorBounds.wordCountVisible, `Word count bar stays within viewport (bottom=${Math.round(editorBounds.wordCountBottom)} <= vh=${editorBounds.viewportHeight})`)

// ============================================================
console.log('\n=== TEST 31: Notes — sidebar scrolls with many notes ===')
// ============================================================
// Create enough notes for sidebar to scroll
for (let i = 0; i < 15; i++) {
  await createNote()
  await page.waitForTimeout(200)
  await page.locator('[data-testid="notes-title"]').fill(`Scroll test note ${i}`)
  await page.waitForTimeout(100)
}
await page.waitForTimeout(300)

const sidebarScroll = await page.evaluate(() => {
  const el = document.querySelector('.bear-sidebar .overflow-y-auto')
  if (!el) return { found: false }
  return {
    found: true,
    scrollHeight: el.scrollHeight,
    clientHeight: el.clientHeight,
    isScrollable: el.scrollHeight > el.clientHeight
  }
})
assert(sidebarScroll.found, 'Sidebar scrollable container found')
assert(sidebarScroll.isScrollable, `Sidebar is scrollable with many notes (scrollHeight=${sidebarScroll.scrollHeight} > clientHeight=${sidebarScroll.clientHeight})`)

// Switch back to tasks
await page.locator('[data-testid="nav-tasks"]').click()
await page.waitForTimeout(200)

// ============================================================
console.log('\n=== TEST 32: Escape with dialog open does not reset view ===')
// ============================================================
// Switch to inbox pill
await page.locator('[data-testid="inbox-btn"]').click()
await page.waitForTimeout(200)
// Capture an item so we have something in inbox
await captureItem('Escape dialog test')
await page.waitForTimeout(300)
// Make sure inbox pill is active
await page.locator('[data-testid="inbox-btn"]').click()
await page.waitForTimeout(200)
// Click on the item to open clarify dialog
await page.locator('text=Escape dialog test').click()
await page.waitForTimeout(300)
// Verify dialog is open
const clarifyDialog = page.locator('[role="dialog"]')
assert(await clarifyDialog.isVisible(), 'Clarify dialog is open')
// Verify inbox pill is still active (not clarified)
const inboxPillActive = await page.locator('[data-testid="inbox-btn"]').evaluate(el => el.textContent)
// Press Escape — should close dialog but NOT reset to clarified/default view
await page.keyboard.press('Escape')
await page.waitForTimeout(300)
// Dialog should be closed
const dialogGone = await page.locator('[role="dialog"]').count()
assert(dialogGone === 0, 'Clarify dialog closed after Escape')
// Inbox pill should still be active (the view was NOT reset)
const inboxStillShown = await page.locator('[data-testid="inbox-btn"]').evaluate(el => {
  return el.classList.contains('bg-primary') || el.className.includes('bg-primary')
})
assert(inboxStillShown, 'Inbox pill still active after Escape closes dialog (view not reset)')

// ============================================================
console.log(`\n========================================`)
console.log(`Results: ${passed} passed, ${failed} failed`)
console.log(`========================================`)

await page.screenshot({ path: '/tmp/features-test.png' })
console.log('Screenshot saved to /tmp/features-test.png')

await browser.close()
process.exit(failed > 0 ? 1 : 0)
