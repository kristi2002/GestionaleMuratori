<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ExpenseModel;
use App\Models\ProjectModel;
use App\Models\UserModel;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validate;
use App\Support\View;

/**
 * Sidebar "Spese": running costs outside warehouse materials — worker meals
 * ("pasti"), fuel, vehicle upkeep, work clothing and other site expenses.
 */
final class ExpenseController
{
    public const CATEGORIES = ['meals', 'fuel', 'vehicle', 'clothing', 'other'];

    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $category = (string) $request->input('category', '');
        if (!in_array($category, self::CATEGORIES, true)) {
            $category = '';
        }

        $filters = [
            'search'     => trim((string) $request->input('q', '')),
            'category'   => $category,
            'worker_id'  => (int) $request->input('worker_id', 0),
            'project_id' => (int) $request->input('project_id', 0),
            'date_from'  => $this->dateInput($request, 'date_from'),
            'date_to'    => $this->dateInput($request, 'date_to'),
        ];

        $model = new ExpenseModel();

        Response::html(View::render('admin/expenses/index', [
            'title'      => Lang::get('admin.expenses.title'),
            'expenses'   => $model->all($filters),
            'totals'     => $model->totals($filters),
            'workers'    => (new UserModel())->listByRole('worker', false),
            'projects'   => (new ProjectModel())->all(),
            'filters'    => $filters,
            'categories' => self::CATEGORIES,
        ], 'layout'));
    }

    public function create(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/expenses/form', [
            'title'      => Lang::get('admin.expenses.new'),
            'expense'    => null,
            'workers'    => (new UserModel())->listByRole('worker'),
            'projects'   => (new ProjectModel())->all(),
            'categories' => self::CATEGORIES,
        ], 'layout'));
    }

    public function edit(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $expense = (new ExpenseModel())->find((int) $id);
        if ($expense === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/expenses/form', [
            'title'      => Lang::get('admin.expenses.edit'),
            'expense'    => $expense,
            'workers'    => (new UserModel())->listByRole('worker', false),
            'projects'   => (new ProjectModel())->all(),
            'categories' => self::CATEGORIES,
        ], 'layout'));
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }
        $data['created_by'] = Auth::id();

        $id = (new ExpenseModel())->create($data);
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new ExpenseModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.expenses.not_found'), 404);
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

        $model = new ExpenseModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.expenses.not_found'), 404);
            return;
        }

        $model->delete((int) $id);
        Response::ok();
    }

    /** @return array<string,mixed>|null Validated fields, or null if a fail response was already sent. */
    private function validated(Request $request): ?array
    {
        $date = trim((string) $request->input('expense_date', ''));
        if (!Validate::isDate($date)) {
            Response::fail(Lang::get('admin.expenses.date_invalid'), 422);
            return null;
        }

        $category = (string) $request->input('category', '');
        if (!in_array($category, self::CATEGORIES, true)) {
            Response::fail(Lang::get('admin.expenses.category_invalid'), 422);
            return null;
        }

        $description = trim((string) $request->input('description', ''));
        if ($description === '') {
            Response::fail(Lang::get('admin.expenses.description_required'), 422);
            return null;
        }

        $amountRaw = str_replace(',', '.', trim((string) $request->input('amount', '')));
        if (!Validate::isMoney($amountRaw) || (float) $amountRaw <= 0) {
            Response::fail(Lang::get('admin.expenses.amount_invalid'), 422);
            return null;
        }

        $workerId = (int) $request->input('worker_id', 0);
        if ($workerId > 0) {
            $worker = (new UserModel())->findById($workerId);
            if ($worker === null || $worker['role'] !== 'worker') {
                Response::fail(Lang::get('admin.expenses.worker_invalid'), 422);
                return null;
            }
        }

        $projectId = (int) $request->input('project_id', 0);
        if ($projectId > 0 && (new ProjectModel())->find($projectId) === null) {
            Response::fail(Lang::get('admin.expenses.project_invalid'), 422);
            return null;
        }

        $note = trim((string) $request->input('note', ''));

        return [
            'expense_date' => $date,
            'category'     => $category,
            'description'  => mb_substr($description, 0, 255),
            'amount'       => number_format((float) $amountRaw, 2, '.', ''),
            'worker_id'    => $workerId > 0 ? $workerId : null,
            'project_id'   => $projectId > 0 ? $projectId : null,
            'note'         => $note !== '' ? mb_substr($note, 0, 255) : null,
        ];
    }

    /** A malformed filter date is dropped rather than passed to SQL. */
    private function dateInput(Request $request, string $key): string
    {
        $raw = trim((string) $request->input($key, ''));
        return Validate::isDate($raw) ? $raw : '';
    }
}
