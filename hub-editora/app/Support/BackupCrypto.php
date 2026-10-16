<?php

namespace App\Support;

use RuntimeException;

/**
 * Cifra dos backups: AES-256-GCM com chave própria (BACKUP_ENCRYPTION_KEY,
 * separada da APP_KEY). O GCM também acusa arquivo adulterado ou corrompido.
 *
 * Formato: "HUBBK1" + IV (12 bytes) + TAG (16 bytes) + conteúdo cifrado.
 */
class BackupCrypto
{
    private const MAGIC = 'HUBBK1';

    private const CIPHER = 'aes-256-gcm';

    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    public static function encrypt(string $plain, string $key): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::rawKey($key), OPENSSL_RAW_DATA, $iv, $tag, self::MAGIC);

        if ($cipher === false) {
            throw new RuntimeException('Falha ao cifrar o backup.');
        }

        return self::MAGIC.$iv.$tag.$cipher;
    }

    public static function decrypt(string $blob, string $key): string
    {
        if (! str_starts_with($blob, self::MAGIC) || strlen($blob) < 34) {
            throw new RuntimeException('Arquivo não é um backup do hub.');
        }

        $iv = substr($blob, 6, 12);
        $tag = substr($blob, 18, 16);
        $plain = openssl_decrypt(substr($blob, 34), self::CIPHER, self::rawKey($key), OPENSSL_RAW_DATA, $iv, $tag, self::MAGIC);

        if ($plain === false) {
            throw new RuntimeException('Não foi possível decifrar: chave errada ou arquivo corrompido.');
        }

        return $plain;
    }

    private static function rawKey(string $key): string
    {
        $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;

        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY inválida: use a gerada por "php artisan hub:backup --gerar-chave".');
        }

        return $raw;
    }
}
