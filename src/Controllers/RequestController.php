<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\LeadModel;
use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Services\PushService;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Public (unauthenticated) "request a job" form — lead capture. Anti-spam is a
 * hidden honeypot field; the global CSRF gate already covers the POST. A new lead
 * notifies the admins (bell + push) and lands in the admin inbox (/admin/leads).
 */
final class RequestController
{
    /** GET /request — public request form. */
    public function show(Request $request): void
    {
        $this->render(true, false, null, []);
    }

    /** POST /request — validate + store a lead, then show a thank-you. */
    public function submit(Request $request): void
    {
        // Honeypot: bots fill the hidden 'website' field. Pretend success, store nothing.
        if (trim((string) $request->input('website', '')) !== '') {
            $this->render(false, true, null, []);
            return;
        }

        $name    = trim((string) $request->input('name', ''));
        $email   = trim((string) $request->input('email', ''));
        $phone   = trim((string) $request->input('phone', ''));
        $message = trim((string) $request->input('message', ''));
        $old     = ['name' => $name, 'email' => $email, 'phone' => $phone, 'message' => $message];

        if ($name === '' || mb_strlen($name) > 190) {
            $this->render(false, false, Lang::get('public.request.name_required'), $old);
            return;
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->render(false, false, Lang::get('public.request.email_invalid'), $old);
            return;
        }
        if ($email === '' && $phone === '') {
            $this->render(false, false, Lang::get('public.request.contact_required'), $old);
            return;
        }
        if (mb_strlen($message) > 2000) {
            $this->render(false, false, Lang::get('public.request.message_too_long'), $old);
            return;
        }

        $leadId = (new LeadModel())->create([
            'name'    => $name,
            'email'   => $email !== '' ? $email : null,
            'phone'   => $phone !== '' ? $phone : null,
            'message' => $message !== '' ? $message : null,
            'source'  => 'web',
            'ip'      => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
        ]);

        $this->notifyAdmins($leadId, $name, $email !== '' ? $email : $phone);
        $this->render(false, true, null, []);
    }

    private function notifyAdmins(int $leadId, string $name, string $contact): void
    {
        (new NotificationModel())->createIfAbsent([
            'type'      => 'system',
            'severity'  => 'info',
            'title'     => sprintf(Lang::get('admin.leads.notify_title'), $name),
            'body'      => $contact !== '' ? sprintf(Lang::get('admin.leads.notify_body'), $contact) : null,
            'link'      => '/admin/leads/' . $leadId,
            'dedup_key' => 'lead:' . $leadId,
        ]);

        $adminIds = [];
        foreach ((new UserModel())->listByRole('admin') as $admin) {
            $adminIds[] = (int) $admin['id'];
        }
        PushService::sendToUsers($adminIds);
    }

    /** @param array<string,string> $old */
    private function render(bool $fresh, bool $sent, ?string $error, array $old): void
    {
        Response::html(View::render('public/request', [
            'title' => Lang::get('public.request.title'),
            'sent'  => $sent,
            'error' => $error,
            'old'   => $old,
        ], 'layout'), $error !== null ? 422 : 200);
    }
}
