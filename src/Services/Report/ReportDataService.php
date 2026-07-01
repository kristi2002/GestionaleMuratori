<?php
declare(strict_types=1);

namespace App\Services\Report;

use App\Models\InterventionModel;
use App\Models\PhotoModel;
use App\Models\ProjectModel;
use App\Models\StockMovementModel;
use App\Support\Config;
use App\Support\Storage\LocalStorage;

/**
 * Gathers everything needed for a project report (§5), shared by both the
 * PDF and Excel builders and by the admin/client controllers that serve them.
 */
final class ReportDataService
{
    /** Photo types embedded in the report's photo grid (before/after, per §5). */
    private const GALLERY_TYPES = ['before', 'after'];

    public function build(int $projectId): ?array
    {
        $project = (new ProjectModel())->find($projectId);
        if ($project === null) {
            return null;
        }

        $storage = new LocalStorage((string) Config::get('storage.uploads_path'));
        $photoModel = new PhotoModel();

        $interventions = (new InterventionModel())->all(['project_id' => $projectId]);
        $completed     = 0;
        foreach ($interventions as &$intervention) {
            if ($intervention['status'] === 'completed') {
                $completed++;
            }

            $gallery = array_values(array_filter(
                $photoModel->forIntervention((int) $intervention['id']),
                static fn (array $p): bool => in_array($p['type'], self::GALLERY_TYPES, true)
            ));
            foreach ($gallery as &$photo) {
                $rel = $photo['thumb_path'] ?? $photo['file_path'];
                $photo['absolute_path'] = $storage->exists($rel) ? $storage->absolutePath($rel) : null;
            }
            unset($photo);
            $intervention['gallery'] = $gallery;

            $intervention['signature_absolute_path'] = null;
            if ($intervention['client_signature_path'] && $storage->exists($intervention['client_signature_path'])) {
                $intervention['signature_absolute_path'] = $storage->absolutePath($intervention['client_signature_path']);
            }
        }
        unset($intervention);

        return [
            'project'       => $project,
            'interventions' => $interventions,
            'materials'     => (new StockMovementModel())->usedByProject($projectId),
            'totals'        => [
                'count'     => count($interventions),
                'completed' => $completed,
            ],
            'generated_at'  => (new \DateTimeImmutable())->format('Y-m-d H:i'),
        ];
    }
}
