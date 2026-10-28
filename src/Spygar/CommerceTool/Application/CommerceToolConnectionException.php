<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application;

final class CommerceToolConnectionException extends \RuntimeException
{
    public function __construct(string $message, private string $field)
    {
        parent::__construct($message);
    }

    public function field(): string
    {
        return $this->field;
    }
}
