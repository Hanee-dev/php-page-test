<?php
declare(strict_types=1);

namespace SlowMail;

/**
 * 화면별 처리. 라우팅은 www/index.php
 * 흐름: /write → (POST) → /posted → 메일 링크 /verify?t= → (POST) → /sealed
 */
final class Controller
{
    public function __construct(private Services $services, private array $config)
    {
    }

    public function home(): void
    {
        $this->render('home', 'home', ['waiting' => $this->waitingCount()]);
    }

    public function writeForm(array $errors = [], array $old = [], int $status = 200): void
    {
        $rules = $this->services->rules();
        $this->render('write', '편지 쓰기', [
            'errors'  => $errors,
            'old'     => $old + ['preset' => $this->config['letter']['default_preset']],
            'presets' => $rules->presets(),
            'minDate' => $rules->minDate()->format('Y-m-d'),
            'maxDate' => $rules->maxDate()->format('Y-m-d'),
            'limits'  => $this->config['letter'],
        ], $status, true);
    }

    public function writeSubmit(): void
    {
        $old = [
            'title'       => (string) ($_POST['title'] ?? ''),
            'body'        => (string) ($_POST['body'] ?? ''),
            'email'       => (string) ($_POST['email'] ?? ''),
            'preset'      => (string) ($_POST['preset'] ?? ''),
            'custom_date' => (string) ($_POST['custom_date'] ?? ''),
        ];

        // 허니팟: 사람에게는 보이지 않는 필드
        if (!empty($_POST['website'])) {
            $this->writeForm(['잘못된 요청입니다.'], $old, 400);
            return;
        }
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            $this->writeForm(['편지지가 오래 펼쳐져 있었어요. 한 번 더 봉인해 주세요.'], $old, 403);
            return;
        }

        $result = $this->services->letters()->submit($old, client_ip());
        if ($result['errors'] !== []) {
            $this->writeForm($result['errors'], $old, 422);
            return;
        }

