<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Verification request from Meta
        if ($request->isMethod('GET')) {

            if (
                $request->hub_mode === 'subscribe' &&
                $request->hub_verify_token === env('WHATSAPP_VERIFY_TOKEN')
            ) {
                return response($request->hub_challenge, 200);
            }

            return response('Forbidden', 403);
        }

        // Incoming webhook events
        \Log::info('WhatsApp Webhook', $request->all());

        return response()->json(['success' => true]);
    }
}