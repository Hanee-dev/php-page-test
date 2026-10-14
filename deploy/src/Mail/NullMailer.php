<?php
declare(strict_types=1);

namespace SlowMail\Mail;

/** 아무것도 하지 않는 드라이버 (테스트용) */
final class NullMailer implements Mailer
{
    /** @var Message[] 테스트에서 확인용 */
    public array $sent = [];

    public function name(): string
    {
        return 'null';
    }

    public function send(Message $message): string
    {
        $this->sent[] = $message;
        return '<null-' . count($this->sent) . '@localhost>';
    }
}
