<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

use App\Modules\ProcurementOne\Models\ProcurementVendor;

final class ProcurementVendorContactResolver
{
    public function resolveEmail(?ProcurementVendor $vendor): ?string
    {
        if ($vendor === null) {
            return null;
        }

        $contact = is_array($vendor->contact_json) ? $vendor->contact_json : [];
        $candidates = [
            $contact['email'] ?? null,
            $contact['contact_email'] ?? null,
            $contact['primary_email'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $email = strtolower(trim((string) $candidate));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }
}
