<?php

declare(strict_types=1);

namespace NeneVault\Demo;

use Nene2\Demo\DemoTemplateKeyInterface;

/**
 * Vault's demo template keys (`Nene2\Demo` consumer, #141).
 *
 * One template for now: `standard` — the received-document archive every
 * prospect conversation starts from ({@see DemoDataSeeder}'s three-industry
 * vendor mix). The enum exists so `/demo/{template}` can grow
 * industry-specific presets without touching the orchestration.
 */
enum DemoTemplate: string implements DemoTemplateKeyInterface
{
    case Standard = 'standard';

    public function value(): string
    {
        return $this->value;
    }

    public static function tryFromValue(string $value): ?static
    {
        return self::tryFrom($value);
    }
}
