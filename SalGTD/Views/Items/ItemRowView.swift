import SwiftUI

struct ItemRowView: View {
    let item: GTDItem

    var body: some View {
        HStack {
            VStack(alignment: .leading, spacing: 4) {
                HStack(spacing: 6) {
                    Text(item.title)
                        .lineLimit(2)
                        .strikethrough(item.status == "done")
                        .foregroundStyle(item.status == "done" ? .secondary : .primary)

                    if item.flagged {
                        Image(systemName: "flag.fill")
                            .font(.caption)
                            .foregroundStyle(.orange)
                    }

                    if item.pendingSync {
                        Image(systemName: "arrow.triangle.2.circlepath")
                            .font(.caption2)
                            .foregroundStyle(.secondary)
                    }
                }

                HStack(spacing: 8) {
                    if let context = item.context, !context.isEmpty {
                        Label(context, systemImage: "at")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }

                    if item.status == "waiting", let waitingFor = item.waitingFor {
                        Label(waitingFor, systemImage: "person")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }

                    if item.status == "project" {
                        Label("Project", systemImage: "folder")
                            .font(.caption)
                            .foregroundStyle(.blue)
                    }

                    if let email = item.email {
                        Label(email.fromAddress ?? "email", systemImage: "envelope")
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }

                    if !item.tags.isEmpty {
                        HStack(spacing: 2) {
                            Image(systemName: "tag")
                                .font(.caption2)
                            Text(item.tags.joined(separator: ", "))
                                .font(.caption)
                        }
                        .foregroundStyle(.secondary)
                    }
                }
            }

            Spacer()

            statusBadge
        }
        .padding(.vertical, 2)
    }

    @ViewBuilder
    private var statusBadge: some View {
        Text(item.statusDisplayName)
            .font(.caption2)
            .fontWeight(.medium)
            .padding(.horizontal, 8)
            .padding(.vertical, 3)
            .background(statusColor.opacity(0.15))
            .foregroundStyle(statusColor)
            .clipShape(Capsule())
    }

    private var statusColor: Color {
        switch item.status {
        case "next-action": return .blue
        case "waiting": return .orange
        case "project": return .purple
        case "someday": return .gray
        case "tickler": return .cyan
        case "done": return .green
        case "inbox": return .red
        default: return .secondary
        }
    }
}
