<?php

namespace App\Services;

/**
 * Registry of provider templates for the Settings > Notifications UI.
 * Each entry describes the config fields a provider of that type needs,
 * so the "Add Provider" form can render the right inputs.
 *
 * `implemented => true` means a Service class actually knows how to
 * send through that provider (BulkSmsService, BrevoService,
 * WhatsAppService). Providers with `implemented => false` can still be
 * added and stored — useful for recording credentials ahead of time —
 * but sending won't actually route through them until that support is
 * built.
 */
class NotificationProviderTemplates
{
    public static function all(): array
    {
        return [
            'sms' => [
                'bulksms' => [
                    'name' => 'BulkSMS Nigeria',
                    'implemented' => true,
                    'fields' => [
                        'apiToken' => 'API Token',
                        'senderId' => 'Sender ID',
                        'sandbox' => 'Sandbox Mode (true/false)',
                    ],
                ],
                'twilio' => [
                    'name' => 'Twilio',
                    'implemented' => false,
                    'fields' => [
                        'accountSid' => 'Account SID',
                        'authToken' => 'Auth Token',
                        'fromNumber' => 'From Number',
                    ],
                ],
                'termii' => [
                    'name' => 'Termii',
                    'implemented' => false,
                    'fields' => [
                        'apiKey' => 'API Key',
                        'senderId' => 'Sender ID',
                    ],
                ],
            ],
            'email' => [
                'brevo' => [
                    'name' => 'Brevo',
                    'implemented' => true,
                    'fields' => [
                        'apiKey' => 'API Key',
                        'fromEmail' => 'From Email',
                        'fromName' => 'From Name',
                    ],
                ],
                'sendgrid' => [
                    'name' => 'SendGrid',
                    'implemented' => false,
                    'fields' => [
                        'apiKey' => 'API Key',
                        'fromEmail' => 'From Email',
                        'fromName' => 'From Name',
                    ],
                ],
                'mailgun' => [
                    'name' => 'Mailgun',
                    'implemented' => false,
                    'fields' => [
                        'apiKey' => 'API Key',
                        'domain' => 'Domain',
                        'fromEmail' => 'From Email',
                    ],
                ],
            ],
            'whatsapp' => [
                'whatsapp_meta' => [
                    'name' => 'WhatsApp (Meta Cloud API)',
                    'implemented' => true,
                    'fields' => [
                        'apiUrl' => 'API URL',
                        'token' => 'Access Token',
                        'phoneNumberId' => 'Phone Number ID',
                    ],
                ],
            ],
        ];
    }
}
