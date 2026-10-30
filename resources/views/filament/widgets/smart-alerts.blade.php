<x-filament-widgets::widget>
    <x-filament::section heading="Розумні сповіщення" icon="heroicon-o-bell-alert">
        <div class="xr-alerts">
            <div class="xr-alerts__col">
                <h3 class="xr-alerts__h"><span class="xr-dot xr-dot--danger"></span>Закінчуються запуски <span class="xr-count">{{ $low->count() }}</span></h3>
                @forelse ($low as $l)
                    <a href="{{ $url($l->user) }}" class="xr-alerts__item">
                        <span><b>{{ $l->user->display_name }}</b><br><small>{{ $l->lesson->title }}</small></span>
                        <span class="xr-pill xr-pill--danger">{{ $l->tickets_remaining }} шт.</span>
                    </a>
                @empty
                    <p class="xr-alerts__empty">Усі заклади мають достатньо запусків.</p>
                @endforelse
            </div>
            <div class="xr-alerts__col">
                <h3 class="xr-alerts__h"><span class="xr-dot xr-dot--warning"></span>Спливає підписка або тріал (3 дні) <span class="xr-count">{{ $expiring->count() }}</span></h3>
                @forelse ($expiring as $l)
                    <a href="{{ $url($l->user) }}" class="xr-alerts__item">
                        <span><b>{{ $l->user->display_name }}</b><br><small>{{ $l->lesson->title }} · {{ $l->license_type->getLabel() }}</small></span>
                        <span class="xr-pill xr-pill--warning">{{ $l->valid_until->format('d.m H:i') }}</span>
                    </a>
                @empty
                    <p class="xr-alerts__empty">Найближчими днями нічого не спливає.</p>
                @endforelse
            </div>
            <div class="xr-alerts__col">
                <h3 class="xr-alerts__h"><span class="xr-dot xr-dot--danger"></span>Підозріла активність окулярів <span class="xr-count">{{ $suspicious->count() }}</span></h3>
                @forelse ($suspicious as $d)
                    <a href="{{ $url($d->user) }}" class="xr-alerts__item">
                        <span><b>{{ $d->device_name }}</b> <code>{{ $d->hardware_serial }}</code><br><small>{{ $d->user?->display_name }}</small></span>
                        <span class="xr-pill xr-pill--danger">
                            {{ $d->clock_tamper_detected_at ? 'відкат годинника' : 'офлайн '.(int) $d->last_sync_at->diffInDays(now()).' дн.' }}
                        </span>
                    </a>
                @empty
                    <p class="xr-alerts__empty">Підозрілої активності немає.</p>
                @endforelse
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
