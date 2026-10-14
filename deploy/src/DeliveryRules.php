<?php
declare(strict_types=1);

namespace SlowMail;

use DateTimeImmutable;
use DateTimeZone;

/**
 * 도착일 규칙 (docs/03-architecture.md §1)
 * - 사용자는 표시 시간대(Asia/Seoul) 기준 "날짜"만 고른다.
 * - 실제 발송 시각은 그 날짜의 deliver_hour 시 → UTC 로 변환해 저장한다.
 */
final class DeliveryRules
{
    private DateTimeZone $tz;

    public function __construct(private array $letter, string $timezone, private Clock $clock)
    {
        $this->tz = new DateTimeZone($timezone);
    }

    /** 오늘 (표시 시간대, 자정) */
    public function today(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($this->tz)->setTime(0, 0);
    }

    public function minDate(): DateTimeImmutable
    {
        return $this->today()->modify('+' . (int) $this->letter['min_days'] . ' days');
    }

    public function maxDate(): DateTimeImmutable
    {
        return $this->today()->modify('+' . (int) $this->letter['max_years'] . ' years');
    }

    /** @return array<string, array{label: string, date: DateTimeImmutable}> */
    public function presets(): array
    {
        $out = [];
        foreach ($this->letter['presets'] as $key => $preset) {
            $out[$key] = ['label' => $preset['label'], 'date' => $this->today()->modify($preset['modify'])];
        }
        return $out;
    }

    /**
     * 프리셋 키 또는 'custom' + 'Y-m-d' 를 도착일로 해석한다.
     * @return DateTimeImmutable|string 도착일(표시 시간대 자정) 또는 오류 메시지
     */
    public function resolve(string $preset, string $custom): DateTimeImmutable|string
    {
        if ($preset === 'custom') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $custom, $this->tz);
            if ($date === false || $date->format('Y-m-d') !== $custom) {
                return '도착할 날짜를 다시 골라 주세요.';
            }
        } elseif (isset($this->letter['presets'][$preset])) {
            $date = $this->today()->modify($this->letter['presets'][$preset]['modify']);
        } else {
            return '도착할 날짜를 골라 주세요.';
        }

        if ($date < $this->minDate()) {
            return sprintf('편지는 적어도 %d일 뒤에 도착하도록 골라 주세요. 천천히 가는 우체통이거든요.', $this->letter['min_days']);
        }
        if ($date > $this->maxDate()) {
            return sprintf('%d년보다 먼 미래로는 아직 보낼 수 없어요.', $this->letter['max_years']);
        }
        return $date;
    }

    /** 도착일(표시 시간대) → 발송 시각(UTC) */
    public function deliverAt(DateTimeImmutable $localDate): DateTimeImmutable
    {
        return $localDate->setTimezone($this->tz)
            ->setTime((int) $this->letter['deliver_hour'], 0)
            ->setTimezone(new DateTimeZone('UTC'));
    }

    /** 오늘부터 도착일까지 남은 날 수 */
    public function daysUntil(string $deliverOn): int
    {
        $date = new DateTimeImmutable($deliverOn, $this->tz);
        return max(0, (int) $this->today()->diff($date)->format('%r%a'));
    }
}
