<?php

/*
 | SMS to clients (result ready, recall and appointment reminders).
 | NCSR_SMS_DRIVER: log (default — nothing is sent, messages are only saved in sms_logs),
 |                  brevo (Brevo transactional SMS, same account as the emails), or bulksmsnigeria.
 */
return [
    'driver'      => env('NCSR_SMS_DRIVER', 'log'),
    'sender'      => env('NCSR_SMS_SENDER', 'NCSR'),          // max 11 characters
    'brevo_key'   => env('NCSR_SMS_BREVO_KEY', env('BREVO_API_KEY')),
    'bulksms_token' => env('NCSR_SMS_BULKSMS_TOKEN'),
    'helpline'    => env('NCSR_SMS_HELPLINE', ''),            // e.g. 0800-NICRAT, added to messages when set
    'reminder_time' => env('NCSR_SMS_REMINDER_TIME', '08:30'),
];
