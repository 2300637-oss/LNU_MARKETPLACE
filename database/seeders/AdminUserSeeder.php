<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::query()->where('code', 'admin')->first();

        if (! $adminRole) {
            return;
        }

        $adminExists = User::query()
            ->whereHas('roles', fn ($query) => $query->where('roles.id', $adminRole->id))
            ->exists();

        if ($adminExists) {
            return;
        }

        $prefix = StudentIdPrefix::query()
            ->where('is_active', true)
            ->orderBy('enrollment_year')
            ->value('prefix');

        if (! $prefix) {
            throw new RuntimeException('No active student_id_prefixes available for admin seeding.');
        }

        $studentId = $this->nextStudentId($prefix);

        $adminUser = User::query()->create([
            'student_id' => $studentId,
            'student_id_prefix' => $prefix,
            'email' => $studentId.'@lnu.edu.ph',
            'password' => 'admin123',
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'middle_name' => null,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $adminUser->roles()->syncWithoutDetaching([
            $adminRole->id => [
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
            ],
        ]);
    }

    private function nextStudentId(string $prefix): string
    {
        for ($sequence = 1; $sequence <= 9999; $sequence++) {
            $candidate = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

            if (! User::query()->where('student_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate unique admin student_id for prefix '.$prefix);
    }
}

