<?php
declare(strict_types=1);

namespace SlowMail;

use PDO;
use RuntimeException;

/**
 * migrations/NNN_이름.sql 을 번호 순으로 한 번씩 적용한다.
 * MySQL DDL 은 암묵적 커밋이라 트랜잭션으로 묶지 않고, 파일 단위로 기록한다.
 * 동시에 두 번 실행되지 않도록 GET_LOCK 을 건다 (배포 직후 HTTP 트리거 + CLI 중복 대비).
 */
final class Migrator
{
    public function __construct(private PDO $pdo, private string $dir, private Clock $clock)
    {
    }

    /** @return string[] 이번에 적용한 버전 목록 */
    public function run(): array
    {
        if ((int) $this->pdo->query("SELECT GET_LOCK('slowmail_migrate', 10)")->fetchColumn() !== 1) {
            throw new RuntimeException('다른 마이그레이션이 실행 중입니다.');
        }
        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS schema_migrations (
                    version    VARCHAR(50) NOT NULL PRIMARY KEY,
                    applied_at DATETIME    NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $done = $this->pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

            $applied = [];
            foreach ($this->pending($done) as $version => $file) {
                foreach (self::split((string) file_get_contents($file)) as $statement) {
                    $this->pdo->exec($statement);
                }
                $this->pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)')
                    ->execute([$version, $this->clock->nowSql()]);
                $applied[] = $version;
            }
            return $applied;
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('slowmail_migrate')");
        }
    }

    /** @return array<string, string> 버전 => 파일 경로 */
    private function pending(array $done): array
    {
        $files = glob($this->dir . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        $pending = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (!in_array($version, $done, true)) {
                $pending[$version] = $file;
            }
        }
        return $pending;
    }

    /** 줄 끝 ; 기준으로 문장 분리, -- 주석 줄 제거 (마이그레이션 파일에 프로시저·문자열 속 ; 는 쓰지 않는다) */
    private static function split(string $sql): array
    {
        $lines = array_filter(
            preg_split('/\R/', $sql),
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--')
        );
        $statements = preg_split('/;\s*$/m', implode("\n", $lines));
        return array_values(array_filter(array_map('trim', $statements), static fn (string $s): bool => $s !== ''));
    }
}
