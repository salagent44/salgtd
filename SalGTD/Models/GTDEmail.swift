import Foundation
import SwiftData

@Model
final class GTDEmail {
    @Attribute(.unique) var id: String
    var fromAddress: String?
    var fromName: String?
    var toAddress: String?
    var subject: String?
    var bodyText: String?
    var receivedAt: Date?
    var messageId: String?

    @Relationship(inverse: \GTDItem.email) var item: GTDItem?

    init(
        id: String = ULIDGenerator.generate(),
        fromAddress: String? = nil,
        fromName: String? = nil,
        toAddress: String? = nil,
        subject: String? = nil,
        bodyText: String? = nil,
        receivedAt: Date? = nil,
        messageId: String? = nil
    ) {
        self.id = id
        self.fromAddress = fromAddress
        self.fromName = fromName
        self.toAddress = toAddress
        self.subject = subject
        self.bodyText = bodyText
        self.receivedAt = receivedAt
        self.messageId = messageId
    }
}
