<?php
declare(strict_types=1);

namespace SlowMail\Mail;

/**
 * 실제로 보내지 않고 storage/mail/ 에 .eml 파일로 남긴다.
 * 메일 클라이언트(Thunderbird, Outlook 등)로 열어 확인할 수 있다.
 */
final class LogMailer implements Mailer
{
    public function __construct(private string $dir)
    {
    }

    public function name(): string
    {
        return 'log';
    }

    public function send(Message $message): string
    {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0750, true) && !is_dir($this->dir)) {
            throw new MailException('메일 저장 폴더를 만들 수 없습니다: ' . $this->dir);
        }

        $id = bin2hex(random_bytes(8));
        $messageId = sprintf('<%s@%s>', $id, substr(strrchr($message->fromEmail, '@') ?: '@localhost', 1));
        $headers = [
            'Message-ID: ' . $messageId,
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . self::encodeHeader($message->fromName) . ' <' . $message->fromEmail . '>',
            'To: ' . $message->to,
            'Subject: ' . self::encodeHeader($message->subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $eml = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($message->text));

        $file = sprintf('%s/%s_%s.eml', $this->dir, gmdate('Ymd_His'), $id);
        if (file_put_contents($file, $eml, LOCK_EX) === false) {
            throw new MailException('메일 파일 저장에 실패했습니다.');
        }
        return $messageId;
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
