<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Support\EntraDirectoryPerson;
use PHPUnit\Framework\TestCase;

final class EntraDirectoryPersonTest extends TestCase
{
    public function test_from_graph_reads_department(): void
    {
        $person = EntraDirectoryPerson::fromGraph([
            'id' => 'entra-1',
            'mail' => 'user@example.com',
            'displayName' => 'Sample User',
            'jobTitle' => 'Analyst',
            'department' => 'Quality Management',
            'assignedLicenses' => [],
        ]);

        $this->assertNotNull($person);
        $this->assertSame('Analyst', $person->jobTitle);
        $this->assertSame('Quality Management', $person->department);
    }

    public function test_from_graph_treats_blank_department_as_null(): void
    {
        $person = EntraDirectoryPerson::fromGraph([
            'id' => 'entra-2',
            'userPrincipalName' => 'user@example.com',
            'displayName' => 'Sample User',
            'department' => '   ',
        ]);

        $this->assertNotNull($person);
        $this->assertNull($person->department);
    }
}
