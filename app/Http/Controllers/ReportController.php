<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Services\ExpectedStockReportService;
use App\Services\ReportExcelExporter;
use App\Support\CurrentOutlet;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function __construct(
        private readonly ExpectedStockReportService $reports,
        private readonly ReportExcelExporter $excel,
        private readonly CurrentOutlet $currentOutlet,
    ) {}

    public function dashboard()
    {
        $outlet = $this->outlet();
        $asAt = now()->startOfDay();
        $rows = $this->reports->current($outlet, $asAt);
        $periodNotice = $this->reports->periodNotice($outlet->id, $asAt, $asAt);
        $financials = $this->reports->financials($outlet, $asAt, $rows);
        $totalCostWithTax = $this->reports->totalIndentCostWithTax($outlet->id, $asAt);

        return view('dashboard', compact('outlet', 'asAt', 'rows', 'financials', 'totalCostWithTax'));
    }

    public function current(Request $request)
    {
        $request->validate(['as_at' => ['nullable', 'date_format:Y-m-d']]);
        $outlet = $this->outlet();
        $asAt = Carbon::parse($request->string('as_at')->toString() ?: now()->toDateString())->startOfDay();
        $rows = $this->reports->current($outlet, $asAt);
        $periodNotice = $this->reports->periodNotice($outlet->id, $asAt, $asAt);

        return view('reports.current', compact('outlet', 'asAt', 'rows', 'periodNotice'));
    }

    public function currentPdf(Request $request)
    {
        $request->validate(['as_at' => ['nullable', 'date_format:Y-m-d']]);
        $outlet = $this->outlet();
        $asAt = Carbon::parse($request->string('as_at')->toString() ?: now()->toDateString())->startOfDay();
        $rows = $this->reports->current($outlet, $asAt);
        $periodNotice = $this->reports->periodNotice($outlet->id, $asAt, $asAt);

        return Pdf::loadView('reports.current-pdf', compact('outlet', 'asAt', 'rows', 'periodNotice'))
            ->setPaper('a4', 'landscape')
            ->download('expected-stock-'.$asAt->toDateString().'.pdf');
    }

    public function currentExcel(Request $request)
    {
        $request->validate(['as_at' => ['nullable', 'date_format:Y-m-d']]);
        $outlet = $this->outlet();
        $asAt = Carbon::parse($request->string('as_at')->toString() ?: now()->toDateString())->startOfDay();
        $rows = $this->reports->current($outlet, $asAt);
        $periodNotice = $this->reports->periodNotice($outlet->id, $asAt, $asAt);

        return $this->excel->current($outlet, $asAt, $rows, $periodNotice);
    }

    public function interval(Request $request)
    {
        $outlet = $this->outlet();
        [$from, $to] = $this->range($request);
        $report = $this->reports->interval($outlet, $from, $to);

        return view('reports.interval', compact('outlet', 'from', 'to', 'report'));
    }

    public function intervalPdf(Request $request)
    {
        $outlet = $this->outlet();
        [$from, $to] = $this->range($request);
        $report = $this->reports->interval($outlet, $from, $to);

        return Pdf::loadView('reports.interval-pdf', compact('outlet', 'from', 'to', 'report'))
            ->setPaper('a3', 'landscape')
            ->download('stock-movement-'.$from->toDateString().'-to-'.$to->toDateString().'.pdf');
    }

    public function intervalExcel(Request $request)
    {
        $outlet = $this->outlet();
        [$from, $to] = $this->range($request);
        $report = $this->reports->interval($outlet, $from, $to);

        return $this->excel->interval($outlet, $from, $to, $report);
    }

    private function outlet(): Outlet
    {
        return $this->currentOutlet->get()->loadMissing('organisation');
    }

    /** @return array{Carbon, Carbon} */
    private function range(Request $request): array
    {
        $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $to = Carbon::parse($request->string('to')->toString() ?: now()->toDateString())->startOfDay();
        $from = Carbon::parse($request->string('from')->toString() ?: $to->copy()->subDays(2)->toDateString())->startOfDay();

        if ($from->gt($to)) {
            throw ValidationException::withMessages(['from' => 'The start date must be before the end date.']);
        }

        return [$from, $to];
    }
}
