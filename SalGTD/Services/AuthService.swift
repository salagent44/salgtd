import Foundation
import Security

@Observable
final class AuthService {
    private(set) var token: String?
    private(set) var serverURL: URL?

    private let tokenKey = "com.salgtd.api-token"
    private let serverURLKey = "com.salgtd.server-url"

    var isAuthenticated: Bool { token != nil }

    init() {
        self.token = Self.loadFromKeychain(key: "com.salgtd.api-token")
        if let urlString = UserDefaults.standard.string(forKey: serverURLKey) {
            self.serverURL = URL(string: urlString)
        }
    }

    func login(serverURL: URL, email: String, password: String) async throws -> (token: String, userName: String) {
        self.serverURL = serverURL
        UserDefaults.standard.set(serverURL.absoluteString, forKey: serverURLKey)

        var request = URLRequest(url: serverURL.appendingPathComponent("api/auth/login"))
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let body: [String: String] = [
            "email": email,
            "password": password,
            "device_name": "ios"
        ]
        request.httpBody = try JSONSerialization.data(withJSONObject: body)

        let (data, response) = try await URLSession.shared.data(for: request)

        guard let httpResponse = response as? HTTPURLResponse else {
            throw AuthError.invalidResponse
        }

        guard httpResponse.statusCode == 200 else {
            if httpResponse.statusCode == 422 {
                throw AuthError.invalidCredentials
            }
            throw AuthError.serverError(httpResponse.statusCode)
        }

        guard let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
              let token = json["token"] as? String,
              let user = json["user"] as? [String: Any],
              let userName = user["name"] as? String else {
            throw AuthError.invalidResponse
        }

        self.token = token
        Self.saveToKeychain(key: tokenKey, value: token)

        return (token, userName)
    }

    func logout() async {
        if let serverURL, let token {
            var request = URLRequest(url: serverURL.appendingPathComponent("api/auth/logout"))
            request.httpMethod = "POST"
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
            request.setValue("application/json", forHTTPHeaderField: "Accept")
            try? await URLSession.shared.data(for: request)
        }

        self.token = nil
        Self.deleteFromKeychain(key: tokenKey)
    }

    // MARK: - Keychain

    private static func saveToKeychain(key: String, value: String) {
        let data = Data(value.utf8)
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrAccount as String: key,
        ]
        SecItemDelete(query as CFDictionary)

        let attributes: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrAccount as String: key,
            kSecValueData as String: data,
            kSecAttrAccessible as String: kSecAttrAccessibleAfterFirstUnlock,
        ]
        SecItemAdd(attributes as CFDictionary, nil)
    }

    private static func loadFromKeychain(key: String) -> String? {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrAccount as String: key,
            kSecReturnData as String: true,
            kSecMatchLimit as String: kSecMatchLimitOne,
        ]
        var result: AnyObject?
        let status = SecItemCopyMatching(query as CFDictionary, &result)
        guard status == errSecSuccess, let data = result as? Data else { return nil }
        return String(data: data, encoding: .utf8)
    }

    private static func deleteFromKeychain(key: String) {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrAccount as String: key,
        ]
        SecItemDelete(query as CFDictionary)
    }
}

enum AuthError: LocalizedError {
    case invalidCredentials
    case invalidResponse
    case serverError(Int)

    var errorDescription: String? {
        switch self {
        case .invalidCredentials: return "Invalid email or password"
        case .invalidResponse: return "Invalid server response"
        case .serverError(let code): return "Server error (\(code))"
        }
    }
}
