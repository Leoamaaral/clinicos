<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\ClinicSetting;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
    ) {}

    public function index(ReportFilterRequest $request): Response
    {
        $data = $this->reportService->build(
            $request->startDate(),
            $request->endDate(),
        );

        return Inertia::render('reports/index', [
            ...$data,
            'clinic_name' => ClinicSetting::current()->clinic_name,
        ]);
    }

    public function pdf(ReportFilterRequest $request): HttpResponse
    {
        $data = $this->reportService->build(
            $request->startDate(),
            $request->endDate(),
        );

        $clinic = ClinicSetting::current();

        $pdf = Pdf::loadView('reports.pdf', [
            ...$data,
            'clinic_name' => $clinic->clinic_name,
            'generated_at' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'relatorio-%s-a-%s.pdf',
            $data['filters']['start_date'],
            $data['filters']['end_date'],
        );

        return $pdf->download($filename);
    }
}
