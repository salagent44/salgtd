<?php

namespace App\Http\Controllers;

use App\Models\Context;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:contexts,name',
        ]);

        Context::create([
            'name' => $validated['name'],
            'built_in' => false,
        ]);

        return back();
    }

    public function destroy(Context $context)
    {
        if ($context->built_in) {
            return back()->withErrors(['name' => 'Cannot delete built-in contexts.']);
        }

        $context->delete();

        return back();
    }
}
