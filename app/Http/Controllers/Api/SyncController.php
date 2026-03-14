<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Context;
use App\Models\Item;
use App\Models\Note;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncController extends Controller
{
    public function full()
    {
        $currentVersion = (int) DB::table('sync_cursor')->where('id', 1)->value('version');

        return response()->json([
            'version' => $currentVersion,
            'items' => Item::withTrashed()->with(['tags', 'email'])->get()->map(fn ($i) => $this->formatItem($i)),
            'notes' => Note::withTrashed()->with('tags')->get()->map(fn ($n) => $this->formatNote($n)),
            'calendar_events' => CalendarEvent::withTrashed()->get()->map(fn ($e) => $this->formatEvent($e)),
            'contexts' => Context::withTrashed()->get()->map(fn ($c) => $this->formatContext($c)),
            'settings' => Setting::all()->map(fn ($s) => [
                'key' => $s->key,
                'value' => $s->value,
                'sync_version' => $s->sync_version,
            ]),
        ]);
    }

    public function pull(Request $request)
    {
        $request->validate([
            'since_version' => 'required|integer|min:0',
        ]);

        $since = (int) $request->since_version;
        $currentVersion = (int) DB::table('sync_cursor')->where('id', 1)->value('version');

        return response()->json([
            'version' => $currentVersion,
            'items' => Item::withTrashed()->with(['tags', 'email'])
                ->where('sync_version', '>', $since)->get()
                ->map(fn ($i) => $this->formatItem($i)),
            'notes' => Note::withTrashed()->with('tags')
                ->where('sync_version', '>', $since)->get()
                ->map(fn ($n) => $this->formatNote($n)),
            'calendar_events' => CalendarEvent::withTrashed()
                ->where('sync_version', '>', $since)->get()
                ->map(fn ($e) => $this->formatEvent($e)),
            'contexts' => Context::withTrashed()
                ->where('sync_version', '>', $since)->get()
                ->map(fn ($c) => $this->formatContext($c)),
            'settings' => Setting::where('sync_version', '>', $since)->get()
                ->map(fn ($s) => [
                    'key' => $s->key,
                    'value' => $s->value,
                    'sync_version' => $s->sync_version,
                ]),
        ]);
    }

    public function push(Request $request)
    {
        $request->validate([
            'mutations' => 'required|array|min:1',
            'mutations.*.entity' => 'required|in:item,note,calendar_event,context,setting',
            'mutations.*.action' => 'required|in:upsert,delete',
            'mutations.*.id' => 'required|string',
            'mutations.*.base_version' => 'sometimes|integer',
            'mutations.*.data' => 'sometimes|array',
        ]);

        $results = [];

        DB::transaction(function () use ($request, &$results) {
            foreach ($request->mutations as $mutation) {
                $results[] = $this->processMutation($mutation);
            }
        });

        $currentVersion = (int) DB::table('sync_cursor')->where('id', 1)->value('version');

        return response()->json([
            'version' => $currentVersion,
            'results' => $results,
        ]);
    }

    protected function processMutation(array $mutation): array
    {
        $entity = $mutation['entity'];
        $action = $mutation['action'];
        $id = $mutation['id'];
        $baseVersion = $mutation['base_version'] ?? 0;
        $data = $mutation['data'] ?? [];

        $model = $this->resolveModel($entity, $id);
        $status = 'applied';

        if ($action === 'delete') {
            if ($model) {
                if ($model->sync_version > $baseVersion && $baseVersion > 0) {
                    $status = 'conflict_overwritten';
                }
                $model->delete(); // soft delete
            } else {
                $status = 'not_found';
            }
        } else {
            // upsert
            if ($model) {
                if ($model->sync_version > $baseVersion && $baseVersion > 0) {
                    $status = 'conflict_overwritten';
                }
                if ($model->trashed()) {
                    $model->restore();
                }
                $this->updateModel($entity, $model, $data);
            } else {
                $this->createModel($entity, $id, $data);
                $status = 'created';
            }
        }

        return [
            'entity' => $entity,
            'id' => $id,
            'status' => $status,
        ];
    }

    protected function resolveModel(string $entity, string $id)
    {
        return match ($entity) {
            'item' => Item::withTrashed()->find($id),
            'note' => Note::withTrashed()->find($id),
            'calendar_event' => CalendarEvent::withTrashed()->find($id),
            'context' => Context::withTrashed()->find($id),
            'setting' => Setting::find($id),
            default => null,
        };
    }

    protected function createModel(string $entity, string $id, array $data): void
    {
        switch ($entity) {
            case 'item':
                $tags = $data['tags'] ?? [];
                unset($data['tags']);
                $item = Item::create(array_merge($data, ['id' => $id]));
                foreach ($tags as $tag) {
                    $item->tags()->firstOrCreate(['tag' => $tag]);
                }
                break;
            case 'note':
                $noteData = array_merge($data, ['id' => $id]);
                $noteData['title'] = $noteData['title'] ?? '';
                $noteData['content'] = $noteData['content'] ?? '';
                unset($noteData['tags']);
                $note = Note::create($noteData);
                if (! empty($data['tags'])) {
                    foreach ($data['tags'] as $tag) {
                        $note->tags()->firstOrCreate(['tag' => $tag]);
                    }
                }
                break;
            case 'calendar_event':
                CalendarEvent::create(array_merge($data, ['id' => $id]));
                break;
            case 'context':
                Context::create(array_merge($data, ['id' => $id]));
                break;
            case 'setting':
                Setting::create(['key' => $id, 'value' => $data['value'] ?? null]);
                break;
        }
    }

    protected function updateModel(string $entity, $model, array $data): void
    {
        // Handle tags separately for items and notes
        if (in_array($entity, ['item', 'note']) && array_key_exists('tags', $data)) {
            $tags = $data['tags'];
            unset($data['tags']);
            $model->tags()->delete();
            foreach ($tags as $tag) {
                $model->tags()->create(['tag' => $tag]);
            }
        }

        if ($entity === 'setting') {
            $model->update(['value' => $data['value'] ?? null]);
        } else {
            $model->update($data);
        }
    }

    protected function formatItem($item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'status' => $item->status,
            'context' => $item->context,
            'waiting_for' => $item->waiting_for,
            'waiting_date' => $item->waiting_date?->format('Y-m-d'),
            'tickler_date' => $item->tickler_date?->format('Y-m-d'),
            'notes' => $item->notes,
            'sort_order' => $item->sort_order,
            'flagged' => $item->flagged,
            'completed_at' => $item->completed_at?->toISOString(),
            'original_status' => $item->original_status,
            'goal' => $item->goal,
            'project_id' => $item->project_id,
            'tags' => $item->tags->pluck('tag')->values(),
            'email' => $item->email ? [
                'id' => $item->email->id,
                'from_address' => $item->email->from_address,
                'from_name' => $item->email->from_name,
                'to_address' => $item->email->to_address,
                'subject' => $item->email->subject,
                'body_text' => $item->email->body_text,
                'received_at' => $item->email->received_at?->toISOString(),
                'message_id' => $item->email->message_id,
            ] : null,
            'sync_version' => $item->sync_version,
            'deleted' => ! is_null($item->deleted_at),
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    protected function formatNote($note): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'content' => $note->content,
            'pinned' => $note->pinned,
            'trashed' => $note->trashed,
            'locked' => $note->locked,
            'tags' => $note->tags->pluck('tag')->values(),
            'sync_version' => $note->sync_version,
            'deleted' => ! is_null($note->deleted_at),
            'created_at' => $note->created_at?->toISOString(),
            'updated_at' => $note->updated_at?->toISOString(),
        ];
    }

    protected function formatEvent($event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'event_date' => $event->event_date?->format('Y-m-d'),
            'end_date' => $event->end_date?->format('Y-m-d'),
            'event_time' => $event->event_time,
            'end_time' => $event->end_time,
            'description' => $event->description,
            'color' => $event->color,
            'recurrence' => $event->recurrence,
            'sync_version' => $event->sync_version,
            'deleted' => ! is_null($event->deleted_at),
            'created_at' => $event->created_at?->toISOString(),
            'updated_at' => $event->updated_at?->toISOString(),
        ];
    }

    protected function formatContext($context): array
    {
        return [
            'id' => $context->id,
            'name' => $context->name,
            'built_in' => $context->built_in,
            'sort_order' => $context->sort_order,
            'sync_version' => $context->sync_version,
            'deleted' => ! is_null($context->deleted_at),
        ];
    }
}
