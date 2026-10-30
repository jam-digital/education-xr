@php
    use App\Enums\LicenseStatus;
@endphp
@foreach (\App\Enums\Subject::cases() as $s)
    @php $items = $lessons->where('subject_category', $s); @endphp
    @continue($items->isEmpty())
    <div class="xr-matrix__subject" style="--c: {{ $s->hex() }}">{{ $s->getLabel() }}</div>
    <div class="xr-matrix">
        @foreach ($items as $lesson)
            @php
                $ls = $licenses->get($lesson->id, collect());
                $usable = $ls->filter->isUsable();
                $state = $usable->isNotEmpty() ? 'on' : ($ls->firstWhere('status', LicenseStatus::Revoked) ? 'revoked' : ($ls->isNotEmpty() ? 'off' : 'none'));
            @endphp
            <div class="xr-matrix__cell xr-matrix__cell--{{ $state }}">
                <div class="xr-matrix__title">{{ $lesson->title }}</div>
                @forelse ($usable as $l)
                    <div class="xr-matrix__line">{{ $l->license_type->getLabel() }}: {{ $l->balanceLabel() }}</div>
                    @if ($l->license_type === \App\Enums\LicenseType::TicketPool && $l->tickets_total_purchased > 0)
                        <div class="xr-bar"><span style="width: {{ min(100, round($l->tickets_remaining / max(1, $l->tickets_total_purchased) * 100)) }}%"></span></div>
                    @endif
                @empty
                    <div class="xr-matrix__line">
                        {{ match ($state) { 'revoked' => 'Доступ відкликано', 'off' => 'Закінчився', default => 'Немає доступу' } }}
                    </div>
                @endforelse
            </div>
        @endforeach
    </div>
@endforeach
