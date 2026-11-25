<?php

namespace App\Services;

use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends one SMS to a client and records it in sms_logs. Messages never contain results or diagnoses —
 * only that something is ready / due and where to go.
 */
class SmsService
{
    /** "08031234567" / "+234 803 123 4567" → "2348031234567" */
    public static function normalize(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if ($d === '') return null;
        if (str_starts_with($d, '234')) return strlen($d) >= 13 ? $d : null;
        if (str_starts_with($d, '0') && strlen($d) === 11) return '234' . substr($d, 1);
        if (strlen($d) === 10) return '234' . $d;
        return strlen($d) >= 11 ? $d : null;
    }

    /** @return array{ok: bool, status: string, message: string} */
    public static function send(?string $phone, string $message, string $purpose, ?string $clientId = null): array
    {
        $to = self::normalize($phone);
        $driver = (string) config('ncsr_sms.driver', 'log');
        if (!$to) {
            return ['ok' => false, 'status' => 'failed', 'message' => 'The client has no valid phone number.'];
        }
        $help = (string) config('ncsr_sms.helpline', '');
        if ($help !== '' && !str_contains($message, $help)) $message .= " Enquiries: {$help}.";

        $status = 'logged';
        $response = null;
        try {
            if ($driver === 'brevo' && config('ncsr_sms.brevo_key')) {
                $res = Http::timeout(20)->withHeaders(['api-key' => config('ncsr_sms.brevo_key'), 'accept' => 'application/json'])
                    ->post('https://api.brevo.com/v3/transactionalSMS/sms', [
                        'sender' => mb_substr((string) config('ncsr_sms.sender', 'NCSR'), 0, 11),
                        'recipient' => $to,
                        'content' => $message,
                        'type' => 'transactional',
                    ]);
                $status = $res->successful() ? 'sent' : 'failed';
                $response = mb_substr($res->body(), 0, 1000);
            } elseif ($driver === 'bulksmsnigeria' && config('ncsr_sms.bulksms_token')) {
                $res = Http::timeout(20)->get('https://www.bulksmsnigeria.com/api/v1/sms/create', [
                    'api_token' => config('ncsr_sms.bulksms_token'),
                    'from' => mb_substr((string) config('ncsr_sms.sender', 'NCSR'), 0, 11),
                    'to' => $to,
                    'body' => $message,
                    'dnd' => 2,
                ]);
                $status = $res->successful() && !str_contains(strtolower($res->body()), 'error') ? 'sent' : 'failed';
                $response = mb_substr($res->body(), 0, 1000);
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $response = $e->getMessage();
            Log::warning('SMS failed: ' . $e->getMessage());
        }

        SmsLog::create([
            'clientId' => $clientId, 'phone' => $to, 'purpose' => $purpose, 'message' => $message,
            'driver' => $driver, 'status' => $status, 'response' => $response, 'userId' => auth('api')->id(),
        ]);

        return [
            'ok' => $status !== 'failed',
            'status' => $status,
            'message' => $status === 'sent' ? 'SMS sent.' : ($status === 'logged'
                ? 'SMS saved (sending is switched off on the server: set NCSR_SMS_DRIVER).'
                : 'The SMS provider refused the message.'),
        ];
    }

    /** Ready-made messages (no clinical details). */
    public static function template(string $purpose, array $v): string
    {
        $name = trim(explode(' ', (string) ($v['name'] ?? ''))[0] ?? '') ?: 'there';
        $fac = $v['facility'] ?? 'the clinic';
        $date = $v['date'] ?? null;
        return match ($purpose) {
            'result_ready' => "Hello {$name}, your screening result is ready. Please visit {$fac} to discuss it with a nurse.",
            'recall'       => "Hello {$name}, this is a reminder that your next cancer screening is due" . ($date ? " on {$date}" : '') . " at {$fac}.",
            'booked_test'  => "Hello {$name}, a reminder of your test appointment" . ($date ? " on {$date}" : '') . " at {$fac}.",
            'referral'     => "Hello {$name}, please remember to attend your referral appointment at {$fac}" . ($date ? " (referred on {$date})" : '') . '.',
            default        => (string) ($v['message'] ?? ''),
        };
    }
}
