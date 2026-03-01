<?php

$allowedEmailDomains = array_values(array_filter(array_map(
    static fn (string $domain): string => strtolower(trim($domain)),
    explode(',', (string) env('LNU_ALLOWED_EMAIL_DOMAINS', 'lnu.edu.ph'))
)));

return [
    'allowed_email_domains' => $allowedEmailDomains,
    'student_id_prefix_length' => (int) env('LNU_STUDENT_ID_PREFIX_LENGTH', 2),
    'password_uncompromised' => filter_var(env('LNU_PASSWORD_UNCOMPROMISED', false), FILTER_VALIDATE_BOOL),
    'email_otp_expires_minutes' => (int) env('LNU_EMAIL_OTP_EXPIRES_MINUTES', 10),
];
