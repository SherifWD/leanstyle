<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupportController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'subject'     => ['required','string','max:190'],
            'message'     => ['required','string'],
            'attachments' => ['nullable','array'],
            'attachments.*' => ['file','max:5120'], // 5MB per file
            'name'  => ['nullable','string','max:190'],
            'phone' => ['nullable','string','max:30'],
            'email' => ['nullable','email'],
        ]);

        $paths = [];
        if (!empty($data['attachments'])) {
            foreach ($data['attachments'] as $file) {
                $paths[] = Storage::disk('public')->putFile('support', $file);
            }
        }

        $ticket = SupportTicket::create([
            'user_id'     => optional($request->user('api'))->id,
            'customer_id' => optional($request->user('customer'))->id,
            'name'        => $data['name'] ?? ($request->user('api')->name ?? $request->user('customer')->name ?? null),
            'phone'       => $data['phone'] ?? ($request->user('api')->phone ?? $request->user('customer')->phone ?? null),
            'email'       => $data['email'] ?? ($request->user('api')->email ?? $request->user('customer')->email ?? null),
            'subject'     => $data['subject'],
            'message'     => $data['message'],
            'attachments' => $paths ?: null,
        ]);

        // TODO: notify support (mail/Slack)
        return $this->returnData('ticket', $ticket->only('id','subject','status','created_at'), 'Ticket submitted');
    }
}

