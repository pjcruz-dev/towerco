<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Support\EntraDirectoryPerson;
use Illuminate\Support\Facades\Http;

class AzureGraphService
{
    private const SELECT = 'id,mail,userPrincipalName,displayName,jobTitle,department,assignedLicenses';

    /**
     * @return list<string>
     */
    public function fetchGroupIds(string $accessToken): array
    {
        $groups = [];
        $url = 'https://graph.microsoft.com/v1.0/me/memberOf?$select=id';

        for ($i = 0; $i < 3; $i++) {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withToken($accessToken)
                ->get($url);

            if (! $response->successful()) {
                break;
            }

            $data = $response->json();
            $values = $data['value'] ?? [];
            if (is_array($values)) {
                foreach ($values as $item) {
                    $id = $item['id'] ?? null;
                    if (is_string($id)) {
                        $groups[] = $id;
                    }
                }
            }

            $next = $data['@odata.nextLink'] ?? null;
            if (! is_string($next) || $next === '') {
                break;
            }
            $url = $next;
        }

        return array_values(array_unique($groups));
    }

    public function fetchMe(string $accessToken): ?EntraDirectoryPerson
    {
        $response = Http::timeout(15)
            ->acceptJson()
            ->withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me?$select='.self::SELECT);

        if (! $response->successful()) {
            return null;
        }

        return EntraDirectoryPerson::fromGraph($response->json() ?? []);
    }

    public function fetchManager(string $accessToken): ?EntraDirectoryPerson
    {
        $response = Http::timeout(15)
            ->acceptJson()
            ->withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me/manager?$select='.self::SELECT);

        if (! $response->successful()) {
            return null;
        }

        return EntraDirectoryPerson::fromGraph($response->json() ?? []);
    }

    /**
     * @return list<EntraDirectoryPerson>
     */
    public function fetchDirectReports(string $accessToken): array
    {
        $response = Http::timeout(15)
            ->acceptJson()
            ->withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me/directReports?$select='.self::SELECT.'&$top=999');

        if (! $response->successful()) {
            return [];
        }

        $people = [];
        foreach ($response->json('value') ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $person = EntraDirectoryPerson::fromGraph($row);
            if ($person !== null) {
                $people[] = $person;
            }
        }

        return $people;
    }
}
