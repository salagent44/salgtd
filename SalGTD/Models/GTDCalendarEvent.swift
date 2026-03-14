import Foundation
import SwiftData

@Model
final class GTDCalendarEvent {
    @Attribute(.unique) var id: String
    var title: String
    var eventDate: Date
    var endDate: Date?
    var eventTime: String?
    var endTime: String?
    var eventDescription: String
    var color: String
    var recurrence: String?
    var syncVersion: Int
    var pendingSync: Bool
    var isDeleted: Bool
    var createdAt: Date?
    var updatedAt: Date?

    init(
        id: String = ULIDGenerator.generate(),
        title: String,
        eventDate: Date,
        endDate: Date? = nil,
        eventTime: String? = nil,
        endTime: String? = nil,
        eventDescription: String = "",
        color: String = "blue",
        recurrence: String? = nil,
        syncVersion: Int = 0,
        pendingSync: Bool = true,
        isDeleted: Bool = false,
        createdAt: Date? = Date(),
        updatedAt: Date? = Date()
    ) {
        self.id = id
        self.title = title
        self.eventDate = eventDate
        self.endDate = endDate
        self.eventTime = eventTime
        self.endTime = endTime
        self.eventDescription = eventDescription
        self.color = color
        self.recurrence = recurrence
        self.syncVersion = syncVersion
        self.pendingSync = pendingSync
        self.isDeleted = isDeleted
        self.createdAt = createdAt
        self.updatedAt = updatedAt
    }
}
