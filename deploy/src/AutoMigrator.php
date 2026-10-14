<?php
declare(strict_types=1);

namespace SlowMail;

use Closure;
use Throwable;

/**
 * 배포 후 첫 웹 요청 때 아직 적용되지 않은 마이그레이션을 스스로 적용한다.
 * Cafe24 가 해외 IP(GitHub Actions)의 웹 접근을 막아 배포 단계에서 HTTP 로 실행할 수 없기 때문.
 *
 * 평소 요청: migrations/ 의 마지막 파일 이름과 marker 파일 내용만 비교한다 (DB 접속 없음).
 * 다르면 Migrator::run() 을 실행하고 (동시 실행은 Migrator 의 GET_LOCK 이 막는다), 성공 시 marker 를 갱신한다.
 */
final class AutoMigrator
{
    /**
     * @param Migrator|Closure(): Migrator $migrator Closure 면 실제로 필요할 때만 만든다
     *                                              (Migrator 를 만드는 순간 DB 에 접속하므로)
     */
    public function __construct(private Migrator|Closure $migrator, private string $migrationsDir, private string $markerFile)
    {
    }

    public function ensure(): void
    {
        $latest = $this->latestVersion();
        if ($latest === null) {
            return;
        }
        if (is_file($this->markerFile) && trim((string) @file_get_contents($this->markerFile)) === $latest) {
            return;
        }

        try {
            $migrator = $this->migrator instanceof Closure ? ($this->migrator)() : $this->migrator;
            $migrator->run();
        } catch (Throwable $e) {
            // 요청은 계속 처리한다. marker 를 쓰지 않으므로 다음 요청에서 다시 시도
            error_log('[slowmail] 자동 마이그레이션 실패: ' . $e::class . ': ' . $e->getMessage());
            return;
        }

        // 못 쓰면(권한) 매 요청 run() 이 돌지만, 적용할 게 없으면 가볍게 끝나므로 요청은 그대로 진행
        $dir = dirname($this->markerFile);
        if (is_dir($dir) || @mkdir($dir, 0750, true)) {
            @file_put_contents($this->markerFile, $latest, LOCK_EX);
        }
    }

    /** migrations/*.sql 중 이름순 마지막 파일의 버전 (예: 001_init). 없으면 null */
    private function latestVersion(): ?string
    {
        $files = glob($this->migrationsDir . '/*.sql') ?: [];
        if ($files === []) {
            return null;
        }
        sort($files, SORT_STRING);
        return basename(end($files), '.sql');
    }
}
