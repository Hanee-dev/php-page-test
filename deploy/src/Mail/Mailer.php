<?php
declare(strict_types=1);

namespace SlowMail\Mail;

/**
 * 메일 발송 드라이버 계약.
 * 드라이버는 "한 번 보내기"만 책임진다. 재시도·백오프·mail_log 기록은 호출하는 쪽(Dispatcher, LetterService) 몫.
 *
 * 실제 발송(SMTP·메일 API)을 붙일 때는 이 인터페이스 구현체를 추가하고
 * Mail\Factory 와 config['mail']['driver'] 만 바꾼다.
 */
interface Mailer
{
    /** 드라이버 이름 (mail_log.driver 에 기록) */
    public function name(): string;

    /**
     * @return string 메시지 ID
     * @throws MailException 발송 실패 (재시도 대상)
     */
    public function send(Message $message): string;
}
