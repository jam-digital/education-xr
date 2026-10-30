@extends('landing.layout')

@section('content')
<section class="hero">
  <div class="hero-logo"><img src="{{ asset('img/logo-white.svg') }}" alt="VR-освіта"><div class="sub">Віртуальні лабораторії для школи</div></div>
  <h1>VR-уроки з фізики, хімії та математики<br>для вашого класу</h1>
  <p class="lead">Інтерактивні VR-лабораторії для Meta Quest і Pico: досліди з оптики, електрики й хімічних реакцій, стереометрія в просторі, подорож усередину клітини. Пакети запусків або безліміт на навчальний рік для шкіл, ліцеїв і освітніх центрів.</p>
  <div class="row">
    <a class="btn primary" href="#packages">Обрати пакет</a>
    <a class="btn ghost" href="#lessons">Дивитись уроки</a>
  </div>
  <ul class="facts">
    <li><b>{{ $lessons->count() }}</b><span>VR-уроків у бібліотеці</span></li>
    <li><b>{{ $lessons->pluck('subject_category')->unique()->count() }}</b><span>шкільних предметів</span></li>
    <li><b>45 хв</b><span>повноцінний урок у шоломі</span></li>
    <li><b>офлайн</b><span>працює без Wi-Fi у класі</span></li>
  </ul>
</section>

<section id="lessons" class="section">
  <h2>Бібліотека VR-уроків</h2>
  <p class="muted center">Кожен урок має PDF-методичку для вчителя, встановлюється на шкільні окуляри через VR-Osvita Client і запускається з кіоск-режиму.</p>
  <div class="tabs" role="tablist">
    <button class="tab active" type="button" data-filter="all">Усі предмети</button>
    @foreach($subjects as $s)
      @if($lessons->contains('subject_category', $s))
        <button class="tab" type="button" data-filter="{{ $s->value }}" style="--c: {{ $s->hex() }}">{{ $s->getLabel() }}</button>
      @endif
    @endforeach
  </div>
  <div class="apps">
    @foreach($lessons as $lesson)
      @php
        $img = $lesson->cover_image_url ? asset('storage/'.$lesson->cover_image_url) : (file_exists(public_path("img/lessons/{$lesson->app_key}.svg")) ? asset("img/lessons/{$lesson->app_key}.svg") : null);
        $isNew = $lesson->released_at && $lesson->released_at->gt(now()->subDays(45));
      @endphp
      <article class="app" data-subject="{{ $lesson->subject_category->value }}">
        <div class="art" @if($img) style="background-image:url('{{ $img }}')" @endif>
          @unless($img)<span>{{ mb_substr($lesson->title, 0, 1) }}</span>@endunless
          <span class="subj" style="--c: {{ $lesson->subject_category->hex() }}">{{ $lesson->subject_category->getLabel() }}</span>
          @if($isNew)<span class="new">NEW</span>@endif
        </div>
        <div class="body">
          <h3>{{ $lesson->title }}</h3>
          <p>{{ $lesson->description }}</p>
          <div class="meta">
            @if($lesson->recommended_grades)<span class="pill">{{ $lesson->recommended_grades }}</span>@endif
            <span class="pill">{{ $lesson->max_session_duration_minutes }} хв</span>
            <span class="pill">{{ $lesson->default_tickets_count }} запусків у пакеті</span>
          </div>
          <button class="btn primary small" type="button" data-buy="lesson" data-app="{{ $lesson->id }}">Купити запуски</button>
        </div>
      </article>
    @endforeach
    @if($lessons->isEmpty())<p class="muted center">Бібліотека поповнюється.</p>@endif
  </div>
</section>

