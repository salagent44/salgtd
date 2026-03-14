import SwiftUI
import SwiftData

struct CalendarView: View {
    @Environment(\.modelContext) private var modelContext
    @Environment(SyncViewModel.self) private var syncViewModel

    @Query(filter: #Predicate<GTDCalendarEvent> { !$0.isDeleted },
           sort: \GTDCalendarEvent.eventDate)
    private var events: [GTDCalendarEvent]

    @State private var selectedDate = Date()
    @State private var showingAddEvent = false
    @State private var newEventTitle = ""
    @State private var newEventDate = Date()
    @State private var selectedEvent: GTDCalendarEvent?

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                DatePicker("Select date", selection: $selectedDate, displayedComponents: .date)
                    .datePickerStyle(.graphical)
                    .padding(.horizontal)

                Divider()

                let dayEvents = eventsForDate(selectedDate)
                if dayEvents.isEmpty {
                    ContentUnavailableView {
                        Label("No Events", systemImage: "calendar")
                    } description: {
                        Text("No events on this day")
                    }
                    .frame(maxHeight: .infinity)
                } else {
                    List {
                        ForEach(dayEvents) { event in
                            EventRow(event: event)
                                .swipeActions(edge: .trailing) {
                                    Button(role: .destructive) {
                                        deleteEvent(event)
                                    } label: {
                                        Label("Delete", systemImage: "trash")
                                    }
                                }
                                .onTapGesture {
                                    selectedEvent = event
                                }
                        }
                    }
                    .listStyle(.plain)
                }
            }
            .navigationTitle("Calendar")
            .toolbar {
                ToolbarItem(placement: .primaryAction) {
                    Button {
                        newEventDate = selectedDate
                        showingAddEvent = true
                    } label: {
                        Image(systemName: "plus")
                    }
                }
            }
            .alert("New Event", isPresented: $showingAddEvent) {
                TextField("Event title", text: $newEventTitle)
                Button("Add") { addEvent() }
                Button("Cancel", role: .cancel) { newEventTitle = "" }
            }
            .sheet(item: $selectedEvent) { event in
                EventDetailSheet(event: event)
            }
        }
    }

    private func eventsForDate(_ date: Date) -> [GTDCalendarEvent] {
        let calendar = Foundation.Calendar.current
        return events.filter { event in
            calendar.isDate(event.eventDate, inSameDayAs: date)
        }
    }

    private func addEvent() {
        guard !newEventTitle.trimmingCharacters(in: .whitespaces).isEmpty else { return }
        let event = GTDCalendarEvent(title: newEventTitle, eventDate: newEventDate)
        modelContext.insert(event)
        try? modelContext.save()

        let dateFormatter = DateFormatter()
        dateFormatter.dateFormat = "yyyy-MM-dd"

        Task {
            await syncViewModel.enqueueMutation(
                entity: "calendar_event",
                entityId: event.id,
                action: "upsert",
                baseVersion: 0,
                data: [
                    "title": event.title,
                    "event_date": dateFormatter.string(from: event.eventDate),
                    "color": "blue"
                ]
            )
        }

        newEventTitle = ""
    }

    private func deleteEvent(_ event: GTDCalendarEvent) {
        event.isDeleted = true
        event.pendingSync = true
        try? modelContext.save()

        Task {
            await syncViewModel.enqueueMutation(
                entity: "calendar_event",
                entityId: event.id,
                action: "delete",
                baseVersion: event.syncVersion,
                data: nil
            )
        }
    }
}

struct EventRow: View {
    let event: GTDCalendarEvent

    var body: some View {
        HStack {
            Circle()
                .fill(colorForName(event.color))
                .frame(width: 10, height: 10)

            VStack(alignment: .leading, spacing: 2) {
                Text(event.title)
                    .font(.body)

                if let time = event.eventTime {
                    Text(time)
                        .font(.caption)
                        .foregroundStyle(.secondary)
                }
            }

            Spacer()

            if event.recurrence != nil {
                Image(systemName: "repeat")
                    .font(.caption)
                    .foregroundStyle(.secondary)
            }
        }
    }

    private func colorForName(_ name: String) -> Color {
        switch name {
        case "red": return .red
        case "orange": return .orange
        case "yellow": return .yellow
        case "green": return .green
        case "blue": return .blue
        case "purple": return .purple
        case "pink": return .pink
        default: return .blue
        }
    }
}

struct EventDetailSheet: View {
    @Environment(\.dismiss) private var dismiss
    @Environment(\.modelContext) private var modelContext
    @Environment(SyncViewModel.self) private var syncViewModel

    @Bindable var event: GTDCalendarEvent
    @State private var title: String = ""
    @State private var description: String = ""

    var body: some View {
        NavigationStack {
            Form {
                Section {
                    TextField("Title", text: $title)
                }
                Section("Description") {
                    TextEditor(text: $description)
                        .frame(minHeight: 100)
                }
            }
            .navigationTitle("Event")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .confirmationAction) {
                    Button("Save") {
                        event.title = title
                        event.eventDescription = description
                        event.pendingSync = true
                        try? modelContext.save()

                        Task {
                            await syncViewModel.enqueueMutation(
                                entity: "calendar_event",
                                entityId: event.id,
                                action: "upsert",
                                baseVersion: event.syncVersion,
                                data: ["title": title, "description": description]
                            )
                        }
                        dismiss()
                    }
                }
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }
                }
            }
            .onAppear {
                title = event.title
                description = event.eventDescription
            }
        }
    }
}
