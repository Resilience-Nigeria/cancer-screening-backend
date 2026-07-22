<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Stage 4, Section 4.11 — daily follow-up reminders, missed-appointment
// flagging, and overdue escalation.
Schedule::command('followups:send-reminders')->daily();
