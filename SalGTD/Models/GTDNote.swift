import Foundation
import SwiftData

@Model
final class GTDNote {
    @Attribute(.unique) var id: String
    var title: String
    var content: String
    var pinned: Bool
    var trashed: Bool
    var locked: Bool
    var tags: [String]
    var syncVersion: Int
    var pendingSync: Bool
    var isDeleted: Bool
    var createdAt: Date?
    var updatedAt: Date?

    init(
        id: String = ULIDGenerator.generate(),
        title: String = "",
        content: String = "",
        pinned: Bool = false,
        trashed: Bool = false,
        locked: Bool = false,
        tags: [String] = [],
        syncVersion: Int = 0,
        pendingSync: Bool = true,
        isDeleted: Bool = false,
        createdAt: Date? = Date(),
        updatedAt: Date? = Date()
    ) {
        self.id = id
        self.title = title
        self.content = content
        self.pinned = pinned
        self.trashed = trashed
        self.locked = locked
        self.tags = tags
        self.syncVersion = syncVersion
        self.pendingSync = pendingSync
        self.isDeleted = isDeleted
        self.createdAt = createdAt
        self.updatedAt = updatedAt
    }
}
