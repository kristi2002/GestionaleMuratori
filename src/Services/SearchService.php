<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use PDO;

/**
 * Global admin search across the main entities. Read-only LIKE lookups, a few
 * results per group. Each result carries a URL so the view stays dumb.
 */
final class SearchService
{
    private const PER_GROUP = 8;

    /** @return array<string,array<int,array<string,mixed>>> group => rows */
    public function search(string $q): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $pdo  = Database::pdo();
        $like = '%' . $q . '%';

        return array_filter([
            'projects'      => $this->projects($pdo, $like),
            'interventions' => $this->interventions($pdo, $like),
            'clients'       => $this->clients($pdo, $like),
            'subcontractors'=> $this->subcontractors($pdo, $like),
            'warehouse'     => $this->warehouse($pdo, $like),
        ], static fn (array $g): bool => $g !== []);
    }

    private function projects(PDO $pdo, string $like): array
    {
        $stmt = $pdo->prepare(
            "SELECT p.id, p.name, p.location, p.status, c.name AS client_name
             FROM projects p JOIN clients c ON c.id = p.client_id
             WHERE p.name LIKE ? OR p.location LIKE ? OR c.name LIKE ?
             ORDER BY p.start_date DESC LIMIT " . self::PER_GROUP
        );
        $stmt->execute([$like, $like, $like]);
        return array_map(fn (array $r): array => [
            'title'    => $r['name'],
            'subtitle' => trim(($r['client_name'] ?? '') . ' · ' . ($r['location'] ?? ''), ' ·'),
            'status'   => ['group' => 'project_status', 'value' => $r['status']],
            'url'      => '/admin/projects/' . $r['id'],
        ], $stmt->fetchAll());
    }

    private function interventions(PDO $pdo, string $like): array
    {
        $stmt = $pdo->prepare(
            "SELECT i.id, i.title, i.status, p.name AS project_name
             FROM interventions i JOIN projects p ON p.id = i.project_id
             WHERE i.title LIKE ? OR p.name LIKE ?
             ORDER BY i.scheduled_date IS NULL, i.scheduled_date DESC LIMIT " . self::PER_GROUP
        );
        $stmt->execute([$like, $like]);
        return array_map(fn (array $r): array => [
            'title'    => $r['title'],
            'subtitle' => $r['project_name'],
            'status'   => ['group' => 'intervention_status', 'value' => $r['status']],
            'url'      => '/admin/interventions/' . $r['id'],
        ], $stmt->fetchAll());
    }

    private function clients(PDO $pdo, string $like): array
    {
        $stmt = $pdo->prepare(
            "SELECT id, name, email, vat_or_tax_id FROM clients
             WHERE name LIKE ? OR email LIKE ? OR vat_or_tax_id LIKE ?
             ORDER BY name LIMIT " . self::PER_GROUP
        );
        $stmt->execute([$like, $like, $like]);
        return array_map(fn (array $r): array => [
            'title'    => $r['name'],
            'subtitle' => (string) ($r['email'] ?? $r['vat_or_tax_id'] ?? ''),
            'status'   => null,
            'url'      => '/admin/clients?q=' . rawurlencode($r['name']),
        ], $stmt->fetchAll());
    }

    private function subcontractors(PDO $pdo, string $like): array
    {
        $stmt = $pdo->prepare(
            "SELECT id, name, email, vat_or_tax_id FROM subcontractors
             WHERE name LIKE ? OR email LIKE ? OR vat_or_tax_id LIKE ?
             ORDER BY name LIMIT " . self::PER_GROUP
        );
        $stmt->execute([$like, $like, $like]);
        return array_map(fn (array $r): array => [
            'title'    => $r['name'],
            'subtitle' => (string) ($r['vat_or_tax_id'] ?? ''),
            'status'   => null,
            'url'      => '/admin/subcontractors?q=' . rawurlencode($r['name']),
        ], $stmt->fetchAll());
    }

    private function warehouse(PDO $pdo, string $like): array
    {
        $stmt = $pdo->prepare(
            "SELECT id, name, sku FROM warehouse_items
             WHERE name LIKE ? OR sku LIKE ?
             ORDER BY name LIMIT " . self::PER_GROUP
        );
        $stmt->execute([$like, $like]);
        return array_map(fn (array $r): array => [
            'title'    => $r['name'],
            'subtitle' => (string) ($r['sku'] ?? ''),
            'status'   => null,
            'url'      => '/admin/warehouse/' . $r['id'],
        ], $stmt->fetchAll());
    }
}
