<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams unified charge ledger rows as a CSV download.
 */
class ChargeCsvExporter
{
    public function __construct(
        protected ChargeBreakdown $breakdown,
        protected ChargeCardLookup $cardLookup,
    ) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function download(Collection $rows, ?string $filename = null): StreamedResponse
    {
        $filename ??= 'all-charges-'.now()->format('Y-m-d-His').'.csv';

        $this->cardLookup->limitRemoteLookups(0);

        return response()->streamDownload(function () use ($rows): void {
            $this->breakdown->preload($rows);

            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $this->headers(), escape: '');

            foreach ($rows as $row) {
                fputcsv($handle, $this->toCsvRow($row), escape: '');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return [
            __('Date'),
            __('Source'),
            __('Model'),
            __('Account / entity'),
            __('Reference'),
            __('Amount'),
            __('Currency'),
            __('Status'),
            __('Card'),
            __('Billing period'),
            __('Calculation'),
            __('Description'),
            __('Email'),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<string>
     */
    public function toCsvRow(array $record): array
    {
        $breakdown = $this->breakdown->forRecord($record);
        $cardLastFour = $this->cardLookup->lastFour($record);

        return array_map($this->escapeFormula(...), [
            (string) ($record['created_at'] ?? ''),
            (string) ($record['source_label'] ?? ''),
            (string) (UnifiedChargeLedger::modelOptions()[$record['model_type'] ?? ''] ?? ($record['model_type'] ?? '')),
            (string) ($record['model_label'] ?? ''),
            (string) ($record['reference'] ?? ''),
            number_format((float) ($record['amount'] ?? 0), 2, '.', ''),
            (string) ($record['currency'] ?? 'NOK'),
            (string) ($record['status_label'] ?? ''),
            $cardLastFour !== null ? '**** '.$cardLastFour : '',
            (string) ($breakdown['period'] ?? ''),
            $this->describeLines($breakdown['lines']),
            (string) ($record['description'] ?? ''),
            (string) ($record['email'] ?? ''),
        ]);
    }

    /**
     * @param  list<array{label: string, amount: float, note: ?string}>  $lines
     */
    protected function describeLines(array $lines): string
    {
        return collect($lines)
            ->map(fn (array $line): string => $line['label'].': '.number_format($line['amount'], 2, '.', ''))
            ->implode('; ');
    }

    /**
     * Prevent spreadsheet apps from evaluating cell values as formulas.
     */
    protected function escapeFormula(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)
            ? "'".$value
            : $value;
    }
}
