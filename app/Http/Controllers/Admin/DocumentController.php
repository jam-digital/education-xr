<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\FinanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function payment(Request $request, Payment $payment, string $kind): StreamedResponse
    {
        abort_unless($request->user()->hasRole(Role::SuperAdmin, Role::FinanceViewer, Role::Manager), 403);
        $path = $kind === 'act' ? $payment->act_pdf_url : $payment->invoice_pdf_url;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path));
    }

    public function report(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->hasRole(Role::SuperAdmin, Role::FinanceViewer), 403);
        $data = $request->validate(['from' => 'required|date', 'to' => 'required|date', 'format' => 'required|in:xlsx,csv', 'user' => 'nullable|uuid']);
        $report = new FinanceReport(Carbon::parse($data['from'])->startOfDay(), Carbon::parse($data['to'])->endOfDay(), $data['user'] ?? null);

        return response()->download($report->write($data['format']), $report->filename($data['format']))->deleteFileAfterSend();
    }
}
