<?php

declare(strict_types=1);

namespace Tests\Unit\Documents;

use App\Modules\Documents\Support\ControlledDocumentPublishedFileToken;
use PHPUnit\Framework\TestCase;

final class ControlledDocumentPublishedFileTokenTest extends TestCase
{
    public function test_token_matches_the_document_and_rejects_another_document(): void
    {
        $token = ControlledDocumentPublishedFileToken::sign('doc-1', 'tenant-a', 'test-key');

        $this->assertTrue(ControlledDocumentPublishedFileToken::matches('doc-1', $token, 'tenant-a', 'test-key'));
        $this->assertFalse(ControlledDocumentPublishedFileToken::matches('doc-2', $token, 'tenant-a', 'test-key'));
        $this->assertFalse(ControlledDocumentPublishedFileToken::matches('doc-1', $token, 'tenant-b', 'test-key'));
        $this->assertFalse(ControlledDocumentPublishedFileToken::matches('doc-1', '', 'tenant-a', 'test-key'));
    }
}
