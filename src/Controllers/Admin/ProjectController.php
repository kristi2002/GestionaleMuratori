<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ClientModel;
use App\Models\ProjectAbsenceModel;
use App\Models\ProjectDocumentModel;
use App\Models\ProjectInvoiceModel;
use App\Models\ProjectMaterialModel;
use App\Models\ProjectModel;
use App\Models\StockLocationModel;
use App\Models\UserModel;
use App\Models\WarehouseItemModel;
use App\Support\Auth;
use App\Support\Config;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Storage\LocalStorage;
use App\Support\View;

final class ProjectController
{
    private const STATUSES = ['active', 'on_hold', 'closed'];

    private const INVOICE_STATUSES = ['draft', 'issued', 'paid'];

    /** Attachment whitelist: extension => Content-Type served on download. */
    private const DOCUMENT_TYPES = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const DOCUMENT_MAX_BYTES = 10 * 1024 * 1024;

    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $filters = [
            'search'    => trim((string) $request->input('q', '')),
            'client_id' => (int) $request->input('client_id', 0),
            'status'    => (string) $request->input('status', ''),
        ];

        Response::html(View::render('admin/projects/index', [
            'title'    => Lang::get('admin.projects.title'),
            'projects' => (new ProjectModel())->all($filters),
            'clients'  => (new ClientModel())->all(),
            'filters'  => $filters,
            'statuses' => self::STATUSES,
        ], 'layout'));
    }

    /** GET /admin/projects/{id} — project dashboard: documents, invoices, materials summary. */
    public function show(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $project = (new ProjectModel())->find((int) $id);
        if ($project === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        $materialModel = new ProjectMaterialModel();

        // Attendance register month: ?att=YYYY-MM, defaulting to the current month.
        $att = (string) $request->input('att', '');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $att)) {
            $att = date('Y-m');
        }
        $attFirst = new \DateTimeImmutable($att . '-01');
        $attLast  = $attFirst->format('Y-m-t');

        // Active workers not yet on the roster feed the "+ Assegna operaio" picker.
        $projectModel = new ProjectModel();
        $assignedIds  = $projectModel->workerIds((int) $id);
        $available    = array_values(array_filter(
            (new UserModel())->listByRole('worker'),
            static fn (array $w): bool => !in_array((int) $w['id'], $assignedIds, true)
        ));

        Response::html(View::render('admin/projects/show', [
            'title'            => (string) $project['name'],
            'project'          => $project,
            'documents'        => (new ProjectDocumentModel())->forProject((int) $id),
            'invoices'         => (new ProjectInvoiceModel())->forProject((int) $id),
            'projectMaterials' => $materialModel->forProject((int) $id),
            'materials'        => $materialModel->summaryForProject((int) $id),
            'warehouseItems'   => (new WarehouseItemModel())->all(),
            'invoiceStatuses'  => self::INVOICE_STATUSES,
            'attWorkers'       => $projectModel->workers((int) $id),
            'attAvailable'     => $available,
            'attAbsences'      => (new ProjectAbsenceModel())->forRange((int) $id, $attFirst->format('Y-m-d'), $attLast),
            'attMonth'         => $attFirst,
            'attPrev'          => $attFirst->modify('-1 month')->format('Y-m'),
            'attNext'          => $attFirst->modify('+1 month')->format('Y-m'),
        ], 'layout'));
    }

    /** POST /admin/projects/{id}/workers — {worker_id}: adds a worker to the roster. */
    public function assignWorker(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model   = new ProjectModel();
        $project = $model->find((int) $id);
        if ($project === null) {
            Response::fail(Lang::get('admin.projects.not_found'), 404);
            return;
        }

        $workerId = (int) $request->input('worker_id', 0);
        $worker   = $workerId > 0 ? (new UserModel())->findById($workerId) : null;
        if ($worker === null || $worker['role'] !== 'worker' || (int) $worker['is_active'] !== 1) {
            Response::fail(Lang::get('admin.projects.worker_invalid'), 422);
            return;
        }
        if (in_array($workerId, $model->workerIds((int) $id), true)) {
            Response::fail(Lang::get('admin.projects.attendance_already_assigned'), 422);
            return;
        }

        $model->addWorker((int) $id, $workerId);

        // Absences already on record for the displayed month (a re-assigned
        // worker keeps history), so the client-built calendar renders truthfully.
        $absences = [];
        $month    = (string) $request->input('month', '');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $first    = new \DateTimeImmutable($month . '-01');
            $absences = (new ProjectAbsenceModel())
                ->datesFor((int) $id, $workerId, $first->format('Y-m-d'), $first->format('Y-m-t'));
        }

        Response::ok(['id' => $workerId, 'name' => (string) $worker['name'], 'absences' => $absences]);
    }

    /** POST /admin/projects/{id}/workers/{workerId}/remove — unassigns a roster worker. */
    public function unassignWorker(Request $request, string $id, string $workerId): void
    {
        AuthGuard::require($request, ['admin']);

        $model   = new ProjectModel();
        $project = $model->find((int) $id);
        if ($project === null) {
            Response::fail(Lang::get('admin.projects.not_found'), 404);
            return;
        }

        if (!$model->removeWorker((int) $id, (int) $workerId)) {
            Response::fail(Lang::get('admin.projects.attendance_worker_invalid'), 404);
            return;
        }
        Response::ok();
    }

    /**
     * POST /admin/projects/{id}/attendance — {worker_id, date}: flips the day
     * between present (default) and absent for this project's register.
     */
    public function toggleAttendance(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model   = new ProjectModel();
        $project = $model->find((int) $id);
        if ($project === null) {
            Response::fail(Lang::get('admin.projects.not_found'), 404);
            return;
        }

        $workerId = (int) $request->input('worker_id', 0);
        if ($workerId <= 0 || !in_array($workerId, $model->workerIds((int) $id), true)) {
            Response::fail(Lang::get('admin.projects.attendance_worker_invalid'), 422);
            return;
        }

        $date = (string) $request->input('date', '');
        if (!$this->isValidDate($date)) {
            Response::fail(Lang::get('admin.projects.attendance_date_invalid'), 422);
            return;
        }

        $status = (new ProjectAbsenceModel())->toggle((int) $id, $workerId, $date);
        Response::ok(['status' => $status]);
    }

    /** POST /admin/projects/{id}/materials — log a material used on this project. */
    public function storeMaterial(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $project = (new ProjectModel())->find((int) $id);
        if ($project === null) {
            Response::fail(Lang::get('admin.projects.not_found'), 404);
            return;
        }

        $itemId = (int) $request->input('item_id', 0);
        if ($itemId <= 0) {
            Response::fail(Lang::get('admin.projects.material_item_required'), 422);
            return;
        }
        if ((new WarehouseItemModel())->find($itemId) === null) {
            Response::fail(Lang::get('admin.projects.material_item_invalid'), 422);
            return;
        }

        $qtyRaw = str_replace(',', '.', trim((string) $request->input('qty', '')));
        if ($qtyRaw === '' || !is_numeric($qtyRaw) || (float) $qtyRaw <= 0) {
            Response::fail(Lang::get('admin.projects.material_qty_invalid'), 422);
            return;
        }

        $note = trim((string) $request->input('note', ''));

        $materialId = (new ProjectMaterialModel())->create([
            'project_id' => (int) $id,
            'item_id'    => $itemId,
            'qty'        => number_format((float) $qtyRaw, 3, '.', ''),
            'note'       => $note !== '' ? mb_substr($note, 0, 255) : null,
            'created_by' => Auth::id(),
        ]);

        Response::ok(['id' => $materialId]);
    }

    /** POST /admin/projects/{id}/materials/{materialId}/delete */
    public function deleteMaterial(Request $request, string $id, string $materialId): void
    {
        AuthGuard::require($request, ['admin']);

        $model    = new ProjectMaterialModel();
        $material = $model->find((int) $materialId);
        if ($material === null || (int) $material['project_id'] !== (int) $id) {
            Response::fail(Lang::get('admin.projects.material_not_found'), 404);
            return;
        }

        $model->delete((int) $materialId);
        Response::ok();
    }

    /** POST /admin/projects/{id}/documents — attach a document (multipart). */
    public function uploadDocument(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $project = (new ProjectModel())->find((int) $id);
        if ($project === null) {
            Response::fail(Lang::get('admin.projects.not_found'), 404);
            return;
        }

        $file = $_FILES['document'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::fail(Lang::get('admin.projects.document_upload_failed'), 422);
            return;
        }
        if ((int) $file['size'] > self::DOCUMENT_MAX_BYTES) {
            Response::fail(Lang::get('admin.projects.document_too_large'), 422);
            return;
        }

        $originalName = (string) $file['name'];
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::DOCUMENT_TYPES[$ext])) {
            Response::fail(Lang::get('admin.projects.document_invalid_type'), 422);
            return;
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $title = pathinfo($originalName, PATHINFO_FILENAME);
        }

        $relPath = sprintf('documents/%d/%s_%s.%s', (int) $id, date('YmdHis'), bin2hex(random_bytes(4)), $ext);
        $storage = new LocalStorage((string) Config::get('storage.uploads_path'));
        $storage->putUploadedFile($relPath, $file['tmp_name']);

        $docId = (new ProjectDocumentModel())->create([
            'project_id'    => (int) $id,
            'title'         => mb_substr($title, 0, 150),
            'original_name' => mb_substr($originalName, 0, 255),
            'file_path'     => $relPath,
            'mime_type'     => self::DOCUMENT_TYPES[$ext],
            'size_bytes'    => (int) $file['size'],
            'uploaded_by'   => Auth::id(),
        ]);

        Response::ok(['id' => $docId]);
    }

    /** GET /admin/projects/{id}/documents/{docId} — download an attachment. */
    public function downloadDocument(Request $request, string $id, string $docId): void
    {
        AuthGuard::require($request, ['admin']);

        $document = (new ProjectDocumentModel())->find((int) $docId);
        $storage  = new LocalStorage((string) Config::get('storage.uploads_path'));
        if ($document === null || (int) $document['project_id'] !== (int) $id
            || !$storage->exists((string) $document['file_path'])) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        header('Content-Type: ' . $document['mime_type']);
        header('Content-Disposition: attachment; filename="'
            . str_replace(['"', "\r", "\n"], '', (string) $document['original_name']) . '"');
        header('Content-Length: ' . (string) $document['size_bytes']);
        echo $storage->get((string) $document['file_path']);
    }

    /** POST /admin/projects/{id}/documents/{docId}/delete */
    public function deleteDocument(Request $request, string $id, string $docId): void
    {
        AuthGuard::require($request, ['admin']);

        $model    = new ProjectDocumentModel();
        $document = $model->find((int) $docId);
        if ($document === null || (int) $document['project_id'] !== (int) $id) {
            Response::fail(Lang::get('admin.projects.document_not_found'), 404);
            return;
        }

        (new LocalStorage((string) Config::get('storage.uploads_path')))->delete((string) $document['file_path']);
        $model->delete((int) $docId);
        Response::ok();
    }

    /** POST /admin/projects/{id}/invoices — register/link an invoice. */
    public function storeInvoice(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $project = (new ProjectModel())->find((int) $id);
        if ($project === null) {
            Response::fail(Lang::get('admin.projects.not_found'), 404);
            return;
        }

        $number = trim((string) $request->input('number', ''));
        if ($number === '') {
            Response::fail(Lang::get('admin.projects.invoice_number_required'), 422);
            return;
        }

        $issueDate = trim((string) $request->input('issue_date', ''));
        if (!$this->isValidDate($issueDate)) {
            Response::fail(Lang::get('admin.projects.invoice_date_invalid'), 422);
            return;
        }

        $amountRaw = str_replace(',', '.', trim((string) $request->input('amount', '')));
        if ($amountRaw === '') {
            Response::fail(Lang::get('admin.invoices.amount_required'), 422);
            return;
        }
        if (!is_numeric($amountRaw) || (float) $amountRaw <= 0) {
            Response::fail(Lang::get('admin.projects.invoice_amount_invalid'), 422);
            return;
        }
        $amount = number_format((float) $amountRaw, 2, '.', '');

        $status = (string) $request->input('status', 'issued');
        if (!in_array($status, self::INVOICE_STATUSES, true)) {
            Response::fail(Lang::get('admin.projects.invoice_status_invalid'), 422);
            return;
        }

        $note = trim((string) $request->input('note', ''));

        $invoiceId = (new ProjectInvoiceModel())->create([
            'project_id' => (int) $id,
            'number'     => mb_substr($number, 0, 100),
            'issue_date' => $issueDate,
            'amount'     => $amount,
            'status'     => $status,
            'note'       => $note !== '' ? mb_substr($note, 0, 255) : null,
            'created_by' => Auth::id(),
        ]);

        Response::ok(['id' => $invoiceId]);
    }

    /** POST /admin/projects/{id}/invoices/{invoiceId}/delete */
    public function deleteInvoice(Request $request, string $id, string $invoiceId): void
    {
        AuthGuard::require($request, ['admin']);

        $model   = new ProjectInvoiceModel();
        $invoice = $model->find((int) $invoiceId);
        if ($invoice === null || (int) $invoice['project_id'] !== (int) $id) {
            Response::fail(Lang::get('admin.projects.invoice_not_found'), 404);
            return;
        }

        $model->delete((int) $invoiceId);
        Response::ok();
    }

    /** GET /admin/projects/create — dedicated create page (no modal). */
    public function create(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/projects/form', [
            'title'             => Lang::get('admin.projects.new'),
            'project'           => null,
            'clients'           => (new ClientModel())->all(),
            'workers'           => (new UserModel())->listByRole('worker'),
            'assignedWorkerIds' => [],
            'statuses'          => self::STATUSES,
            // ?client_id= pre-selects the client (e.g. "+ Aggiungi locale" from the client profile).
            'preselectedClientId' => (int) $request->input('client_id', 0),
        ], 'layout'));
    }

    /** GET /admin/projects/{id}/edit — dedicated edit page (no modal). */
    public function edit(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model   = new ProjectModel();
        $project = $model->find((int) $id);
        if ($project === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/projects/form', [
            'title'             => Lang::get('admin.projects.edit'),
            'project'           => $project,
            'clients'           => (new ClientModel())->all(),
            'workers'           => (new UserModel())->listByRole('worker'),
            'assignedWorkerIds' => $model->workerIds((int) $id),
            'statuses'          => self::STATUSES,
        ], 'layout'));
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }
        $workerIds = $data['worker_ids'];
        unset($data['worker_ids']);

        $model = new ProjectModel();
        $id    = $model->create($data);
        $model->syncWorkers($id, $workerIds);
        // Every project gets its own site location so material can be transferred
        // warehouse -> cantiere and tracked with a per-site balance (Desktop multisite).
        (new StockLocationModel())->ensureForProject($id, (string) $data['name']);
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model   = new ProjectModel();
        $project = $model->find((int) $id);
        if ($project === null) {
            Response::fail(Lang::get('admin.projects.not_found'), 404);
            return;
        }

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }
        $workerIds = $data['worker_ids'];
        unset($data['worker_ids']);

        $model->update((int) $id, $data);
        $model->syncWorkers((int) $id, $workerIds);
        Response::ok();
    }

    public function destroy(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model   = new ProjectModel();
        $project = $model->find((int) $id);
        if ($project === null) {
            Response::fail(Lang::get('admin.projects.not_found'), 404);
            return;
        }

        $model->delete((int) $id);
        Response::ok();
    }

    /** @return array<string,mixed>|null Validated fields, or null if a fail response was already sent. */
    private function validated(Request $request): ?array
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Response::fail(Lang::get('admin.projects.name_required'), 422);
            return null;
        }

        $clientId = (int) $request->input('client_id', 0);
        if ($clientId <= 0) {
            Response::fail(Lang::get('admin.projects.client_required'), 422);
            return null;
        }
        if ((new ClientModel())->find($clientId) === null) {
            Response::fail(Lang::get('admin.projects.client_invalid'), 422);
            return null;
        }

        $startDate = trim((string) $request->input('start_date', ''));
        if (!$this->isValidDate($startDate)) {
            Response::fail(Lang::get('admin.projects.start_date_required'), 422);
            return null;
        }

        $endDate = trim((string) $request->input('end_date', ''));
        $endDate = $endDate !== '' ? $endDate : null;
        if ($endDate !== null && (!$this->isValidDate($endDate) || $endDate < $startDate)) {
            Response::fail(Lang::get('admin.projects.end_date_invalid'), 422);
            return null;
        }

        $status = (string) $request->input('status', 'active');
        if (!in_array($status, self::STATUSES, true)) {
            Response::fail(Lang::get('admin.projects.status_invalid'), 422);
            return null;
        }

        $invoiceReference = trim((string) $request->input('invoice_reference', ''));
        $location          = trim((string) $request->input('location', ''));

        $workerIds = $request->input('worker_ids', []);
        $workerIds = is_array($workerIds) ? array_values(array_unique(array_map('intval', $workerIds))) : [];
        if ($workerIds !== []) {
            $validIds = array_map(
                static fn (array $w): int => (int) $w['id'],
                (new UserModel())->listByRole('worker')
            );
            foreach ($workerIds as $workerId) {
                if (!in_array($workerId, $validIds, true)) {
                    Response::fail(Lang::get('admin.projects.worker_invalid'), 422);
                    return null;
                }
            }
        }

        return [
            'client_id'         => $clientId,
            'name'              => $name,
            'location'          => $location !== '' ? $location : null,
            'start_date'        => $startDate,
            'end_date'          => $endDate,
            'invoice_reference' => $invoiceReference !== '' ? $invoiceReference : null,
            'status'            => $status,
            'worker_ids'        => $workerIds,
        ];
    }

    private function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
