<?php
use App\Support\Lang;
use App\Support\View;

/**
 * Soft colored status pill: render with
 * View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => $status], null).
 * @var string $group Lang group ('intervention_status' | 'project_status')
 * @var string $value ENUM value from the DB
 */
$tones = [
    'intervention_status' => [
        'pending'     => 'neutral',
        'in_progress' => 'info',
        'on_hold'     => 'warning',
        'completed'   => 'success',
        'cancelled'   => 'danger',
    ],
    'project_status' => [
        'active'  => 'success',
        'on_hold' => 'warning',
        'closed'  => 'neutral',
    ],
    'invoice_status' => [
        'draft'  => 'neutral',
        'issued' => 'info',
        'paid'   => 'success',
    ],
];
$tone = $tones[$group][$value] ?? 'neutral';
?>
<span class="badge rounded-pill app-status app-status-<?= View::e($tone) ?>"><?= View::e(Lang::label($group, $value)) ?></span>
