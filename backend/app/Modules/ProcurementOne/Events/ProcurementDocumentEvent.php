<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Events;

use App\Core\Events\DomainEvent;

abstract class ProcurementDocumentEvent extends DomainEvent
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $documentType,
        public readonly string $documentId,
        public readonly ?string $documentNo,
        public readonly ?string $actorUserId,
        public readonly array $metadata = [],
    ) {}

    public function eventName(): string
    {
        return static::name();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'event' => $this->eventName(),
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
            'document_no' => $this->documentNo,
            'actor_user_id' => $this->actorUserId,
            'metadata' => $this->metadata,
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    abstract public static function name(): string;
}
