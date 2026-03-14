import Foundation
import SwiftData

@Model
final class GTDItem {
    @Attribute(.unique) var id: String
    var title: String
    var status: String // inbox, next-action, project, waiting, someday, tickler, done, trash
    var context: String?
    var waitingFor: String?
    var waitingDate: Date?
    var ticklerDate: Date?
    var notes: String?
    var sortOrder: Int
    var flagged: Bool
    var completedAt: Date?
    var originalStatus: String?
    var goal: String?
    var projectId: String?
    var tags: [String]
    var syncVersion: Int
    var pendingSync: Bool
    var isDeleted: Bool
    var createdAt: Date?
    var updatedAt: Date?

    @Relationship(deleteRule: .cascade) var email: GTDEmail?

    init(
        id: String = ULIDGenerator.generate(),
        title: String,
        status: String = "inbox",
        context: String? = nil,
        waitingFor: String? = nil,
        waitingDate: Date? = nil,
        ticklerDate: Date? = nil,
        notes: String? = nil,
        sortOrder: Int = 0,
        flagged: Bool = false,
        completedAt: Date? = nil,
        originalStatus: String? = nil,
        goal: String? = nil,
        projectId: String? = nil,
        tags: [String] = [],
        syncVersion: Int = 0,
        pendingSync: Bool = true,
        isDeleted: Bool = false,
        createdAt: Date? = Date(),
        updatedAt: Date? = Date(),
        email: GTDEmail? = nil
    ) {
        self.id = id
        self.title = title
        self.status = status
        self.context = context
        self.waitingFor = waitingFor
        self.waitingDate = waitingDate
        self.ticklerDate = ticklerDate
        self.notes = notes
        self.sortOrder = sortOrder
        self.flagged = flagged
        self.completedAt = completedAt
        self.originalStatus = originalStatus
        self.goal = goal
        self.projectId = projectId
        self.tags = tags
        self.syncVersion = syncVersion
        self.pendingSync = pendingSync
        self.isDeleted = isDeleted
        self.createdAt = createdAt
        self.updatedAt = updatedAt
        self.email = email
    }

    var statusDisplayName: String {
        switch status {
        case "next-action": return "Next Action"
        case "inbox": return "Inbox"
        case "project": return "Project"
        case "waiting": return "Waiting"
        case "someday": return "Someday"
        case "tickler": return "Tickler"
        case "done": return "Done"
        case "trash": return "Trash"
        default: return status.capitalized
        }
    }
}
