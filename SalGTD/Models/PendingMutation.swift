import Foundation
import SwiftData

@Model
final class PendingMutation {
    @Attribute(.unique) var id: String
    var entity: String // item, note, calendar_event, context
    var entityId: String
    var action: String // upsert, delete
    var baseVersion: Int
    var data: Data? // JSON-encoded mutation data
    var createdAt: Date

    init(
        id: String = UUID().uuidString,
        entity: String,
        entityId: String,
        action: String,
        baseVersion: Int = 0,
        data: Data? = nil,
        createdAt: Date = Date()
    ) {
        self.id = id
        self.entity = entity
        self.entityId = entityId
        self.action = action
        self.baseVersion = baseVersion
        self.data = data
        self.createdAt = createdAt
    }
}
