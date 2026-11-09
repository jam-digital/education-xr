{{-- Модалка покупки: дані закладу → /buy → LiqPay. Потрібні $lessons, $subjects, $packages --}}
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
      <p class="muted small-text">Натискаючи «Перейти до оплати», ви приймаєте <a href="{{ route('page.offer') }}" target="_blank">публічну оферту</a> і <a href="{{ route('page.privacy') }}" target="_blank">політику конфіденційності</a>. Після оплати доступ відкриється автоматично, а рахунок і акт будуть у кабінеті закладу.</p>
      <button class="btn primary" type="submit">Перейти до оплати</button>
    </form>
  </div>
</div>

@push('scripts')
<script>
(function () {
  const packs = @json(collect($packages)->map(fn ($p) => ['title' => $p['title'], 'price' => \App\Services\Storefront::priceLabel($p), 'scope' => $p['scope']]));
  const modal = document.getElementById('buyModal'), title = document.getElementById('buyTitle'), price = document.getElementById('buyPrice');
  const pkg = document.getElementById('buyPackage');
  const lessonField = document.getElementById('lessonField'), lessonSel = document.getElementById('buyLesson');
  const subjectField = document.getElementById('subjectField'), subjectSel = document.getElementById('buySubject');
  function open(key, lessonId, subject) {
    const p = packs[key]; if (!p) return;
    pkg.value = key; title.textContent = p.title; price.textContent = p.price;
    lessonField.hidden = lessonSel.disabled = p.scope !== 'lesson';
    subjectField.hidden = subjectSel.disabled = p.scope !== 'subject';
    if (lessonId) lessonSel.value = lessonId;
    if (subject) subjectSel.value = subject;
    modal.hidden = false; document.body.classList.add('modal-open');
    const first = modal.querySelector('input[name=name]'); if (first) first.focus();
  }
  function close() { modal.hidden = true; document.body.classList.remove('modal-open'); }
  document.querySelectorAll('[data-buy]').forEach(b => b.addEventListener('click', () => open(b.dataset.buy, b.dataset.app, b.dataset.subject)));
  modal.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', close));
  modal.addEventListener('click', e => { if (e.target === modal) close(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) close(); });
  @if($errors->any() && old('_form') === 'buy') open(@json(old('package', 'lesson')), @json(old('lesson_id'))); @endif
})();
</script>
@endpush
