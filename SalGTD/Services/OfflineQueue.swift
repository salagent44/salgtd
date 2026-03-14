import Foundation
import SwiftData

actor OfflineQueue {
    private let modelContainer: ModelContainer

    init(modelContainer: ModelContainer) {
        self.modelContainer = modelContainer
    }

    func enqueue(entity: String, entityId: String, action: String, baseVersion: Int, data: [String: Any]?) async {
        let context = ModelContext(modelContainer)

        // Coalesce: if there's already a pending mutation for this entity+id, update it
        let descriptor = FetchDescriptor<PendingMutation>(
            predicate: #Predicate { $0.entity == entity && $0.entityId == entityId }
        )

        if let existing = try? context.fetch(descriptor).first {
            if action == "delete" {
                // Delete trumps upsert
                existing.action = "delete"
                existing.data = nil
            } else {
                existing.action = action
                existing.data = encodeData(data)
            }
            existing.baseVersion = baseVersion
        } else {
            let mutation = PendingMutation(
                entity: entity,
                entityId: entityId,
                action: action,
                baseVersion: baseVersion,
                data: encodeData(data)
            )
            context.insert(mutation)
        }

        try? context.save()
    }

    func pendingMutations() async -> [[String: Any]] {
        let context = ModelContext(modelContainer)
        let descriptor = FetchDescriptor<PendingMutation>(
            sortBy: [SortDescriptor(\.createdAt)]
        )

        guard let mutations = try? context.fetch(descriptor) else { return [] }

        return mutations.map { mutation in
            var dict: [String: Any] = [
                "entity": mutation.entity,
                "action": mutation.action,
                "id": mutation.entityId,
                "base_version": mutation.baseVersion,
            ]
            if let data = mutation.data, let decoded = decodeData(data) {
                dict["data"] = decoded
            }
            return dict
        }
    }

    func clearAll() async {
        let context = ModelContext(modelContainer)
        do {
            try context.delete(model: PendingMutation.self)
            try context.save()
        } catch {
            // Best effort
        }
    }

    func hasPending() async -> Bool {
        let context = ModelContext(modelContainer)
        let descriptor = FetchDescriptor<PendingMutation>()
        return (try? context.fetchCount(descriptor)) ?? 0 > 0
    }

    private func encodeData(_ data: [String: Any]?) -> Data? {
        guard let data else { return nil }
        return try? JSONSerialization.data(withJSONObject: data)
    }

    private func decodeData(_ data: Data) -> [String: Any]? {
        return try? JSONSerialization.jsonObject(with: data) as? [String: Any]
    }
}
