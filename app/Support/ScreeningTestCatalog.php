<?php

namespace App\Support;

/**
 * Reads resources/screening_tests.json — the same file the frontend ships as lib/screeningTests.json.
 * Keep the two copies identical when you add or change a test.
 */
class ScreeningTestCatalog
{
    private static ?array $data = null;

    public static function all(): array
    {
        if (self::$data === null) {
            self::$data = json_decode(file_get_contents(resource_path('screening_tests.json')), true);
        }
        return self::$data;
    }

    public static function find(string $key): ?array
    {
        foreach (self::all()['tests'] as $t) {
            if ($t['key'] === $key) {
                return $t;
            }
        }
        return null;
    }

    public static function keys(): array
    {
        return array_column(self::all()['tests'], 'key');
    }

    public static function cancerTypes(): array
    {
        return array_column(self::all()['cancerTypes'], 'value');
    }

    /** Result implied by a numeric value (PSA, AFP), using the test's bands. */
    public static function deriveResult(array $test, float $value): ?string
    {
        foreach ($test['value']['bands'] ?? [] as $band) {
            if (!isset($band['below']) || $value < $band['below']) {
                return $band['result'];
            }
        }
        return null;
    }
}