<section id="how" class="section">
  <h2>Як це працює в школі</h2>
  <div class="steps">
    <div class="step"><b>1</b><h3>Реєструємо окуляри</h3><p>Кожен шолом прив'язується до закладу за серійним номером або 6-значним кодом з екрана. Учням не потрібні логіни.</p></div>
    <div class="step"><b>2</b><h3>Вчитель обирає урок</h3><p>VR-Osvita Client сам завантажує уроки на окуляри. Учні бачать лише освітнє меню, вихід тільки за PIN-кодом вчителя.</p></div>
    <div class="step"><b>3</b><h3>Урок без Wi-Fi</h3><p>Офлайн-сертифікат дозволяє провести урок у класі чи укритті без інтернету. Перезапуск у перші 90 секунд не списує запуск.</p></div>
    <div class="step"><b>4</b><h3>Звітність</h3><p>Статистика проведених уроків за предметами, рахунки й акти для бухгалтерії та казначейства за ЄДРПОУ.</p></div>
  </div>
</section>

<section id="packages" class="section">
  <h2>Пакети для закладів освіти</h2>
  <p class="muted center">Оплата карткою через LiqPay, доступ відкривається одразу після оплати. Для оплати за рахунком через казначейство напишіть нам.</p>
  <div class="packs">
    @foreach($packages as $key => $pack)
      <article class="pack {{ $pack['scope'] === 'all' ? 'top' : '' }}">
        @if($pack['scope'] === 'all')<div class="ribbon">Для всієї школи</div>@endif
        <h3>{{ $pack['title'] }}</h3>
        <div class="price">{{ \App\Services\Storefront::priceLabel($pack) }}</div>
        <div class="tagline">{{ $pack['tagline'] }}</div>
        <ul>@foreach($pack['features'] as $f)<li>{{ $f }}</li>@endforeach</ul>
        <button class="btn {{ $pack['scope'] === 'all' ? 'primary' : 'ghost' }}" type="button" data-buy="{{ $key }}">{{ $pack['scope'] === 'lesson' ? 'Купити запуски' : 'Оплатити пакет' }}</button>
      </article>
    @endforeach
  </div>
</section>

<section id="contact" class="section narrow">
  <h2>Пробний тиждень для вашої школи</h2>
  <p class="muted center">Відкриємо 7 днів безкоштовного доступу для презентації директору, допоможемо налаштувати окуляри й підберемо пакет. Пишіть, якщо потрібен рахунок для казначейства.</p>
  @if(session('contact_sent'))
    <div class="ok">Дякуємо! Ми отримали ваше повідомлення і відповімо на email найближчим часом.</div>
  @endif
  @if($errors->any() && old('_form') === 'contact')
    <div class="err">{{ $errors->first() }}</div>
  @endif
  <form class="form card" method="post" action="{{ route('contact') }}">
    @csrf
    <input type="hidden" name="_form" value="contact">
    <div class="grid2">
      <label>Ім'я<input name="name" required maxlength="120" value="{{ old('_form') === 'contact' ? old('name') : '' }}"></label>
      <label>Email<input name="email" type="email" required maxlength="190" value="{{ old('_form') === 'contact' ? old('email') : '' }}"></label>
      <label>Телефон<input name="phone" maxlength="40" value="{{ old('_form') === 'contact' ? old('phone') : '' }}"></label>
      <label>Заклад освіти<input name="company" maxlength="190" placeholder="Ліцей №142 м. Києва" value="{{ old('_form') === 'contact' ? old('company') : '' }}"></label>
    </div>
    <label>Повідомлення<textarea name="message" rows="4" maxlength="3000" placeholder="Скільки окулярів у класі, які предмети цікавлять">{{ old('_form') === 'contact' ? old('message') : '' }}</textarea></label>
    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">
    <button class="btn primary" type="submit">Надіслати заявку</button>
  </form>
</section>

