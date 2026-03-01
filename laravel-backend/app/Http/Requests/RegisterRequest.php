<?php

namespace App\Http\Requests;

use App\Models\StudentIdPrefix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $passwordRule = Password::min(8)->mixedCase()->numbers()->symbols();

        if ((bool) config('lnu.password_uncompromised', false)) {
            $passwordRule = $passwordRule->uncompromised();
        }

        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'student_id' => [
                'required',
                'string',
                'regex:/^\d{6,12}$/',
                Rule::unique('users', 'student_id'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $studentId = (string) $value;
                    $prefixLength = (int) config('lnu.student_id_prefix_length', 2);
                    $prefix = substr($studentId, 0, $prefixLength);

                    if ($prefix === '' || ! $this->prefixExists($prefix)) {
                        $fail('The selected student ID prefix is not allowed.');
                    }
                },
            ],
            'email' => [
                'nullable',
                'email',
                Rule::unique('users', 'email'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }

                    $allowedDomains = config('lnu.allowed_email_domains', []);
                    $normalizedDomains = array_values(array_filter(array_map(
                        static fn ($domain) => Str::lower(trim((string) $domain)),
                        is_array($allowedDomains) ? $allowedDomains : []
                    )));

                    $domain = Str::lower(Str::after((string) $value, '@'));

                    if ($normalizedDomains === [] || ! in_array($domain, $normalizedDomains, true)) {
                        $fail('The email domain is not allowed.');
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                $passwordRule,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $password = Str::lower((string) $value);
                    $studentId = Str::lower((string) $this->input('student_id'));
                    $email = (string) $this->input('email');
                    $emailLocalPart = Str::lower(Str::before($email, '@'));
                    $name = Str::lower(preg_replace('/\s+/', '', (string) $this->input('name')) ?? '');

                    if ($studentId !== '' && Str::contains($password, $studentId)) {
                        $fail('The password must not contain your student ID.');
                    }

                    if ($emailLocalPart !== '' && Str::contains($password, $emailLocalPart)) {
                        $fail('The password must not contain your email username.');
                    }

                    if ($name !== '' && Str::contains($password, $name)) {
                        $fail('The password must not contain your name.');
                    }
                },
            ],
        ];
    }

    private function prefixExists(string $prefix): bool
    {
        return StudentIdPrefix::query()
            ->where('is_active', true)
            ->where('prefix', $prefix)
            ->exists();
    }
}
