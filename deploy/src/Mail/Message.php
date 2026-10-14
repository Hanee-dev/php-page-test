<?php
declare(strict_types=1);

namespace SlowMail\Mail;

/** 보낼 메일 한 통 (드라이버와 무관한 값 객체) */
final class Message
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $text,
        public ?string $html = null,
        public string $fromEmail = '',
        public string $fromName = '',
    ) {
    }
}
