<?php

namespace App\Http\Controllers;

use App\Models\ChecklistItem;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChecklistItemController extends Controller
{
    public function store(Request $request, Item $item)
    {
        $request->validate([
            'title' => 'required|string|max:500',
        ]);

        $maxOrder = $item->checklistItems()->max('sort_order') ?? -1;

        $item->checklistItems()->create([
            'id' => Str::ulid(),
            'title' => $request->title,
            'sort_order' => $maxOrder + 1,
        ]);

        return back();
    }

    public function update(Request $request, ChecklistItem $checklistItem)
    {
        $request->validate([
            'title' => 'sometimes|string|max:500',
            'completed' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $checklistItem->update($request->only(['title', 'completed', 'sort_order']));

        return back();
    }

    public function destroy(ChecklistItem $checklistItem)
    {
        $checklistItem->delete();

        return back();
    }

    public function toggle(ChecklistItem $checklistItem)
    {
        $checklistItem->update(['completed' => ! $checklistItem->completed]);

        return back();
    }

    public function reorder(Request $request, Item $item)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|string',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->items as $entry) {
            $ci = ChecklistItem::where('id', $entry['id'])
                ->where('item_id', $item->id)
                ->first();
            if ($ci) {
                $ci->update(['sort_order' => $entry['sort_order']]);
            }
        }

        return back();
    }
}
