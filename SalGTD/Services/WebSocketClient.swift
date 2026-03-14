import Foundation

actor WebSocketClient {
    private var task: URLSessionWebSocketTask?
    private var urlSession: URLSession?
    private var isConnected = false
    private var pingTimer: Task<Void, Never>?
    private var reconnectTask: Task<Void, Never>?

    var onSyncEvent: (() async -> Void)?

    private let serverURL: URL
    private let appKey: String

    init(serverURL: URL, appKey: String) {
        self.serverURL = serverURL
        self.appKey = appKey
    }

    func connect(token: String) {
        disconnect()

        // Build WebSocket URL — Reverb uses the Pusher protocol
        // WebSocket path: /app/{key}?protocol=7&client=js&version=8.4.0-rc2&flash=false
        var components = URLComponents(url: serverURL, resolvingAgainstBaseURL: false)!
        let isSecure = components.scheme == "https"
        components.scheme = isSecure ? "wss" : "ws"
        components.path = "/app/\(appKey)"
        components.queryItems = [
            URLQueryItem(name: "protocol", value: "7"),
            URLQueryItem(name: "client", value: "js"),
            URLQueryItem(name: "version", value: "8.4.0"),
            URLQueryItem(name: "flash", value: "false"),
        ]

        guard let wsURL = components.url else { return }

        urlSession = URLSession(configuration: .default)
        task = urlSession?.webSocketTask(with: wsURL)
        task?.resume()

        isConnected = true
        startReceiving()
        startPingTimer()

        // Subscribe to private-sync channel after connection
        Task {
            try? await Task.sleep(for: .milliseconds(500))
            await subscribeToSync(token: token)
        }
    }

    func disconnect() {
        pingTimer?.cancel()
        pingTimer = nil
        reconnectTask?.cancel()
        reconnectTask = nil
        task?.cancel(with: .goingAway, reason: nil)
        task = nil
        isConnected = false
    }

    // MARK: - Private

    private func subscribeToSync(token: String) {
        // For private channels, we need to get an auth signature from the server
        // We'll request it from the broadcasting/auth endpoint
        Task {
            guard let authSignature = await getAuthSignature(channel: "private-sync", token: token) else {
                return
            }

            let subscribeMessage: [String: Any] = [
                "event": "pusher:subscribe",
                "data": [
                    "auth": authSignature,
                    "channel": "private-sync"
                ]
            ]

            if let data = try? JSONSerialization.data(withJSONObject: subscribeMessage),
               let string = String(data: data, encoding: .utf8) {
                try? await task?.send(.string(string))
            }
        }
    }

    private func getAuthSignature(channel: String, token: String) async -> String? {
        var components = URLComponents(url: serverURL, resolvingAgainstBaseURL: false)!
        components.scheme = serverURL.scheme
        components.path = "/broadcasting/auth"

        guard let url = components.url else { return nil }

        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        // Get socket_id from connection
        let socketId = await getSocketId()
        let body: [String: String] = [
            "socket_id": socketId ?? "",
            "channel_name": channel,
        ]
        request.httpBody = try? JSONSerialization.data(withJSONObject: body)

        guard let (data, response) = try? await URLSession.shared.data(for: request),
              let httpResponse = response as? HTTPURLResponse,
              httpResponse.statusCode == 200,
              let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
              let auth = json["auth"] as? String else {
            return nil
        }

        return auth
    }

    private var socketId: String?

    private func getSocketId() -> String? {
        return socketId
    }

    private func startReceiving() {
        Task {
            while isConnected {
                guard let task else { break }
                do {
                    let message = try await task.receive()
                    await handleMessage(message)
                } catch {
                    isConnected = false
                    break
                }
            }
        }
    }

    private func handleMessage(_ message: URLSessionWebSocketTask.Message) async {
        switch message {
        case .string(let text):
            guard let data = text.data(using: .utf8),
                  let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
                  let event = json["event"] as? String else {
                return
            }

            switch event {
            case "pusher:connection_established":
                if let dataString = json["data"] as? String,
                   let dataJson = try? JSONSerialization.jsonObject(with: Data(dataString.utf8)) as? [String: Any],
                   let sid = dataJson["socket_id"] as? String {
                    socketId = sid
                }

            case "pusher:subscription_succeeded":
                break // Successfully subscribed

            case "pusher:ping":
                let pong = #"{"event":"pusher:pong","data":{}}"#
                try? await task?.send(.string(pong))

            case ".SyncUpdated", "SyncUpdated":
                await onSyncEvent?()

            default:
                break
            }

        case .data:
            break

        @unknown default:
            break
        }
    }

    private func startPingTimer() {
        pingTimer = Task {
            while !Task.isCancelled {
                try? await Task.sleep(for: .seconds(30))
                guard !Task.isCancelled, isConnected else { break }
                let ping = #"{"event":"pusher:ping","data":{}}"#
                try? await task?.send(.string(ping))
            }
        }
    }
}
