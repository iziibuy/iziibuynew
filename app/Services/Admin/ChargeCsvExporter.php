<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Streams unified charge ledger rows as a CSV download.
 */
class ChargeCsvExporter
{
    protected const EXPORT_CHUNK_SIZE = 200;

    public function __construct(
        protected ChargeCardLookup $cardLookup,
    ) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function download(Collection $rows, ?string $filename = null): BinaryFileResponse|StreamedResponse
    {
        $filename ??= 'all-charges-'.now()->format('Y-m-d-His').'.csv';

        $this->cardLookup->limitRemoteLookups(0);

        $tempPath = tempnam(sys_get_temp_dir(), 'all-charges-');
        if ($tempPath === false) {
            throw new \RuntimeException('Could not create a temporary file for the CSV export.');
        }

        $handle = fopen($tempPath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Could not open the temporary CSV file for writing.');
        }

        try {
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $this->headers(), escape: '');

            foreach ($rows->chunk(self::EXPORT_CHUNK_SIZE) as $chunk) {
                $breakdown = app(ChargeBreakdown::class);
                $breakdown->preload($chunk);

                foreach ($chunk as $row) {
                    try {
                        fputcsv($handle, $this->toCsvRow($row, $breakdown), escape: '');
                    } catch (Throwable $exception) {
                        report($exception);
                        fputcsv($handle, $this->fallbackCsvRow($row), escape: '');
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ])->deleteFileAfterSend(true);
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
    public function toCsvRow(array $record, ?ChargeBreakdown $breakdown = null): array
    {
        $breakdown ??= app(ChargeBreakdown::class);
        $breakdownData = $breakdown->forRecord($record);
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
            (string) ($breakdownData['period'] ?? ''),
            $this->describeLines($breakdownData['lines']),
            (string) ($record['description'] ?? ''),
            (string) ($record['email'] ?? ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<string>
     */
    protected function fallbackCsvRow(array $record): array
    {
        return array_map($this->escapeFormula(...), [
            (string) ($record['created_at'] ?? ''),
            (string) ($record['source_label'] ?? ''),
            (string) ($record['model_type'] ?? ''),
            (string) ($record['model_label'] ?? ''),
            (string) ($record['reference'] ?? ''),
            number_format((float) ($record['amount'] ?? 0), 2, '.', ''),
            (string) ($record['currency'] ?? 'NOK'),
            (string) ($record['status_label'] ?? ''),
            '',
            '',
            '',
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
