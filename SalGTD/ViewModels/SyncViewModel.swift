import Foundation
import SwiftData
import Observation

@Observable
final class SyncViewModel {
    let authService: AuthService
    let networkMonitor: NetworkMonitor
    private let apiClient: APIClient
    private let syncEngine: SyncEngine
    private let offlineQueue: OfflineQueue
    private var webSocketClient: WebSocketClient?
    private let modelContainer: ModelContainer

    private(set) var isSyncing = false
    private(set) var lastSyncDate: Date?
    private(set) var syncError: String?

    var isAuthenticated: Bool { authService.isAuthenticated }

    private var syncTimer: Task<Void, Never>?
    private var syncDebounceTask: Task<Void, Never>?

    init(modelContainer: ModelContainer) {
        self.modelContainer = modelContainer
        self.authService = AuthService()
        self.networkMonitor = NetworkMonitor()
        self.apiClient = APIClient(authService: authService)
        self.offlineQueue = OfflineQueue(modelContainer: modelContainer)
        self.syncEngine = SyncEngine(apiClient: apiClient, offlineQueue: offlineQueue, modelContainer: modelContainer)

        networkMonitor.onConnectivityRestored = { [weak self] in
            Task { await self?.triggerSync() }
        }
    }

    func startSync() {
        guard authService.isAuthenticated else { return }
        Task { await triggerSync() }
        startPeriodicSync()
        connectWebSocket()
    }

    func stopSync() {
        syncTimer?.cancel()
        syncTimer = nil
        Task { await webSocketClient?.disconnect() }
    }

    func triggerSync() async {
        guard !isSyncing, networkMonitor.isConnected else { return }
        isSyncing = true
        syncError = nil

        do {
            try await syncEngine.sync()
            lastSyncDate = Date()
        } catch {
            syncError = error.localizedDescription
        }

        isSyncing = false
    }

    func forceFullSync() async {
        guard !isSyncing, networkMonitor.isConnected else { return }
        isSyncing = true
        syncError = nil

        do {
            try await syncEngine.fullSync()
            lastSyncDate = Date()
        } catch {
            syncError = error.localizedDescription
        }

        isSyncing = false
    }

    func login(serverURL: URL, email: String, password: String) async throws {
        let _ = try await authService.login(serverURL: serverURL, email: email, password: password)
        startSync()
    }

    func logout() async {
        stopSync()
        await authService.logout()
        // Clear local data
        let context = ModelContext(modelContainer)
        try? context.delete(model: GTDItem.self)
        try? context.delete(model: GTDNote.self)
        try? context.delete(model: GTDCalendarEvent.self)
        try? context.delete(model: GTDContext.self)
        try? context.delete(model: GTDEmail.self)
        try? context.delete(model: PendingMutation.self)
        try? context.delete(model: SyncState.self)
        try? context.save()
    }

    // MARK: - Offline Queue

    func enqueueMutation(entity: String, entityId: String, action: String, baseVersion: Int, data: [String: Any]?) async {
        await offlineQueue.enqueue(entity: entity, entityId: entityId, action: action, baseVersion: baseVersion, data: data)

        if networkMonitor.isConnected {
            await triggerSync()
        }
    }

    // MARK: - Private

    private func startPeriodicSync() {
        syncTimer?.cancel()
        syncTimer = Task {
            while !Task.isCancelled {
                try? await Task.sleep(for: .seconds(60))
                guard !Task.isCancelled else { break }
                await triggerSync()
            }
        }
    }

    private func connectWebSocket() {
        guard let serverURL = authService.serverURL,
              let token = authService.token else { return }

        // Use "salgtd-key" as default app key, matching the .env.production config
        let appKey = "salgtd-key"
        let client = WebSocketClient(serverURL: serverURL, appKey: appKey)
        self.webSocketClient = client

        Task {
            await client.connect(token: token)
            await setWebSocketSyncHandler(client)
        }
    }

    private func setWebSocketSyncHandler(_ client: WebSocketClient) async {
        await withCheckedContinuation { continuation in
            Task {
                await client.setOnSyncEvent { [weak self] in
                    // Debounce WebSocket events (500ms)
                    self?.syncDebounceTask?.cancel()
                    self?.syncDebounceTask = Task {
                        try? await Task.sleep(for: .milliseconds(500))
                        guard !Task.isCancelled else { return }
                        await self?.triggerSync()
                    }
                }
                continuation.resume()
            }
        }
    }

    private func setOnSyncEvent(for client: WebSocketClient) async {
        // The WebSocket client's onSyncEvent is set in connectWebSocket
    }
}

extension WebSocketClient {
    func setOnSyncEvent(_ handler: @escaping () async -> Void) {
        self.onSyncEvent = handler
    }
}
