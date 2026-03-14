<?php

namespace App\Http\Controllers;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateStep;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChecklistTemplateController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|string|exists:items,id',
        ]);

        $item = Item::with('checklistItems')->findOrFail($validated['item_id']);

        $template = ChecklistTemplate::create([
            'id' => Str::ulid(),
            'name' => $item->title,
        ]);

        foreach ($item->checklistItems as $ci) {
            ChecklistTemplateStep::create([
                'id' => Str::ulid(),
                'checklist_template_id' => $template->id,
                'title' => $ci->title,
                'sort_order' => $ci->sort_order,
            ]);
        }

        return back();
    }

    public function apply(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|string|exists:checklist_templates,id',
        ]);

        $template = ChecklistTemplate::with('steps')->findOrFail($validated['template_id']);

        $item = Item::create([
            'id' => Str::ulid(),
            'title' => $template->name,
            'status' => 'checklist',
        ]);

        foreach ($template->steps as $step) {
            ChecklistItem::create([
                'id' => Str::ulid(),
                'item_id' => $item->id,
                'title' => $step->title,
                'completed' => false,
                'sort_order' => $step->sort_order,
            ]);
        }

        return back();
    }

    public function destroy(ChecklistTemplate $checklistTemplate)
    {
        $checklistTemplate->delete();

        return back();
    }
}