{{-- Модальне вікно покупки: дані закладу → /buy → LiqPay --}}
<div class="modal" id="buyModal" hidden>
  <div class="modal-card card">
    <button class="x" type="button" data-close aria-label="Закрити">×</button>
    <h3 id="buyTitle">Купити</h3>
    <div class="muted" id="buyPrice"></div>
    @if($errors->any() && old('_form') === 'buy')<div class="err">{{ $errors->first() }}</div>@endif
    <form class="form" method="post" action="{{ route('buy') }}">
      @csrf
      <input type="hidden" name="_form" value="buy">
      <input type="hidden" name="package" id="buyPackage" value="{{ old('package', 'lesson') }}">
      <label id="lessonField">VR-урок
        <select name="lesson_id" id="buyLesson">
          @foreach($lessons->groupBy(fn ($l) => $l->subject_category->getLabel()) as $group => $items)
            <optgroup label="{{ $group }}">
              @foreach($items as $lesson)<option value="{{ $lesson->id }}" @selected(old('lesson_id') === $lesson->id)>{{ $lesson->title }}</option>@endforeach
            </optgroup>
          @endforeach
        </select>
      </label>
      <label id="subjectField">Предмет
        <select name="subject" id="buySubject">
          @foreach($subjects as $s)
            @if($lessons->contains('subject_category', $s))<option value="{{ $s->value }}" @selected(old('subject') === $s->value)>{{ $s->getLabel() }}</option>@endif
          @endforeach
        </select>
      </label>
      <div class="grid2">
        <label>Ім'я<input name="name" required maxlength="120" value="{{ old('_form') === 'buy' ? old('name') : '' }}"></label>
        <label>Email<input name="email" type="email" required maxlength="190" value="{{ old('_form') === 'buy' ? old('email') : '' }}"></label>
        <label>Заклад освіти<input name="company" maxlength="190" value="{{ old('_form') === 'buy' ? old('company') : '' }}"></label>
        <label>Телефон<input name="phone" maxlength="40" value="{{ old('_form') === 'buy' ? old('phone') : '' }}"></label>
      </div>
      <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">
      <p class="muted small-text">Після оплати доступ з'явиться в кабінеті закладу автоматично, а на email прийде рахунок і акт.</p>
      <button class="btn primary" type="submit">Перейти до оплати</button>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  // фільтр бібліотеки за предметами
  const tabs = document.querySelectorAll('.tab');
  tabs.forEach(t => t.addEventListener('click', () => {
    tabs.forEach(x => x.classList.toggle('active', x === t));
    document.querySelectorAll('.app[data-subject]').forEach(a => { a.hidden = t.dataset.filter !== 'all' && a.dataset.subject !== t.dataset.filter; });
  }));

  const packs = @json(collect($packages)->map(fn ($p) => ['title' => $p['title'], 'price' => \App\Services\Storefront::priceLabel($p), 'scope' => $p['scope']]));
  const modal = document.getElementById('buyModal'), title = document.getElementById('buyTitle'), price = document.getElementById('buyPrice');
  const pkg = document.getElementById('buyPackage');
  const lessonField = document.getElementById('lessonField'), lessonSel = document.getElementById('buyLesson');
  const subjectField = document.getElementById('subjectField'), subjectSel = document.getElementById('buySubject');
  function open(key, lessonId) {
    const p = packs[key]; if (!p) return;
    pkg.value = key; title.textContent = p.title; price.textContent = p.price;
    lessonField.hidden = lessonSel.disabled = p.scope !== 'lesson';
    subjectField.hidden = subjectSel.disabled = p.scope !== 'subject';
    if (lessonId) lessonSel.value = lessonId;
    modal.hidden = false; document.body.classList.add('modal-open');
    const first = modal.querySelector('input[name=name]'); if (first) first.focus();
  }
  function close() { modal.hidden = true; document.body.classList.remove('modal-open'); }
  document.querySelectorAll('[data-buy]').forEach(b => b.addEventListener('click', () => open(b.dataset.buy, b.dataset.app)));
  modal.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', close));
  modal.addEventListener('click', e => { if (e.target === modal) close(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) close(); });
  @if($errors->any() && old('_form') === 'buy') open(@json(old('package', 'lesson')), @json(old('lesson_id'))); @endif
})();
</script>
@endpush
