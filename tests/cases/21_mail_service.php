<?php
/**
 * Transactional e-mail (App\Services\MailService): pure message-building and the
 * disabled-by-default send gate. No network — mirrors 00_paginator_mailer.
 */
declare(strict_types=1);

use App\Services\MailService;

T::section('MailService: quote-sent message building');
$quote = ['number' => '2026/007', 'title' => 'Ristrutturazione bagno', 'client_email' => 'cliente@example.com'];
$m = MailService::buildQuoteSent($quote);
T::ok(str_contains($m['subject'], '2026/007'), 'subject carries the quote number');
T::ok(str_contains($m['html'], '2026/007'), 'body carries the quote number');
T::ok(str_contains($m['html'], 'Ristrutturazione bagno'), 'body carries the quote title');
T::ok(str_contains($m['html'], '/client/quotes'), 'body links to the client quotes portal');

T::section('MailService: invoice-issued message building');
$inv = ['number' => 'FT-2026-014', 'issue_date' => '2026-07-16', 'amount' => '1234.5', 'client_email' => 'cliente@example.com'];
$mi = MailService::buildInvoiceIssued($inv);
T::ok(str_contains($mi['subject'], 'FT-2026-014'), 'subject carries the invoice number');
T::ok(str_contains($mi['html'], '2026-07-16'), 'body carries the issue date');
T::ok(str_contains($mi['html'], '1.234,50'), 'amount formatted it-IT (1.234,50)');
$mi2 = MailService::buildInvoiceIssued(['number' => 'FT-1', 'issue_date' => '2026-01-01', 'amount' => null, 'client_email' => 'x@y.com']);
T::ok(str_contains($mi2['html'], 'FT-1'), 'invoice with null amount still builds');
T::ok(!str_contains($mi2['html'], 'Importo'), 'no amount line when amount is null');

T::section('MailService: send gate (disabled by default in tests)');
T::ok(MailService::quoteSent($quote) === false, 'quoteSent no-ops when mail disabled');
T::ok(MailService::invoiceIssued($inv) === false, 'invoiceIssued no-ops when mail disabled');
T::ok(MailService::test('admin@example.com') === false, 'test() no-ops when mail disabled');
T::ok(MailService::quoteSent(['number' => 'X', 'title' => 'Y']) === false, 'quoteSent no-ops without a client_email');
