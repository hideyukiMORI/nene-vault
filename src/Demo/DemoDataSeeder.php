<?php

declare(strict_types=1);

namespace NeneVault\Demo;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\ClockInterface;
use NeneVault\Document\RestoreDocumentUseCaseInterface;
use NeneVault\Document\UploadDocumentInput;
use NeneVault\Document\UploadDocumentUseCaseInterface;
use NeneVault\Document\VoidDocumentUseCaseInterface;

/**
 * Seeds one organization with a T-relative received-document demo dataset
 * (#118): ~20 generated invoice PDFs from fictional Japanese vendors (the
 * three invoice-demo industries — construction / building maintenance /
 * creative — so sales can tell one story across products), spread over the
 * past 12 months, with some void→restore history so the audit trail and
 * document history have movement.
 *
 * **org_id-parameterized by design** (owner decision 07-09): next round's
 * `Nene2\Demo` disposable-org adoption calls this same class from a
 * `DemoDataSeederInterface` shim, so the fixed-org tool and the future
 * `/demo/{template}` flow share one seeder.
 *
 * Documents go through the real {@see UploadDocumentUseCaseInterface} —
 * SHA-256, version rows, retention calculation and audit events are all
 * authentic — then upload/audit timestamps are spread back over the year
 * (the one thing a use case cannot do). PDF bodies are romanized (base-14
 * fonts carry no CJK); the Japanese vendor names live in the searchable
 * metadata.
 */
final readonly class DemoDataSeeder
{
    /**
     * Vendors: Japanese name (metadata), romaji (PDF body), T-number,
     * industry line items.
     *
     * @var list<array{string, string, string, list<string>}>
     */
    private const array VENDORS = [
        ['大和建設株式会社', 'Yamato Kensetsu K.K.', 'T1234567890123', ['Scaffolding work', 'Foundation work', 'Site expenses']],
        ['株式会社山田工務店', 'Yamada Koumuten Co., Ltd.', 'T2345678901234', ['Carpentry work', 'Interior finishing']],
        ['あおぞらビルメンテナンス株式会社', 'Aozora Building Maintenance', 'T3456789012345', ['Monthly cleaning service', 'Equipment inspection']],
        ['ミナト設備管理株式会社', 'Minato Facility Management', 'T4567890123456', ['HVAC maintenance', 'Elevator inspection']],
        ['クリエイトワークス株式会社', 'Create Works Inc.', 'T5678901234567', ['Design direction', 'Web production']],
        ['株式会社スタジオ青海', 'Studio Oumi Co., Ltd.', 'T6789012345678', ['Video production', 'Monthly retainer']],
        ['さくらオフィスサプライ株式会社', 'Sakura Office Supply', 'T7890123456789', ['Office supplies', 'Toner cartridges']],
        ['東商事株式会社', 'Azuma Shoji K.K.', 'T8901234567890', ['Materials wholesale', 'Delivery charge']],
        ['藤沢電機株式会社', 'Fujisawa Denki K.K.', 'T9012345678901', ['Electrical work', 'Fixture replacement']],
    ];

    public function __construct(
        private UploadDocumentUseCaseInterface $upload,
        private VoidDocumentUseCaseInterface $void,
        private RestoreDocumentUseCaseInterface $restore,
        private DatabaseQueryExecutorInterface $query,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{documents: int, voided: int, restored: int}
     */
    public function seed(int $orgId, ?int $actorUserId): array
    {
        $today = $this->clock->now()->setTime(0, 0);
        mt_srand(20260712 + $orgId); // deterministic content; only T moves

        $documentIds = [];
        $docCount = 20;

        for ($i = 0; $i < $docCount; $i++) {
            $vendor = self::VENDORS[$i % count(self::VENDORS)];
            // Spread over the past 12 months; a few in the current month so the
            // dashboard looks alive. 電帳法's period search is the showcase.
            $daysAgo = $i < 3 ? mt_rand(3, 20) : mt_rand(21, 360);
            $txDate = $today->modify(sprintf('-%d days', $daysAgo));

            $lineDefs = $vendor[3];
            $lines = [];
            $total = 0;
            $lineCount = mt_rand(1, min(2, count($lineDefs)));
            for ($l = 0; $l < $lineCount; $l++) {
                $yen = mt_rand(33, 1650) * 1000;
                $lines[] = [$lineDefs[($i + $l) % count($lineDefs)], $yen];
                $total += $yen;
            }

            $invoiceNumber = sprintf('INV-%s-%03d', $txDate->format('Ym'), $i + 1);
            $pdf = DemoInvoicePdf::build(
                vendorRomaji: $vendor[1],
                registrationNumber: $vendor[2],
                invoiceNumber: $invoiceNumber,
                issueDate: $txDate->format('Y-m-d'),
                lines: $lines,
                totalYen: $total,
            );

            $tmp = tempnam(sys_get_temp_dir(), 'vault-demo-');
            if ($tmp === false) {
                throw new \RuntimeException('Could not create a temp file for the demo PDF.');
            }
            file_put_contents($tmp, $pdf);

            try {
                $category = match (true) {
                    $i % 7 === 5 => 'receipt',
                    $i % 7 === 6 => 'delivery_note',
                    default => 'invoice_received',
                };
                $output = $this->upload->execute(new UploadDocumentInput(
                    organizationId: $orgId,
                    tmpPath: $tmp,
                    originalFilename: $invoiceNumber . '.pdf',
                    mimeType: 'application/pdf',
                    fileSizeBytes: strlen($pdf),
                    counterpartyName: $vendor[0],
                    category: $category,
                    transactionDate: $txDate->format('Y-m-d'),
                    // JPY has no minor unit: amount_cents stores whole yen
                    // (naming-conventions), matching the PDF total. Never x100.
                    amountCents: $total,
                    tags: $i % 3 === 0 ? ['月次'] : [],
                    source: 'web_upload',
                    confirmDuplicate: true,
                    actorUserId: $actorUserId,
                ));
                $document = $output->document;
            } finally {
                @unlink($tmp);
            }

            $documentIds[] = $document->id;

            // Backdate what the use case rightly stamped with "now": the demo
            // must look like a year of steady operation, not one bulk upload.
            $uploadedAt = $txDate->modify(sprintf('+%d days', mt_rand(1, 3)))->format('Y-m-d')
                . sprintf(' %02d:%02d:%02d', mt_rand(9, 17), mt_rand(0, 59), mt_rand(0, 59));
            $this->query->execute('UPDATE vault_documents SET uploaded_at = ? WHERE id = ?', [$uploadedAt, $document->id]);
            $this->query->execute('UPDATE document_versions SET uploaded_at = ? WHERE vault_document_id = ?', [$uploadedAt, $document->id]);
            $this->query->execute(
                "UPDATE audit_events SET created_at = ? WHERE organization_id = ? AND entity_type = 'vault_document' AND entity_id = ?",
                [$uploadedAt, $orgId, $document->id],
            );
        }

        // History movement: two docs voided then restored, one left voided —
        // the void/restore trail and the voided-state filter both show life.
        $voided = 0;
        $restored = 0;
        foreach ([4 => true, 9 => true, 14 => false] as $index => $restoreIt) {
            $documentId = $documentIds[$index];
            $this->void->execute($documentId, $orgId, '誤アップロード', '別書類と取り違えたため無効化', $actorUserId);
            $voided++;
            if ($restoreIt) {
                $this->restore->execute($documentId, $orgId, $actorUserId);
                $restored++;
            }
        }

        return ['documents' => $docCount, 'voided' => $voided, 'restored' => $restored];
    }
}
