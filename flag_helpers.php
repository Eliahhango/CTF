<?php
declare(strict_types=1);

function flag_encrypt(string $plain): string
{
    if (FLAG_ENC_KEY === '') {
        return '';
    }

    $iv = random_bytes(openssl_cipher_iv_length('AES-256-CBC'));
    $enc = openssl_encrypt($plain, 'AES-256-CBC', FLAG_ENC_KEY, 0, $iv);
    return base64_encode($iv . '::' . $enc);
}

function flag_decrypt(string $stored): string
{
    if (FLAG_ENC_KEY === '' || $stored === '') {
        return '';
    }

    $raw = base64_decode($stored, true);
    if (!$raw || !str_contains($raw, '::')) {
        return '';
    }

    [$iv, $enc] = explode('::', $raw, 2);
    return (string)openssl_decrypt($enc, 'AES-256-CBC', FLAG_ENC_KEY, 0, $iv);
}

function verify_flag(string $submitted, array $challenge): bool
{
    $type = (string)($challenge['flag_type'] ?? 'static');

    if ($type === 'static') {
        return password_verify($submitted, (string)($challenge['flag_hash'] ?? ''));
    }

    $plain = flag_decrypt((string)($challenge['flag_plaintext'] ?? ''));
    if ($plain === '') {
        return false;
    }

    if ($type === 'case_insensitive') {
        return mb_strtolower(trim($submitted)) === mb_strtolower(trim($plain));
    }

    if ($type === 'regex') {
        if (@preg_match($plain, '') === false) {
            return false;
        }
        return (bool)preg_match($plain, trim($submitted));
    }

    return false;
}
