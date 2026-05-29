<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use Illuminate\Support\Facades\Http;

class AzureGraphService
{
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
}

