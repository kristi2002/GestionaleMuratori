<?php
declare(strict_types=1);

namespace App\Controllers\Client;

use App\Http\Middleware\AuthGuard;
use App\Models\QuoteModel;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Client self-service for quotes (Preventivi): read their own non-draft quotes and
 * accept/reject the ones the admin has sent. Ownership is enforced on every query
 * by client_id = session client id; drafts are never exposed.
 */
final class QuoteController
{
    /** GET /client/quotes — list the client's quotes. */
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['client']);

        Response::html(View::render('client/quotes/index', [
            'title'  => Lang::get('client.quotes.title'),
            'quotes' => (new QuoteModel())->forClient((int) Auth::clientId()),
        ], 'layout'));
    }

    /** GET /client/quotes/{id} — quote detail with line items + accept/reject actions. */
    public function show(Request $request, string $id): void
    {
        AuthGuard::require($request, ['client']);

        $model = new QuoteModel();
        $quote = $model->findForClient((int) $id, (int) Auth::clientId());
        if ($quote === null) {
            Response::html(View::render('errors/404', ['title' => Lang::get('errors.not_found_title')], 'layout'), 404);
            return;
        }

        Response::html(View::render('client/quotes/show', [
            'title' => $quote['title'],
            'quote' => $quote,
            'lines' => $model->lines((int) $id),
        ], 'layout'));
    }

    /** POST /client/quotes/{id}/accept — accept a sent quote. */
    public function accept(Request $request, string $id): void
    {
        $this->decide($request, (int) $id, 'accepted');
    }

    /** POST /client/quotes/{id}/reject — reject a sent quote. */
    public function reject(Request $request, string $id): void
    {
        $this->decide($request, (int) $id, 'rejected');
    }

    private function decide(Request $request, int $id, string $status): void
    {
        AuthGuard::require($request, ['client']);

        $ok = (new QuoteModel())->setClientDecision($id, (int) Auth::clientId(), $status);
        if (!$ok) {
            // Not owned, not 'sent' (already decided/expired), or missing.
            Response::fail(Lang::get('client.quotes.decision_unavailable'), 422);
            return;
        }
        Response::ok(['status' => $status]);
    }
}
