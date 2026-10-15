<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AllChargesQuery;
use App\Services\Admin\ChargeCsvExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AllChargesExportController extends Controller
{
    public function __invoke(Request $request, AllChargesQuery $query, ChargeCsvExporter $exporter): BinaryFileResponse|StreamedResponse
    {
        abort_unless((bool) $request->user()?->hasRole('admin'), 403);

        @ini_set('memory_limit', '512M');

        $state = $query->stateFromRequest($request);

        $rows = $query->rows(
            filters: $state['filters'],
            sortColumn: $state['sortColumn'] ?? 'created_at',
            sortDirection: $state['sortDirection'] ?? 'desc',
            search: $state['search'],
            lightweight: true,
        );

        return $exporter->download($rows);
    }
}
