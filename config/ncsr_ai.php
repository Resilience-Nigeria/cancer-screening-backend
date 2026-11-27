<?php

/*
 * AI-assisted summary of uploaded reports (simulation report §9). OFF unless NCSR_AI_ENABLED=true.
 * Uses Anthropic's Claude API. Before turning it on, get NICRAT's approval for sending report images to
 * an outside processor (NDPA) — no client name or ID is sent, only the report file the user chose.
 */
return [
    'enabled' => env('NCSR_AI_ENABLED', false),
    'key' => env('ANTHROPIC_API_KEY'),
    'model' => env('NCSR_AI_MODEL', 'claude-sonnet-5-5'),
    'max_mb' => (int) env('NCSR_AI_MAX_MB', 10),
];