        flash('posted', $result['letter'] + [
            'verify_url' => $this->showsVerifyLink() ? $result['verify_url'] : null,
        ]);
        redirect(url('posted'));
    }

    public function posted(): void
    {
        $letter = flash('posted');
        if ($letter === null) {
            redirect(url());
        }
        $this->render('posted', '확인 메일을 보냈어요', [
            'letter'   => $letter,
            'ttlHours' => (int) $this->config['letter']['verify_ttl_hours'],
        ], 200, true);
    }

    public function verifyForm(): void
    {
        $token = (string) ($_GET['t'] ?? '');
        if (!$this->services->limiter()->hit('verify_ip', client_ip(), $this->config['rate_limit']['verify_ip'])) {
            $this->message(429, '잠시 후 다시 열어 주세요', '링크를 너무 자주 열었어요. 조금 뒤에 다시 시도해 주세요.');
            return;
        }

        $letter = $this->services->letters()->findByToken($token);
        $this->renderVerifyState($letter, $token);
    }

    public function verifySubmit(): void
    {
        $token = (string) ($_POST['t'] ?? '');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            redirect(url('verify', ['t' => $token]));
        }

        $letter = $this->services->letters()->seal($token);
        if ($letter !== null && in_array($letter['status'], [LetterService::SEALED, LetterService::SENDING], true)) {
            flash('sealed', $letter);
            redirect(url('sealed'));
        }
        $this->renderVerifyState($letter, $token);
    }

    public function sealed(): void
    {
        $letter = flash('sealed');
        if ($letter === null) {
            redirect(url());
        }
        $this->render('sealed', '봉인되었습니다', [
            'letter'   => $letter,
            'daysLeft' => $this->services->rules()->daysUntil($letter['deliver_on']),
            'waiting'  => $this->waitingCount(true),
        ]);
    }

    /**
     * 배포 직후 마이그레이션 (GitHub Actions 전용)
     * 공유 호스팅의 CGI/FPM 은 Authorization 헤더를 떨어뜨리는 경우가 있어 X-Ops-Token 헤더를 쓴다.
     */
    public function opsMigrate(): void
    {
        $expected = (string) ($this->config['ops']['token'] ?? '');
        $given = (string) ($_SERVER['HTTP_X_OPS_TOKEN'] ?? '');
        if ($expected === '' || !hash_equals($expected, $given)) {
            $this->notFound();
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            $applied = $this->services->migrator()->run();
            echo json_encode(['ok' => true, 'applied' => $applied], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('[slowmail] 마이그레이션 실패: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'migration failed (서버 error_log 확인)'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function page(string $name, string $title): void
    {
        $this->render($name, $title, ['config' => $this->config]);
    }

    public function notFound(): void
    {
        $this->message(404, '길을 잃었어요', '찾으시는 페이지가 밤하늘 어디에도 없어요.');
    }

    public function message(int $status, string $heading, string $text): void
    {
        $this->render('message', $heading, ['heading' => $heading, 'text' => $text], $status);
    }

    /** 실제 메일이 나가지 않는 동안(log/null 드라이버)이나 debug 일 때만 확인 링크를 화면에 보여 준다 */
    private function showsVerifyLink(): bool
    {
        return $this->config['app']['debug']
            || in_array($this->config['mail']['driver'], ['log', 'null'], true);
    }

    private function renderVerifyState(?array $letter, string $token): void
    {
        if ($letter === null) {
            $this->message(404, '편지를 찾을 수 없어요', '링크가 잘못되었거나, 기한이 지나 편지가 사라졌어요.');
            return;
        }
        switch ($letter['status']) {
            case LetterService::PENDING:
                $this->render('verify', '편지 봉인하기', ['letter' => $letter, 'token' => $token], 200, true);
                return;
            case LetterService::EXPIRED:
                $this->message(410, '확인 기한이 지났어요', '봉인되지 않은 편지는 조용히 사라졌어요. 마음이 남아 있다면 다시 한 번 써 주세요.');
                return;
            case LetterService::DELIVERED:
                $this->message(200, '이미 도착한 편지예요', '이 편지는 약속한 날 메일함에 도착했어요.');
                return;
            default:
                // 이미 봉인됨 (링크를 두 번 연 경우)
                $this->render('sealed', '봉인되었습니다', [
                    'letter'   => $letter,
                    'daysLeft' => $this->services->rules()->daysUntil($letter['deliver_on']),
                    'waiting'  => $this->waitingCount(),
                    'again'    => true,
                ]);
        }
    }

    /** 밤하늘의 별 수. 매 요청 COUNT 하지 않도록 5분 파일 캐시 */
    private function waitingCount(bool $fresh = false): int
    {
        $file = BASE_PATH . '/storage/cache/waiting.json';
        $now = $this->services->clock()->now()->getTimestamp();
        if (!$fresh && is_file($file)) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached) && $now - (int) ($cached['at'] ?? 0) < 300) {
                return (int) $cached['count'];
            }
        }
        $count = $this->services->letters()->waitingCount();
        if (is_dir(dirname($file)) || @mkdir(dirname($file), 0750, true)) {
            @file_put_contents($file, json_encode(['at' => $now, 'count' => $count]), LOCK_EX);
        }
        return $count;
    }

    /** @param bool $private 토큰·입력값이 담긴 화면은 캐시 금지 */
    private function render(string $page, string $title, array $vars = [], int $status = 200, bool $private = false): void
    {
        http_response_code($status);
        if ($private) {
            header('Cache-Control: no-store');
        }
        $view = static function (string $__page, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            require BASE_PATH . '/templates/layout/header.php';
            require BASE_PATH . '/templates/pages/' . $__page . '.php';
            require BASE_PATH . '/templates/layout/footer.php';
        };
        $view($page, $vars + ['title' => $title, 'page' => $page, 'appName' => $this->config['app']['name']]);
    }
}
