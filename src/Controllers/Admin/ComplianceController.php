<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ComplianceDocumentModel;
use App\Models\ProjectModel;
use App\Models\SubcontractorModel;
use App\Models\UserModel;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Scadenzario Sicurezza: CRUD for compliance documents (DURC, POS, PSC, Patente a
 * Crediti, visite mediche, formazione…) with expiry tracking. Admin-only.
 */
final class ComplianceController
{
    private const SUBJECT_TYPES = ['worker', 'company', 'subcontractor', 'project'];
    private const DOC_TYPES = ['DURC', 'POS', 'PSC', 'patente_crediti', 'visita_medica', 'formazione', 'assicurazione', 'other'];

    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $filters = [
            'subject_type' => in_array((string) $request->input('subject_type', ''), self::SUBJECT_TYPES, true)
                ? (string) $request->input('subject_type', '') : '',
            'doc_type'     => in_array((string) $request->input('doc_type', ''), self::DOC_TYPES, true)
                ? (string) $request->input('doc_type', '') : '',
            'expiring'     => (string) $request->input('expiring', '') === '1',
        ];

        Response::html(View::render('admin/compliance/index', [
            'title'          => Lang::get('admin.compliance.title'),
            'documents'      => (new ComplianceDocumentModel())->all($filters),
            'workers'        => (new UserModel())->listByRole('worker'),
            'subcontractors' => (new SubcontractorModel())->listActive(),
            'projects'       => (new ProjectModel())->all(),
            'subjectTypes'   => self::SUBJECT_TYPES,
            'docTypes'       => self::DOC_TYPES,
            'filters'        => $filters,
            'today'          => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ], 'layout'));
    }

    /** GET /admin/compliance/create — blank compliance document form page. */
    public function create(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/compliance/form', [
            'title'          => Lang::get('admin.compliance.new'),
            'document'       => null,
            'workers'        => (new UserModel())->listByRole('worker'),
            'subcontractors' => (new SubcontractorModel())->listActive(),
            'projects'       => (new ProjectModel())->all(),
            'subjectTypes'   => self::SUBJECT_TYPES,
            'docTypes'       => self::DOC_TYPES,
        ], 'layout'));
    }

    /** GET /admin/compliance/{id}/edit — populated compliance document form page. */
    public function edit(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $document = (new ComplianceDocumentModel())->find((int) $id);
        if ($document === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/compliance/form', [
            'title'          => Lang::get('admin.compliance.edit'),
            'document'       => $document,
            'workers'        => (new UserModel())->listByRole('worker'),
            'subcontractors' => (new SubcontractorModel())->listActive(),
            'projects'       => (new ProjectModel())->all(),
            'subjectTypes'   => self::SUBJECT_TYPES,
            'docTypes'       => self::DOC_TYPES,
        ], 'layout'));
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }
        $id = (new ComplianceDocumentModel())->create($data);
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new ComplianceDocumentModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.compliance.not_found'), 404);
            return;
        }
        $data = $this->validated($request);
        if ($data === null) {
            return;
        }
        $model->update((int) $id, $data);
        Response::ok();
    }

    public function destroy(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new ComplianceDocumentModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.compliance.not_found'), 404);
            return;
        }
        $model->delete((int) $id);
        Response::ok();
    }

    /** @return array<string,mixed>|null Validated fields, or null if a fail response was already sent. */
    private function validated(Request $request): ?array
    {
        $subjectType = (string) $request->input('subject_type', '');
        if (!in_array($subjectType, self::SUBJECT_TYPES, true)) {
            Response::fail(Lang::get('admin.compliance.subject_type_invalid'), 422);
            return null;
        }

        $subjectId = $this->resolveSubjectId($subjectType, (int) $request->input('subject_id', 0));
        if ($subjectId === false) {
            Response::fail(Lang::get('admin.compliance.subject_invalid'), 422);
            return null;
        }

        $docType = (string) $request->input('doc_type', '');
        if (!in_array($docType, self::DOC_TYPES, true)) {
            Response::fail(Lang::get('admin.compliance.doc_type_invalid'), 422);
            return null;
        }

        $issue  = $this->nullableDate($request->input('issue_date', ''));
        $expiry = $this->nullableDate($request->input('expiry_date', ''));
        if ($issue === false || $expiry === false) {
            Response::fail(Lang::get('admin.compliance.date_invalid'), 422);
            return null;
        }
        if ($issue !== null && $expiry !== null && $expiry < $issue) {
            Response::fail(Lang::get('admin.compliance.expiry_before_issue'), 422);
            return null;
        }

        $creditsRaw = trim((string) $request->input('credits', ''));
        $credits    = null;
        if ($creditsRaw !== '') {
            if (!ctype_digit($creditsRaw)) {
                Response::fail(Lang::get('admin.compliance.credits_invalid'), 422);
                return null;
            }
            $credits = (int) $creditsRaw;
        }

        return [
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'doc_type'     => $docType,
            'reference'    => $this->nullable($request->input('reference', '')),
            'issue_date'   => $issue,
            'expiry_date'  => $expiry,
            'credits'      => $credits,
            'notes'        => $this->nullable($request->input('notes', '')),
            'created_by'   => Auth::id(),
        ];
    }

    /**
     * Validate the subject reference for the given type.
     * @return int|null|false null for 'company', an id for the others, false if invalid.
     */
    private function resolveSubjectId(string $type, int $subjectId): int|null|false
    {
        if ($type === 'company') {
            return null;
        }
        if ($subjectId <= 0) {
            return false;
        }
        $exists = match ($type) {
            'worker'        => (new UserModel())->findById($subjectId) !== null,
            'subcontractor' => (new SubcontractorModel())->find($subjectId) !== null,
            'project'       => (new ProjectModel())->find($subjectId) !== null,
            default         => false,
        };
        return $exists ? $subjectId : false;
    }

    private function nullable(mixed $value): ?string
    {
        $v = trim((string) $value);
        return $v !== '' ? $v : null;
    }

    /** @return string|null|false null when blank, false when malformed, else the date. */
    private function nullableDate(mixed $value): string|null|false
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $v);
        return ($d !== false && $d->format('Y-m-d') === $v) ? $v : false;
    }
}
