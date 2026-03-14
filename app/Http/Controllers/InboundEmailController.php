<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InboundEmailController extends Controller
{
    public function store(Request $request)
    {
        $secret = config('services.inbound_email.secret');
        if (!$secret || !hash_equals($secret, (string) $request->header('X-Webhook-Secret', ''))) {
            abort(403, 'Invalid webhook secret');
        }

        $validated = $request->validate([
            'from' => 'required|email',
            'from_name' => 'nullable|string|max:255',
            'to' => 'required|email',
            'subject' => 'required|string|max:1000',
            'body' => 'required|string|max:100000',
            'message_id' => 'nullable|string|max:500',
        ]);

        // Dedup by message_id
        if (!empty($validated['message_id'])) {
            $existing = Email::where('message_id', $validated['message_id'])->first();
            if ($existing) {
                return response()->json(['status' => 'duplicate', 'email_id' => $existing->id], 200);
            }
        }

        $item = Item::create([
            'id' => Str::ulid(),
            'title' => $validated['subject'],
            'status' => 'inbox',
        ]);

        $email = Email::create([
            'id' => Str::ulid(),
            'item_id' => $item->id,
            'from_address' => $validated['from'],
            'from_name' => $validated['from_name'] ?? null,
            'to_address' => $validated['to'],
            'subject' => $validated['subject'],
            'body_text' => $validated['body'],
            'received_at' => now(),
            'message_id' => $validated['message_id'] ?? null,
        ]);

        return response()->json(['status' => 'ok', 'email_id' => $email->id, 'item_id' => $item->id], 201);
    }
}
