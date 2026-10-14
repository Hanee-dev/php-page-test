<?php
declare(strict_types=1);

namespace SlowMail;

use RuntimeException;

/**
 * 편지 제목·본문·이메일 암호화와 조회용 HMAC.
 *
 * 저장 형식: [알고리즘 1바이트][nonce][암호문]
 *   0x01 = libsodium secretbox (XSalsa20-Poly1305, nonce 24바이트)
 *   0x02 = OpenSSL AES-256-GCM (nonce 12바이트, 태그 16바이트를 암호문 뒤에)
 * 서버에 sodium 이 없으면 0x02 로 암호화하고, 복호화는 형식 바이트를 보고 고른다.
 * 키 버전은 행의 key_version 컬럼에 따로 저장한다.
 */
final class Crypto
{
    private const SODIUM = "\x01";
    private const AES_GCM = "\x02";

    /** @var array<int, string> 버전 => 32바이트 원시 키 */
    private array $keys = [];

    private string $hmacKey;

    public function __construct(array $keys, private int $current, string $hmacKey)
    {
        foreach ($keys as $version => $encoded) {
            $this->keys[(int) $version] = self::decodeKey((string) $encoded, "crypto.keys[$version]");
        }
        if (!isset($this->keys[$current])) {
            throw new RuntimeException("현재 암호화 키(버전 $current)가 설정되지 않았습니다.");
        }
        $this->hmacKey = self::decodeKey($hmacKey, 'crypto.hmac_key');
    }

    public static function fromConfig(array $crypto): self
    {
        return new self($crypto['keys'] ?? [], (int) ($crypto['current'] ?? 1), (string) ($crypto['hmac_key'] ?? ''));
    }

    public function currentVersion(): int
    {
        return $this->current;
    }

    public function encrypt(string $plain): string
    {
        $key = $this->keys[$this->current];
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return self::SODIUM . $nonce . sodium_crypto_secretbox($plain, $nonce, $key);
        }
        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('암호화에 실패했습니다.');
        }
        return self::AES_GCM . $nonce . $cipher . $tag;
    }

    public function decrypt(string $blob, int $version): string
    {
        $key = $this->keys[$version] ?? throw new RuntimeException("암호화 키 버전 $version 이 없습니다.");
        $format = $blob[0] ?? '';

        if ($format === self::SODIUM) {
            $nonce = substr($blob, 1, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open(substr($blob, 1 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
        } elseif ($format === self::AES_GCM) {
            $nonce = substr($blob, 1, 12);
            $tag = substr($blob, -16);
            $cipher = substr($blob, 13, -16);
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        } else {
            throw new RuntimeException('알 수 없는 암호문 형식입니다.');
        }

        if ($plain === false) {
            throw new RuntimeException('복호화에 실패했습니다 (키 불일치 또는 데이터 손상).');
        }
        return $plain;
    }

    /** 조회·요청 제한용 32바이트 해시 (원문 복원 불가) */
    public function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, $this->hmacKey, true);
    }

    private static function decodeKey(string $encoded, string $name): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException("$name 는 base64 로 인코딩한 32바이트여야 합니다 (openssl rand -base64 32).");
        }
        return $raw;
    }
}
