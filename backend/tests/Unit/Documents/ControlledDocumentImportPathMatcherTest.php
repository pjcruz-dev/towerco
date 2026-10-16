<?php

declare(strict_types=1);

namespace Tests\Unit\Documents;

use App\Modules\Documents\Support\ControlledDocumentImportPathMatcher;
use Tests\TestCase;

final class ControlledDocumentImportPathMatcherTest extends TestCase
{
    public function test_windows_folder_path_matches_the_library_tail(): void
    {
        $matcher = new ControlledDocumentImportPathMatcher;
        $folder = 'C:\\Users\\PJ\\Downloads\\Approved\\Approved\\1. POLICIES & PROCEDURES\\OMD\\ATC-OMD-P-002 Leasing.pdf';
        $available = [
            'Approved/1. POLICIES & PROCEDURES/OMD/ATC-OMD-P-002 Leasing.pdf',
            'Approved/other/notes.pdf',
        ];

        $match = $matcher->match($folder, $available);

        $this->assertSame('matched', $match['status']);
        $this->assertSame($available[0], $match['path']);
    }

    public function test_same_file_name_in_two_folders_is_ambiguous_without_a_longer_tail(): void
    {
        $matcher = new ControlledDocumentImportPathMatcher;
        $match = $matcher->match('policy.docx', [
            'QMS/policy.docx',
            'OMD/policy.docx',
        ]);

        $this->assertSame('ambiguous', $match['status']);
    }

    public function test_blank_path_imports_without_a_file(): void
    {
        $matcher = new ControlledDocumentImportPathMatcher;

        $this->assertSame('none', $matcher->match('', ['QMS/policy.pdf'])['status']);
        $this->assertSame('none', $matcher->match(null, [])['status']);
    }

    public function test_unknown_extension_is_rejected(): void
    {
        $matcher = new ControlledDocumentImportPathMatcher;
        $match = $matcher->match('notes.exe', ['notes.exe']);

        $this->assertSame('invalid_type', $match['status']);
    }
}
