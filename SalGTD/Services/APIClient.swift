import Foundation

actor APIClient {
    private let authService: AuthService

    init(authService: AuthService) {
        self.authService = authService
    }

    private var baseURL: URL? { authService.serverURL }
    private var token: String? { authService.token }

    // MARK: - Sync Endpoints

    func fullSync() async throws -> SyncResponse {
        try await get("api/sync/full")
    }

    func pull(sinceVersion: Int) async throws -> SyncResponse {
        try await post("api/sync/pull", body: ["since_version": sinceVersion])
    }

    func push(mutations: [[String: Any]]) async throws -> PushResponse {
        try await post("api/sync/push", body: ["mutations": mutations])
    }

    func processItem(id: String, data: [String: Any]) async throws -> [String: Any] {
        try await postRaw("api/items/\(id)/process", body: data)
    }

    func moveToInbox(id: String) async throws -> [String: Any] {
        try await postRaw("api/items/\(id)/move-to-inbox", body: [:])
    }

    // MARK: - HTTP

    private func get<T: Decodable>(_ path: String) async throws -> T {
        let request = try makeRequest(path: path, method: "GET")
        let (data, response) = try await URLSession.shared.data(for: request)
        try validateResponse(response)
        return try JSONDecoder.api.decode(T.self, from: data)
    }

    private func post<T: Decodable>(_ path: String, body: Any) async throws -> T {
        var request = try makeRequest(path: path, method: "POST")
        request.httpBody = try JSONSerialization.data(withJSONObject: body)
        let (data, response) = try await URLSession.shared.data(for: request)
        try validateResponse(response)
        return try JSONDecoder.api.decode(T.self, from: data)
    }

    private func postRaw(_ path: String, body: Any) async throws -> [String: Any] {
        var request = try makeRequest(path: path, method: "POST")
        request.httpBody = try JSONSerialization.data(withJSONObject: body)
        let (data, response) = try await URLSession.shared.data(for: request)
        try validateResponse(response)
        guard let json = try JSONSerialization.jsonObject(with: data) as? [String: Any] else {
            throw APIError.invalidResponse
        }
        return json
    }

    private func makeRequest(path: String, method: String) throws -> URLRequest {
        guard let baseURL else { throw APIError.notConfigured }
        guard let token else { throw APIError.notAuthenticated }

        var request = URLRequest(url: baseURL.appendingPathComponent(path))
        request.httpMethod = method
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.timeoutInterval = 30
        return request
    }

    private func validateResponse(_ response: URLResponse) throws {
        guard let httpResponse = response as? HTTPURLResponse else {
            throw APIError.invalidResponse
        }
        switch httpResponse.statusCode {
        case 200...299: return
        case 401: throw APIError.notAuthenticated
        case 422: throw APIError.validationError
        default: throw APIError.serverError(httpResponse.statusCode)
        }
    }
}

enum APIError: LocalizedError {
    case notConfigured
    case notAuthenticated
    case invalidResponse
    case validationError
    case serverError(Int)

    var errorDescription: String? {
        switch self {
        case .notConfigured: return "Server not configured"
        case .notAuthenticated: return "Not authenticated"
        case .invalidResponse: return "Invalid server response"
        case .validationError: return "Validation error"
        case .serverError(let code): return "Server error (\(code))"
        }
    }
}

// MARK: - Response Types

struct SyncResponse: Decodable {
    let version: Int
    let items: [SyncItem]
    let notes: [SyncNote]
    let calendarEvents: [SyncCalendarEvent]
    let contexts: [SyncContext]
    let settings: [SyncSetting]?

    enum CodingKeys: String, CodingKey {
        case version, items, notes, settings
        case calendarEvents = "calendar_events"
        case contexts
    }
}

struct SyncItem: Decodable {
    let id: String
    let title: String
    let status: String
    let context: String?
    let waitingFor: String?
    let waitingDate: String?
    let ticklerDate: String?
    let notes: String?
    let sortOrder: Int?
    let flagged: Bool?
    let completedAt: String?
    let originalStatus: String?
    let goal: String?
    let projectId: String?
    let tags: [String]?
    let email: SyncEmail?
    let syncVersion: Int
    let deleted: Bool

    enum CodingKeys: String, CodingKey {
        case id, title, status, context, notes, tags, email, deleted, goal
        case waitingFor = "waiting_for"
        case waitingDate = "waiting_date"
        case ticklerDate = "tickler_date"
        case sortOrder = "sort_order"
        case flagged
        case completedAt = "completed_at"
        case originalStatus = "original_status"
        case projectId = "project_id"
        case syncVersion = "sync_version"
    }
}

struct SyncEmail: Decodable {
    let id: String
    let fromAddress: String?
    let fromName: String?
    let toAddress: String?
    let subject: String?
    let bodyText: String?
    let receivedAt: String?
    let messageId: String?

    enum CodingKeys: String, CodingKey {
        case id, subject
        case fromAddress = "from_address"
        case fromName = "from_name"
        case toAddress = "to_address"
        case bodyText = "body_text"
        case receivedAt = "received_at"
        case messageId = "message_id"
    }
}

struct SyncNote: Decodable {
    let id: String
    let title: String
    let content: String
    let pinned: Bool
    let trashed: Bool
    let locked: Bool
    let tags: [String]?
    let syncVersion: Int
    let deleted: Bool

    enum CodingKeys: String, CodingKey {
        case id, title, content, pinned, trashed, locked, tags, deleted
        case syncVersion = "sync_version"
    }
}

struct SyncCalendarEvent: Decodable {
    let id: String
    let title: String
    let eventDate: String?
    let endDate: String?
    let eventTime: String?
    let endTime: String?
    let description: String?
    let color: String?
    let recurrence: String?
    let syncVersion: Int
    let deleted: Bool

    enum CodingKeys: String, CodingKey {
        case id, title, description, color, recurrence, deleted
        case eventDate = "event_date"
        case endDate = "end_date"
        case eventTime = "event_time"
        case endTime = "end_time"
        case syncVersion = "sync_version"
    }
}

struct SyncContext: Decodable {
    let id: Int
    let name: String
    let builtIn: Bool?
    let sortOrder: Int?
    let syncVersion: Int
    let deleted: Bool

    enum CodingKeys: String, CodingKey {
        case id, name, deleted
        case builtIn = "built_in"
        case sortOrder = "sort_order"
        case syncVersion = "sync_version"
    }
}

struct SyncSetting: Decodable {
    let key: String
    let value: String?
    let syncVersion: Int

    enum CodingKeys: String, CodingKey {
        case key, value
        case syncVersion = "sync_version"
    }
}

struct PushResponse: Decodable {
    let version: Int
    let results: [PushResult]
}

struct PushResult: Decodable {
    let entity: String
    let id: String
    let status: String
}

extension JSONDecoder {
    static let api: JSONDecoder = {
        let decoder = JSONDecoder()
        return decoder
    }()
}
