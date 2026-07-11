<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * One-shot conversion for #161: `organizations.created_at` / `updated_at` were
 * written with `date()` in the host's default timezone; from #161 on they are
 * written in UTC and the demo sweep parses them as UTC. This migration shifts
 * every existing row from the host default timezone (as configured at
 * migration time — php.ini `date.timezone`, JST on the live demo host) to
 * UTC, DST-correct via DateTime.
 *
 * DEPLOY ORDER MATTERS: apply this migration together with the #161 code
 * switch, before the next hourly sweep tick. A row written in UTC by the new
 * code and then shifted again by this migration would look older by the UTC
 * offset (9 h on JST) and a demo org could be reaped early. On a UTC host the
 * migration is a no-op.
 */
final class ConvertOrganizationsTimestampsToUtc extends AbstractMigration
{
    public function up(): void
    {
        $this->convert(date_default_timezone_get(), 'UTC');
    }

    public function down(): void
    {
        $this->convert('UTC', date_default_timezone_get());
    }

    private function convert(string $fromTz, string $toTz): void
    {
        if ($fromTz === $toTz) {
            return;
        }

        $from = new DateTimeZone($fromTz);
        $to = new DateTimeZone($toTz);

        $rows = $this->fetchAll('SELECT id, created_at, updated_at FROM organizations');

        foreach ($rows as $row) {
            $createdAt = $this->shift((string) $row['created_at'], $from, $to);
            $updatedAt = $this->shift((string) $row['updated_at'], $from, $to);

            if ($createdAt === null || $updatedAt === null) {
                continue; // unparseable value — leave untouched rather than corrupt
            }

            $this->execute(sprintf(
                "UPDATE organizations SET created_at = '%s', updated_at = '%s' WHERE id = %d",
                $createdAt,
                $updatedAt,
                (int) $row['id'],
            ));
        }
    }

    private function shift(string $value, DateTimeZone $from, DateTimeZone $to): ?string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $from);

        if ($dt === false) {
            return null;
        }

        return $dt->setTimezone($to)->format('Y-m-d H:i:s');
    }
}
