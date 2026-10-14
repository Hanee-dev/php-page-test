<?php
declare(strict_types=1);

namespace SlowMail;

use SlowMail\Mail\Message;

/** templates/mail/*.php (['subject' => ..., 'text' => ...] 를 반환) 로 Message 를 만든다 */
final class MailTemplates
{
    public function __construct(private string $dir, private array $mail)
    {
    }

    public function build(string $name, string $to, array $vars): Message
    {
        $render = static function (string $__file, array $__vars): array {
            extract($__vars, EXTR_SKIP);
            return require $__file;
        };
        $mail = $render($this->dir . '/' . $name . '.php', $vars);

        return new Message(
            to: $to,
            subject: $mail['subject'],
            text: $mail['text'],
            fromEmail: (string) $this->mail['from'],
            fromName: (string) $this->mail['from_name'],
        );
    }
}
