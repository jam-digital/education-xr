<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\LessonLicense;
use App\Models\Payment;
use App\Models\TicketLog;
use App\Models\User;
use Carbon\CarbonInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Фінансовий звіт за період: зведення по закладах (продажі, нараховані/використані квитки, залишок)
 * і деталізація всіх платежів. XLSX — два аркуші, CSV — зведення, потім деталізація.
 */
class FinanceReport
{
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $to,
        public ?string $userId = null,
    ) {}

    public function summaryHeader(): array
    {
        return ['Заклад', 'ЄДРПОУ', 'Email', 'Оплат', 'Сума оплат, ₴', 'Нараховано запусків', 'Використано запусків', 'Поточний залишок запусків', 'Активних підписок'];
    }

    public function summaryRows(): array
    {
        $paid = Payment::query()->where('status', PaymentStatus::Completed->value)
            ->whereBetween('paid_at', [$this->from, $this->to])
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->selectRaw('user_id, count(*) as cnt, sum(amount) as total, sum(tickets_added) as tickets')
            ->groupBy('user_id')->get()->keyBy('user_id');

        $used = TicketLog::query()->where('tickets_delta', '<', 0)->where('mode', '!=', 'admin_adjustment')
            ->whereBetween('started_at', [$this->from, $this->to])
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->selectRaw('user_id, -sum(tickets_delta) as used')->groupBy('user_id')->pluck('used', 'user_id');

        $remaining = LessonLicense::active()->where('license_type', 'ticket_pool')
            ->selectRaw('user_id, sum(tickets_remaining) as rem')->groupBy('user_id')->pluck('rem', 'user_id');
        $subs = LessonLicense::active()->where('license_type', '!=', 'ticket_pool')->where('valid_until', '>', now())
            ->selectRaw('user_id, count(*) as c')->groupBy('user_id')->pluck('c', 'user_id');

        return User::clients()->when($this->userId, fn ($q) => $q->whereKey($this->userId))
            ->orderBy('school_or_org_name')->get()
            ->filter(fn (User $u) => $paid->has($u->id) || isset($used[$u->id]) || $this->userId)
            ->map(fn (User $u) => [
                $u->display_name,
                (string) $u->edrpou_tax_id,
                $u->email,
                (int) ($paid[$u->id]->cnt ?? 0),
                round((float) ($paid[$u->id]->total ?? 0), 2),
                (int) ($paid[$u->id]->tickets ?? 0),
                (int) ($used[$u->id] ?? 0),
                (int) ($remaining[$u->id] ?? 0),
                (int) ($subs[$u->id] ?? 0),
            ])->values()->all();
    }

    public function detailHeader(): array
    {
        return ['Дата', 'Рахунок', 'Заклад', 'ЄДРПОУ', 'Урок / акція', 'Спосіб оплати', 'Шлюз', 'Сума', 'Валюта', 'Квитків нараховано', 'Днів доступу', 'Статус'];
    }

    public function detailRows(): array
    {
        return Payment::query()->with('user', 'lesson', 'promotion')
            ->whereBetween('created_at', [$this->from, $this->to])
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->orderBy('created_at')->get()
            ->map(fn (Payment $p) => [
                ($p->paid_at ?? $p->created_at)->format('d.m.Y H:i'),
                $p->invoice_number,
                $p->user->display_name,
                (string) $p->user->edrpou_tax_id,
                $p->promotion?->title ?? $p->lesson?->title ?? '',
                $p->payment_method->getLabel(),
                (string) $p->gateway_name,
                round((float) $p->amount, 2),
                $p->currency,
                (int) $p->tickets_added,
                (int) $p->subscription_days_added,
                $p->status->getLabel(),
            ])->all();
    }

    public function filename(string $ext): string
    {
        return 'vr-osvita-finance-'.$this->from->format('Y-m-d').'_'.$this->to->format('Y-m-d').'.'.$ext;
    }

    /** Записує файл і повертає шлях до нього */
    public function write(string $format): string
    {
        $path = storage_path('app/private/reports/'.$this->filename($format));
        @mkdir(dirname($path), 0775, true);
        $period = 'Період: '.$this->from->format('d.m.Y').' — '.$this->to->format('d.m.Y');

        if ($format === 'xlsx') {
            $w = new XlsxWriter;
            $w->openToFile($path);
            $w->getCurrentSheet()->setName('Зведення');
            $w->addRow(Row::fromValues([$period]));
            $w->addRow(Row::fromValues($this->summaryHeader()));
            foreach ($this->summaryRows() as $r) {
                $w->addRow(Row::fromValues($r));
            }
            $w->addNewSheetAndMakeItCurrent()->setName('Платежі');
            $w->addRow(Row::fromValues($this->detailHeader()));
            foreach ($this->detailRows() as $r) {
                $w->addRow(Row::fromValues($r));
            }
            $w->close();

            return $path;
        }

        $w = new CsvWriter;
        $w->openToFile($path);
        $w->addRow(Row::fromValues([$period]));
        $w->addRow(Row::fromValues(['ЗВЕДЕННЯ']));
        $w->addRow(Row::fromValues($this->summaryHeader()));
        foreach ($this->summaryRows() as $r) {
            $w->addRow(Row::fromValues($r));
        }
        $w->addRow(Row::fromValues([]));
        $w->addRow(Row::fromValues(['ПЛАТЕЖІ']));
        $w->addRow(Row::fromValues($this->detailHeader()));
        foreach ($this->detailRows() as $r) {
            $w->addRow(Row::fromValues($r));
        }
        $w->close();

        return $path;
    }
}
