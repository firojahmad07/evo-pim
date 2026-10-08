<?php

declare(strict_types=1);

namespace Psr\Log\Test;

use Psr\Log\AbstractLogger;

/**
 * Minimal TestLogger implementation used in test environment when the psr/log Test classes
 * are not available as a dependency.
 */
class TestLogger extends AbstractLogger
{
    /** @var array<int, array{level: mixed, message: mixed, context: array}> */
    public array $records = [];

    /**
     * @param mixed $level
     * @param mixed $message
     * @param array $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }

    public function hasRecord(string $message, $level = null): bool
    {
        foreach ($this->records as $r) {
            if ($r['message'] === $message && (null === $level || $r['level'] === $level)) {
                return true;
            }
        }

        return false;
    }

    public function hasInfo(array $record): bool
    {
        return $this->hasRecordThatContains($record, 'info');
    }

    public function hasError(array $record): bool
    {
        return $this->hasRecordThatContains($record, 'error');
    }

    public function hasWarning(array $record): bool
    {
        return $this->hasRecordThatContains($record, 'warning');
    }

    public function hasNotice(array $record): bool
    {
        return $this->hasRecordThatContains($record, 'notice');
    }

    public function hasCritical(array $record): bool
    {
        return $this->hasRecordThatContains($record, 'critical');
    }

    public function hasAlert(array $record): bool
    {
        return $this->hasRecordThatContains($record, 'alert');
    }

    public function hasEmergency(array $record): bool
    {
        return $this->hasRecordThatContains($record, 'emergency');
    }

    public function hasDebug(array $record): bool
    {
        return $this->hasRecordThatContains($record, 'debug');
    }

    private function hasRecordThatContains(array $record, string $level): bool
    {
        foreach ($this->records as $r) {
            if ($r['level'] !== $level) {
                continue;
            }

            if (isset($record['message']) && $r['message'] !== $record['message']) {
                continue;
            }

            if (isset($record['context'])) {
                foreach ($record['context'] as $key => $value) {
                    if (!isset($r['context'][$key]) || $r['context'][$key] !== $value) {
                        continue 2;
                    }
                }
            }

            return true;
        }

        return false;
    }

    /** @return array<int, array{level: mixed, message: mixed, context: array}> */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function clear(): void
    {
        $this->records = [];
    }
}
