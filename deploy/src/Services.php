<?php
declare(strict_types=1);

namespace SlowMail;

use PDO;
use SlowMail\Mail\Factory;
use SlowMail\Mail\Mailer;

/** 필요한 것만 그때 만드는 작은 서비스 컨테이너 (DB 가 필요 없는 페이지는 접속하지 않음) */
final class Services
{
    private array $made = [];

    public function __construct(private array $config, private Clock $clock)
    {
    }

    public function clock(): Clock
    {
        return $this->clock;
    }

    public function pdo(): PDO
    {
        return $this->made[__FUNCTION__] ??= Db::connect($this->config['db']);
    }

    public function crypto(): Crypto
    {
        return $this->made[__FUNCTION__] ??= Crypto::fromConfig($this->config['crypto']);
    }

    public function mailer(): Mailer
    {
        return $this->made[__FUNCTION__] ??= Factory::fromConfig($this->config['mail'], BASE_PATH . '/storage');
    }

    public function rules(): DeliveryRules
    {
        return $this->made[__FUNCTION__] ??= new DeliveryRules($this->config['letter'], $this->config['timezone'], $this->clock);
    }

    public function letters(): LetterService
    {
        return $this->made[__FUNCTION__] ??= new LetterService(
            $this->pdo(),
            $this->crypto(),
            $this->mailer(),
            $this->templates(),
            $this->rules(),
            $this->limiter(),
            $this->clock,
            $this->config,
            static fn (string $token): string => absolute_url('verify', ['t' => $token]),
        );
    }

    public function dispatcher(): Dispatcher
    {
        return $this->made[__FUNCTION__] ??= new Dispatcher(
            $this->pdo(),
            $this->crypto(),
            $this->mailer(),
            $this->templates(),
            $this->letters(),
            $this->limiter(),
            $this->clock,
            $this->config,
        );
    }

    public function limiter(): RateLimiter
    {
        return $this->made[__FUNCTION__] ??= new RateLimiter($this->pdo(), $this->crypto(), $this->clock);
    }

    public function templates(): MailTemplates
    {
        return $this->made[__FUNCTION__] ??= new MailTemplates(BASE_PATH . '/templates/mail', $this->config['mail']);
    }

    public function scheduler(): Scheduler\Scheduler
    {
        return $this->made[__FUNCTION__] ??= Scheduler\Factory::fromConfig($this->config['scheduler'] ?? []);
    }

    public function migrator(): Migrator
    {
        return $this->made[__FUNCTION__] ??= new Migrator($this->pdo(), BASE_PATH . '/migrations', $this->clock);
    }
}
