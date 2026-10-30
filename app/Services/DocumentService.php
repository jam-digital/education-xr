<?php

namespace App\Services;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/** PDF рахунку-фактури та акта виконаних робіт. Файли лежать на приватному диску local у documents/ */
class DocumentService
{
    public function invoice(Payment $payment): string
    {
        return $this->render('documents.invoice', $payment, 'invoice');
    }

    public function act(Payment $payment): string
    {
        return $this->render('documents.act', $payment, 'act');
    }

    private function render(string $view, Payment $payment, string $kind): string
    {
        $payment->loadMissing('user', 'lesson', 'promotion');
        $path = "documents/{$payment->created_at->format('Y')}/{$payment->invoice_number}-{$kind}.pdf";
        $pdf = Pdf::loadView($view, [
            'p' => $payment,
            'seller' => config('vrosvita.seller'),
            'amountWords' => Money::inWordsUah((float) $payment->amount),
        ])->setPaper('a4');
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
