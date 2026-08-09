<?php

namespace App\Http\Controllers;

use App\Models\AnalysisReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\View\View;

final class AnalysisReportController extends Controller
{
    public function show(AnalysisReport $analysisReport): View
    {
        abort_unless($analysisReport->user_id === null || (int) $analysisReport->user_id === (int) auth()->id(), 403);
        abort_unless($analysisReport->status === 'completed', 404);
        $data = $analysisReport->report_data ?? [];
        if (empty($data['indicators']) && $analysisReport->instrument_id) {
            $data['indicators'] = DB::table('technical_indicators as ti')
                ->leftJoin('feature_store as fs', function ($join): void {
                    $join->on('fs.instrument_id', '=', 'ti.instrument_id')->on('fs.interval', '=', 'ti.interval')->on('fs.bar_time', '=', 'ti.bar_time');
                })
                ->where('ti.instrument_id', $analysisReport->instrument_id)->where('ti.interval', '1d')
                ->orderByDesc('ti.bar_time')->limit(80)->get([
                    'ti.bar_time','ti.rsi_14','ti.stochastic_k','ti.adx_14','ti.macd_histogram','ti.momentum_10','ti.volatility_20','fs.target_return_20d'
                ])->reverse()->map(fn ($row): array => (array) $row)->values()->all();
        }
        return view('reports.show', ['report' => $analysisReport, 'data' => $data]);
    }

    public function pdf(AnalysisReport $analysisReport): Response
    {
        abort_unless($analysisReport->user_id === null || (int) $analysisReport->user_id === (int) auth()->id(), 403);
        abort_unless($analysisReport->pdf_path && Storage::disk('local')->exists($analysisReport->pdf_path), 404);
        return response(Storage::disk('local')->get($analysisReport->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="signal-report-'.$analysisReport->symbol.'.pdf"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
