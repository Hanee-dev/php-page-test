<?php
declare(strict_types=1);

namespace SlowMail;

use PDO;
use RuntimeException;

final class Db
{
    public static function connect(array $db): PDO
    {
        if (($db['name'] ?? '') === '' || ($db['user'] ?? '') === '') {
            throw new RuntimeException('DB 설정이 비어 있습니다 (config.local.php 확인).');
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'],
            (int) ($db['port'] ?? 3306),
            $db['name']
        );
        $pdo = new PDO($dsn, $db['user'], $db['password'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // 시각은 PHP(Clock)에서 UTC 로 넘기지만, DB 함수가 끼어들 때를 대비해 세션도 UTC 로 고정
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }
}
