<?php

namespace Database\Seeders;

use App\Models\StudentIdPrefix;
use Illuminate\Database\Seeder;

class StudentIdPrefixSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $raw = (string) env('STUDENT_ID_PREFIXES', '');
        $tokens = array_values(array_filter(array_map('trim', explode(',', $raw))));

        if ($tokens === []) {
            // Example local default only. Override with STUDENT_ID_PREFIXES, e.g. "23,24".
            $tokens = ['23'];
        }

        $allowedPrefixes = [];

        foreach ($tokens as $token) {
            if (preg_match('/^\d{2}$/', $token) === 1) {
                $year = 2000 + (int) $token;
                $prefix = $token;
            } elseif (preg_match('/^\d{3}$/', $token) === 1 && str_ends_with($token, '0')) {
                $year = 2000 + (int) substr($token, 0, 2);
                $prefix = substr($token, 0, 2);
            } elseif (preg_match('/^\d{4}$/', $token) === 1) {
                $year = (int) $token;
                $prefix = substr($token, -2);
            } else {
                continue;
            }

            $allowedPrefixes[$prefix] = [
                'prefix' => $prefix,
                'enrollment_year' => $year,
            ];
        }

        if ($allowedPrefixes === []) {
            $allowedPrefixes['23'] = [
                'prefix' => '23',
                'enrollment_year' => 2023,
            ];
        }

        foreach ($allowedPrefixes as $row) {
            StudentIdPrefix::query()->updateOrCreate(
                ['prefix' => $row['prefix']],
                [
                    'enrollment_year' => $row['enrollment_year'],
                    'is_active' => true,
                    'notes' => 'Seeded for local development',
                ]
            );
        }
    }
}
