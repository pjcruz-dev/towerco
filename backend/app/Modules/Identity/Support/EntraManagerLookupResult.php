<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

final class EntraManagerLookupResult
{
    public const CODE_OK = 'ok';

    public const CODE_NOT_CONFIGURED = 'not_configured';

    public const CODE_DIRECTORY_COMMON = 'directory_common';

    public const CODE_TOKEN_FAILED = 'token_failed';

    public const CODE_FORBIDDEN = 'forbidden';

    public const CODE_USER_NOT_FOUND = 'user_not_found';

    public const CODE_NO_MANAGER = 'no_manager';

    public const CODE_GRAPH_ERROR = 'graph_error';

    public function __construct(
        public readonly bool $ok,
        public readonly string $code,
        public readonly string $message,
        public readonly ?EntraDirectoryPerson $manager = null,
        public readonly ?EntraDirectoryPerson $requestor = null,
        public readonly ?int $httpStatus = null,
    ) {}

    public static function fail(string $code, string $message, ?int $httpStatus = null, ?EntraDirectoryPerson $requestor = null): self
    {
        return new self(
            ok: false,
            code: $code,
            message: $message,
            requestor: $requestor,
            httpStatus: $httpStatus,
        );
    }
}
