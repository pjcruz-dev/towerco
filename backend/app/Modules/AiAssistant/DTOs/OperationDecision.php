<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

final readonly class OperationDecision
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public string $decision,
        public ?string $name = null,
        public array $arguments = [],
        public ?string $message = null,
        public ?string $purpose = null,
        public ?string $note = null,
    ) {}

    public static function answer(?string $message = null): self
    {
        return new self('answer', null, [], $message);
    }
}
