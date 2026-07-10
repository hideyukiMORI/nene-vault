<?php

declare(strict_types=1);

namespace NeneVault\Demo;

use Nene2\Demo\DemoDataSeederInterface;
use Nene2\Demo\DemoTemplateKeyInterface;

/**
 * `Nene2\Demo` seeder adapter (#141): the framework contract over the
 * org_id-parameterized {@see DemoDataSeeder} that #118 built for exactly this
 * adoption — the fixed-org reset tool (`tools/seed-demo.php`) and the
 * disposable `/demo/{template}` flow share one dataset, so the two demo models
 * can never drift apart.
 *
 * The seeded uploads are attributed to the org's throwaway admin (read from
 * {@see DemoProvisionRegistry}, recorded by the provisioner moments earlier in
 * the same request) so the audit trail shows a person, not a null actor.
 */
final readonly class DisposableDemoSeeder implements DemoDataSeederInterface
{
    public function __construct(
        private DemoDataSeeder $seeder,
        private DemoProvisionRegistry $registry,
    ) {
    }

    public function seed(int $orgId, DemoTemplateKeyInterface $template): void
    {
        // Single template today; the enum key exists for future presets.
        $this->seeder->seed($orgId, $this->registry->adminUserId($orgId));
    }
}
