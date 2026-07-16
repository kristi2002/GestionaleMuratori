<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Config;
use App\Support\Lang;
use App\Support\Mailer;
use App\Support\Url;

/**
 * Transactional (event-driven) e-mail — distinct from SchedulerService's daily
 * digest. Each method fires on a single business event: a quote sent to a client,
 * an invoice issued, a test from the admin. Everything is best-effort and gated on
 * Mailer::isEnabled() (off until MAIL_* is configured), so a mail failure can never
 * break the request that triggered it. Call these AFTER the DB commit.
 *
 * The build*() methods are pure (no I/O, no config beyond Lang) and unit-tested; the
 * send wrappers add the enabled-gate + recipient validation and hand off to Mailer.
 */
final class MailService
{
    /** Branded HTML shell shared by every message (mirrors the digest styling). */
    public static function shell(string $heading, string $bodyHtml): string
    {
        $app = htmlspecialchars(Lang::get('app_name'), ENT_QUOTES);
        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:620px;color:#171A1F">'
            . '<h2 style="color:#0A0F1E">' . htmlspecialchars($heading, ENT_QUOTES) . '</h2>'
            . $bodyHtml
            . '<hr style="border:none;border-top:1px solid #E5E7EB;margin:20px 0">'
            . '<p style="color:#888;font-size:12px">' . $app . '</p></div>';
    }

    /** Absolute URL to a portal path (uses APP_URL, like the reset-password mail). */
    private static function link(string $path): string
    {
        return rtrim((string) Config::get('app.url', ''), '/') . Url::to($path);
    }

    private static function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, ',', '.');
    }

    // --- Quote sent to a client -------------------------------------------------

    /**
     * @param array<string,mixed> $quote a QuoteModel::find() row (number, title)
     * @return array{subject:string,html:string}
     */
    public static function buildQuoteSent(array $quote): array
    {
        $number  = (string) ($quote['number'] ?? '');
        $title   = (string) ($quote['title'] ?? '');
        $subject = sprintf(Lang::get('mail.quote_sent_subject'), $number);
        $cta     = self::link('/client/quotes');
        $body    = '<p>' . htmlspecialchars(sprintf(Lang::get('mail.quote_sent_body'), $number, $title), ENT_QUOTES) . '</p>'
            . '<p><a href="' . htmlspecialchars($cta, ENT_QUOTES) . '" '
            . 'style="display:inline-block;padding:10px 18px;background:#F97316;color:#fff;text-decoration:none;border-radius:6px">'
            . htmlspecialchars(Lang::get('mail.quote_sent_cta'), ENT_QUOTES) . '</a></p>';
        return ['subject' => $subject, 'html' => self::shell(Lang::get('mail.quote_sent_heading'), $body)];
    }

    /** @param array<string,mixed> $quote a QuoteModel::find() row (carries client_email) */
    public static function quoteSent(array $quote): bool
    {
        $to = trim((string) ($quote['client_email'] ?? ''));
        if ($to === '' || !Mailer::isEnabled()) {
            return false;
        }
        $msg = self::buildQuoteSent($quote);
        return Mailer::send($to, $msg['subject'], $msg['html']);
    }

    // --- Invoice issued to a client ---------------------------------------------

    /**
     * @param array<string,mixed> $invoice a findWithDetails() row (number, issue_date, amount)
     * @return array{subject:string,html:string}
     */
    public static function buildInvoiceIssued(array $invoice): array
    {
        $number  = (string) ($invoice['number'] ?? '');
        $date    = (string) ($invoice['issue_date'] ?? '');
        $subject = sprintf(Lang::get('mail.invoice_issued_subject'), $number);
        $amountH = $invoice['amount'] !== null
            ? '<p>' . htmlspecialchars(sprintf(Lang::get('mail.invoice_issued_amount'), self::money($invoice['amount'])), ENT_QUOTES) . '</p>'
            : '';
        $cta  = self::link('/client');
        $body = '<p>' . htmlspecialchars(sprintf(Lang::get('mail.invoice_issued_body'), $number, $date), ENT_QUOTES) . '</p>'
            . $amountH
            . '<p><a href="' . htmlspecialchars($cta, ENT_QUOTES) . '" '
            . 'style="display:inline-block;padding:10px 18px;background:#F97316;color:#fff;text-decoration:none;border-radius:6px">'
            . htmlspecialchars(Lang::get('mail.invoice_issued_cta'), ENT_QUOTES) . '</a></p>';
        return ['subject' => $subject, 'html' => self::shell(Lang::get('mail.invoice_issued_heading'), $body)];
    }

    /** @param array<string,mixed> $invoice a findWithDetails() row (carries client_email) */
    public static function invoiceIssued(array $invoice): bool
    {
        $to = trim((string) ($invoice['client_email'] ?? ''));
        if ($to === '' || !Mailer::isEnabled()) {
            return false;
        }
        $msg = self::buildInvoiceIssued($invoice);
        return Mailer::send($to, $msg['subject'], $msg['html']);
    }

    // --- Admin test e-mail ------------------------------------------------------

    /** Verifies the SMTP config end to end from the admin UI. */
    public static function test(string $to): bool
    {
        $to = trim($to);
        if ($to === '' || !Mailer::isEnabled()) {
            return false;
        }
        $body = '<p>' . htmlspecialchars(Lang::get('mail.test_body'), ENT_QUOTES) . '</p>';
        return Mailer::send($to, Lang::get('mail.test_subject'), self::shell(Lang::get('mail.test_heading'), $body));
    }
}
