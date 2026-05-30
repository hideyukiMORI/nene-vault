<?php

declare(strict_types=1);

namespace NeneVault\Tests\Auth;

use Nene2\Auth\TokenIssuerInterface;

final class InMemoryTokenIssuer implements TokenIssuerInterface
{
    /** @param array<string, mixed> $claims */
    public function issue(array $claims): string
    {
        return base64_encode(json_encode($claims, JSON_THROW_ON_ERROR));
    }
}
