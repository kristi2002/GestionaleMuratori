<?php
/**
 * Client account view (CRM): the new per-client profile queries — job history
 * (interventions), quotes with computed subtotal, and the originating lead.
 * In-process; cleans up.
 */
declare(strict_types=1);

use App\Models\ClientModel;
use App\Models\LeadModel;

/** @var PDO $pdo */

T::section('Client account: job history + quotes + originating lead');

$cm = new ClientModel();

// A client that actually has interventions, to exercise the project→client join.
$ivClient = (int) $pdo->query(
    'SELECT p.client_id FROM projects p JOIN interventions i ON i.project_id = p.id
     GROUP BY p.client_id ORDER BY COUNT(*) DESC LIMIT 1'
)->fetchColumn();
$ivs = $cm->interventionsForProfile($ivClient);
T::ok($ivs !== [], 'interventionsForProfile returns the client job history');
T::ok(array_key_exists('project_name', $ivs[0]) && array_key_exists('status', $ivs[0]), 'intervention rows carry project + status');

$quoteClient = (int) $pdo->query('SELECT client_id FROM quotes GROUP BY client_id ORDER BY COUNT(*) DESC LIMIT 1')->fetchColumn();
if ($quoteClient > 0) {
    $quotes = $cm->quotesForProfile($quoteClient);
    T::ok($quotes !== [], 'quotesForProfile returns the client quotes');
    T::ok(array_key_exists('subtotal', $quotes[0]), 'quote rows carry a computed subtotal');
} else {
    T::ok(true, 'no seeded quotes (skipped)');
}

// Originating-lead link: convert a lead into this client, confirm leadForClient finds it.
$anyClient = (int) $pdo->query('SELECT id FROM clients ORDER BY id LIMIT 1')->fetchColumn();
T::ok($cm->leadForClient($anyClient) === null, 'no originating lead before conversion');
$lm  = new LeadModel();
$lid = $lm->create(['name' => 'CLIENTLEADTEST', 'email' => null, 'phone' => '1', 'message' => 'x', 'source' => 'web', 'ip' => null]);
$lm->markConverted($lid, $anyClient);
$lead = $cm->leadForClient($anyClient);
T::ok($lead !== null && (int) $lead['id'] === $lid, 'leadForClient returns the originating lead');

// Teardown.
$pdo->exec("DELETE FROM leads WHERE id = {$lid}");
T::ok($cm->leadForClient($anyClient) === null, 'teardown removed the lead');
