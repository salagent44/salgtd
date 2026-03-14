import Foundation
import SwiftData

actor SyncEngine {
    private let apiClient: APIClient
    private let offlineQueue: OfflineQueue
    private let modelContainer: ModelContainer
    private var isSyncing = false

    init(apiClient: APIClient, offlineQueue: OfflineQueue, modelContainer: ModelContainer) {
        self.apiClient = apiClient
        self.offlineQueue = offlineQueue
        self.modelContainer = modelContainer
    }

    func sync() async throws {
        guard !isSyncing else { return }
        isSyncing = true
        defer { isSyncing = false }

        // Push local changes first
        await pushPendingMutations()

        // Then pull remote changes
        let syncState = await getSyncState()

        if syncState.lastSyncVersion == 0 {
            try await fullSync()
        } else {
            try await incrementalPull(sinceVersion: syncState.lastSyncVersion)
        }
    }

    func fullSync() async throws {
        let response = try await apiClient.fullSync()
        await applyFullSync(response)
    }

    func incrementalPull(sinceVersion: Int) async throws {
        let response = try await apiClient.pull(sinceVersion: sinceVersion)
        await applyIncrementalSync(response)
    }

    // MARK: - Push

    private func pushPendingMutations() async {
        let mutations = await offlineQueue.pendingMutations()
        guard !mutations.isEmpty else { return }

        do {
            let response = try await apiClient.push(mutations: mutations)
            // Clear queue on success
            await offlineQueue.clearAll()

            // Mark items as synced
            await markItemsSynced(results: response.results)
        } catch {
            // Keep mutations in queue for retry
        }
    }

    // MARK: - Apply Sync Data

    private func applyFullSync(_ response: SyncResponse) async {
        let context = ModelContext(modelContainer)

        // Clear existing data
        try? context.delete(model: GTDItem.self)
        try? context.delete(model: GTDNote.self)
        try? context.delete(model: GTDCalendarEvent.self)
        try? context.delete(model: GTDContext.self)
        try? context.delete(model: GTDEmail.self)

        // Insert all items
        for syncItem in response.items {
            let item = mapSyncItem(syncItem)
            context.insert(item)
            if let syncEmail = syncItem.email {
                let email = mapSyncEmail(syncEmail)
                email.item = item
                item.email = email
                context.insert(email)
            }
        }

        for syncNote in response.notes {
            context.insert(mapSyncNote(syncNote))
        }

        for syncEvent in response.calendarEvents {
            context.insert(mapSyncCalendarEvent(syncEvent))
        }

        for syncContext in response.contexts {
            context.insert(mapSyncContext(syncContext))
        }

        try? context.save()
        await updateSyncState(version: response.version)
    }

    private func applyIncrementalSync(_ response: SyncResponse) async {
        let context = ModelContext(modelContainer)

        for syncItem in response.items {
            await applyItemUpdate(syncItem, context: context)
        }

        for syncNote in response.notes {
            await applyNoteUpdate(syncNote, context: context)
        }

        for syncEvent in response.calendarEvents {
            await applyEventUpdate(syncEvent, context: context)
        }

        for syncContext in response.contexts {
            await applyContextUpdate(syncContext, context: context)
        }

        try? context.save()
        await updateSyncState(version: response.version)
    }

    // MARK: - Apply Individual Updates

    private func applyItemUpdate(_ syncItem: SyncItem, context: ModelContext) async {
        let id = syncItem.id
        let descriptor = FetchDescriptor<GTDItem>(predicate: #Predicate { $0.id == id })
        let existing = try? context.fetch(descriptor).first

        if syncItem.deleted {
            if let existing {
                existing.isDeleted = true
                existing.syncVersion = syncItem.syncVersion
            }
        } else if let existing {
            // Don't overwrite local pending changes
            guard !existing.pendingSync else { return }
            updateExistingItem(existing, from: syncItem)
        } else {
            let item = mapSyncItem(syncItem)
            context.insert(item)
            if let syncEmail = syncItem.email {
                let email = mapSyncEmail(syncEmail)
                email.item = item
                item.email = email
                context.insert(email)
            }
        }
    }

    private func applyNoteUpdate(_ syncNote: SyncNote, context: ModelContext) async {
        let id = syncNote.id
        let descriptor = FetchDescriptor<GTDNote>(predicate: #Predicate { $0.id == id })
        let existing = try? context.fetch(descriptor).first

        if syncNote.deleted {
            if let existing { existing.isDeleted = true; existing.syncVersion = syncNote.syncVersion }
        } else if let existing {
            guard !existing.pendingSync else { return }
            existing.title = syncNote.title
            existing.content = syncNote.content
            existing.pinned = syncNote.pinned
            existing.trashed = syncNote.trashed
            existing.locked = syncNote.locked
            existing.tags = syncNote.tags ?? []
            existing.syncVersion = syncNote.syncVersion
            existing.isDeleted = false
        } else {
            context.insert(mapSyncNote(syncNote))
        }
    }

    private func applyEventUpdate(_ syncEvent: SyncCalendarEvent, context: ModelContext) async {
        let id = syncEvent.id
        let descriptor = FetchDescriptor<GTDCalendarEvent>(predicate: #Predicate { $0.id == id })
        let existing = try? context.fetch(descriptor).first

        if syncEvent.deleted {
            if let existing { existing.isDeleted = true; existing.syncVersion = syncEvent.syncVersion }
        } else if let existing {
            guard !existing.pendingSync else { return }
            existing.title = syncEvent.title
            existing.eventDate = parseDate(syncEvent.eventDate) ?? Date()
            existing.endDate = parseDate(syncEvent.endDate)
            existing.eventTime = syncEvent.eventTime
            existing.endTime = syncEvent.endTime
            existing.eventDescription = syncEvent.description ?? ""
            existing.color = syncEvent.color ?? "blue"
            existing.recurrence = syncEvent.recurrence
            existing.syncVersion = syncEvent.syncVersion
            existing.isDeleted = false
        } else {
            context.insert(mapSyncCalendarEvent(syncEvent))
        }
    }

    private func applyContextUpdate(_ syncContext: SyncContext, context: ModelContext) async {
        let id = syncContext.id
        let descriptor = FetchDescriptor<GTDContext>(predicate: #Predicate { $0.id == id })
        let existing = try? context.fetch(descriptor).first

        if syncContext.deleted {
            if let existing { existing.isDeleted = true }
        } else if let existing {
            existing.name = syncContext.name
            existing.builtIn = syncContext.builtIn ?? false
            existing.sortOrder = syncContext.sortOrder ?? 0
            existing.syncVersion = syncContext.syncVersion
            existing.isDeleted = false
        } else {
            context.insert(mapSyncContext(syncContext))
        }
    }

    // MARK: - Mapping

    private func mapSyncItem(_ s: SyncItem) -> GTDItem {
        GTDItem(
            id: s.id,
            title: s.title,
            status: s.status,
            context: s.context,
            waitingFor: s.waitingFor,
            waitingDate: parseDate(s.waitingDate),
            ticklerDate: parseDate(s.ticklerDate),
            notes: s.notes,
            sortOrder: s.sortOrder ?? 0,
            flagged: s.flagged ?? false,
            completedAt: parseISO(s.completedAt),
            originalStatus: s.originalStatus,
            goal: s.goal,
            projectId: s.projectId,
            tags: s.tags ?? [],
            syncVersion: s.syncVersion,
            pendingSync: false,
            isDeleted: s.deleted
        )
    }

    private func mapSyncEmail(_ s: SyncEmail) -> GTDEmail {
        GTDEmail(
            id: s.id,
            fromAddress: s.fromAddress,
            fromName: s.fromName,
            toAddress: s.toAddress,
            subject: s.subject,
            bodyText: s.bodyText,
            receivedAt: parseISO(s.receivedAt),
            messageId: s.messageId
        )
    }

    private func mapSyncNote(_ s: SyncNote) -> GTDNote {
        GTDNote(
            id: s.id,
            title: s.title,
            content: s.content,
            pinned: s.pinned,
            trashed: s.trashed,
            locked: s.locked,
            tags: s.tags ?? [],
            syncVersion: s.syncVersion,
            pendingSync: false,
            isDeleted: s.deleted
        )
    }

    private func mapSyncCalendarEvent(_ s: SyncCalendarEvent) -> GTDCalendarEvent {
        GTDCalendarEvent(
            id: s.id,
            title: s.title,
            eventDate: parseDate(s.eventDate) ?? Date(),
            endDate: parseDate(s.endDate),
            eventTime: s.eventTime,
            endTime: s.endTime,
            eventDescription: s.description ?? "",
            color: s.color ?? "blue",
            recurrence: s.recurrence,
            syncVersion: s.syncVersion,
            pendingSync: false,
            isDeleted: s.deleted
        )
    }

    private func mapSyncContext(_ s: SyncContext) -> GTDContext {
        GTDContext(
            id: s.id,
            name: s.name,
            builtIn: s.builtIn ?? false,
            sortOrder: s.sortOrder ?? 0,
            syncVersion: s.syncVersion,
            isDeleted: s.deleted
        )
    }

    private func updateExistingItem(_ item: GTDItem, from s: SyncItem) {
        item.title = s.title
        item.status = s.status
        item.context = s.context
        item.waitingFor = s.waitingFor
        item.waitingDate = parseDate(s.waitingDate)
        item.ticklerDate = parseDate(s.ticklerDate)
        item.notes = s.notes
        item.sortOrder = s.sortOrder ?? 0
        item.flagged = s.flagged ?? false
        item.completedAt = parseISO(s.completedAt)
        item.originalStatus = s.originalStatus
        item.goal = s.goal
        item.projectId = s.projectId
        item.tags = s.tags ?? []
        item.syncVersion = s.syncVersion
        item.isDeleted = s.deleted
    }

    // MARK: - Helpers

    private func markItemsSynced(results: [PushResult]) async {
        let context = ModelContext(modelContainer)
        for result in results {
            switch result.entity {
            case "item":
                let id = result.id
                let desc = FetchDescriptor<GTDItem>(predicate: #Predicate { $0.id == id })
                if let item = try? context.fetch(desc).first {
                    item.pendingSync = false
                }
            case "note":
                let id = result.id
                let desc = FetchDescriptor<GTDNote>(predicate: #Predicate { $0.id == id })
                if let note = try? context.fetch(desc).first {
                    note.pendingSync = false
                }
            case "calendar_event":
                let id = result.id
                let desc = FetchDescriptor<GTDCalendarEvent>(predicate: #Predicate { $0.id == id })
                if let event = try? context.fetch(desc).first {
                    event.pendingSync = false
                }
            default:
                break
            }
        }
        try? context.save()
    }

    private func getSyncState() async -> (lastSyncVersion: Int, lastSyncDate: Date?) {
        let context = ModelContext(modelContainer)
        let descriptor = FetchDescriptor<SyncState>()
        if let state = try? context.fetch(descriptor).first {
            return (state.lastSyncVersion, state.lastSyncDate)
        }
        return (0, nil)
    }

    private func updateSyncState(version: Int) async {
        let context = ModelContext(modelContainer)
        let descriptor = FetchDescriptor<SyncState>()
        if let state = try? context.fetch(descriptor).first {
            state.lastSyncVersion = version
            state.lastSyncDate = Date()
        } else {
            let state = SyncState(lastSyncVersion: version, lastSyncDate: Date())
            context.insert(state)
        }
        try? context.save()
    }

    private func parseDate(_ string: String?) -> Date? {
        guard let string else { return nil }
        let formatter = DateFormatter()
        formatter.dateFormat = "yyyy-MM-dd"
        formatter.timeZone = TimeZone(identifier: "UTC")
        return formatter.date(from: string)
    }

    private func parseISO(_ string: String?) -> Date? {
        guard let string else { return nil }
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return formatter.date(from: string) ?? {
            let f = ISO8601DateFormatter()
            f.formatOptions = [.withInternetDateTime]
            return f.date(from: string)
        }()
    }
}
