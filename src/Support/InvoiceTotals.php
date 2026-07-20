<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Pure calculator for a fiscal invoice's money math, shared by the invoice model
 * (to cache totals) and the FatturaPA builder (to emit DatiRiepilogo). Groups lines
 * into VAT-summary buckets by (aliquota, natura) exactly as the XML requires, and
 * derives imponibile / imposta / bollo / ritenuta / totale documento / netto a pagare.
 *
 * All rounding is half-up to 2 decimals, applied per bucket before summing, so the
 * totals reconcile with the per-rate DatiRiepilogo lines (SdI rejects mismatches).
 */
final class InvoiceTotals
{
    /**
     * @param array<int,array{qty:mixed,unit_price:mixed,vat_rate:mixed,natura?:?string}> $lines
     * @param array{ritenuta_rate?:mixed,bollo?:mixed} $opts
     * @return array{
     *   riepilogo:array<int,array{vat_rate:float,natura:?string,imponibile:float,imposta:float}>,
     *   imponibile:float, imposta:float, bollo:float, ritenuta:float,
     *   total_document:float, net_to_pay:float
     * }
     */
    public static function compute(array $lines, array $opts = []): array
    {
        $buckets = [];
        foreach ($lines as $l) {
            $rate      = (float) $l['vat_rate'];
            $natura    = ($l['natura'] ?? null) !== '' ? ($l['natura'] ?? null) : null;
            $lineTotal = self::lineTotal($l['qty'], $l['unit_price']);
            $key       = number_format($rate, 2, '.', '') . '|' . ((string) $natura);

            if (!isset($buckets[$key])) {
                $buckets[$key] = ['vat_rate' => $rate, 'natura' => $natura, 'imponibile' => 0.0, 'imposta' => 0.0];
            }
            $buckets[$key]['imponibile'] += $lineTotal;
        }

        $imponibile = 0.0;
        $imposta    = 0.0;
        foreach ($buckets as &$b) {
            $b['imponibile'] = round($b['imponibile'], 2);
            $b['imposta']    = round($b['imponibile'] * $b['vat_rate'] / 100, 2);
            $imponibile     += $b['imponibile'];
            $imposta        += $b['imposta'];
        }
        unset($b);

        $imponibile = round($imponibile, 2);
        $imposta    = round($imposta, 2);
        $bollo      = round((float) ($opts['bollo'] ?? 0), 2);

        $ritRate  = (float) ($opts['ritenuta_rate'] ?? 0);
        $ritenuta = $ritRate > 0 ? round($imponibile * $ritRate / 100, 2) : 0.0;

        $totalDocument = round($imponibile + $imposta + $bollo, 2);
        $netToPay      = round($totalDocument - $ritenuta, 2);

        return [
            'riepilogo'      => array_values($buckets),
            'imponibile'     => $imponibile,
            'imposta'        => $imposta,
            'bollo'          => $bollo,
            'ritenuta'       => $ritenuta,
            'total_document' => $totalDocument,
            'net_to_pay'     => $netToPay,
        ];
    }

    /** Line total (PrezzoTotale) = qty × unit price, rounded to 2 decimals. */
    public static function lineTotal(mixed $qty, mixed $unitPrice): float
    {
        return round((float) $qty * (float) $unitPrice, 2);
    }
}
