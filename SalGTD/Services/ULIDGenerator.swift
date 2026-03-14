import Foundation

enum ULIDGenerator {
    private static let encodingChars = Array("0123456789ABCDEFGHJKMNPQRSTVWXYZ")

    static func generate() -> String {
        let timestamp = UInt64(Date().timeIntervalSince1970 * 1000)
        var result = ""

        // Encode 48-bit timestamp into 10 characters
        var t = timestamp
        for _ in 0..<10 {
            result = String(encodingChars[Int(t & 0x1F)]) + result
            t >>= 5
        }

        // Encode 80 bits of randomness into 16 characters
        for _ in 0..<16 {
            result += String(encodingChars[Int.random(in: 0..<32)])
        }

        return result
    }
}
