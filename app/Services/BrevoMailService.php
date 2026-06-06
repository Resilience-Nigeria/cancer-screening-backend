<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrevoMailService
{
    public function sendWelcomeEmail($user, $password): void
    {
        $response = Http::withHeaders([
            'api-key' => env('BREVO_API_KEY'),
            'content-type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'to' => [[
                'email' => $user->email,
                'name' => $user->firstName . ' ' . $user->lastName
            ]],
            'sender' => [
                'name' => env('MAIL_FROM_NAME', 'NCSR'),
                'email' => env('MAIL_FROM_ADDRESS')
            ],
            'subject' => 'Welcome to NCSR - Your Account Details',
            'htmlContent' => view('emails.welcome', [
                'user' => $user,
                'password' => $password,
                'loginUrl' => config('app.frontend_url')
            ])->render(),
        ]);

        if (!$response->successful()) {
            \Log::error('Brevo email failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        }
    }
}