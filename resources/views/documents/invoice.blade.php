<!doctype html>
<html lang="uk"><head><meta charset="utf-8">@include('documents._style')</head>
<body>
<h1>Рахунок-фактура № {{ $p->invoice_number }}</h1>
<div class="muted">від {{ $p->created_at->format('d.m.Y') }}</div>

<table class="req" style="margin-top:14px">
    <tr><td style="width:22%"><b>Постачальник</b></td>
        <td>{{ $seller['name'] }}, код ЄДРПОУ {{ $seller['edrpou'] }}<br>{{ $seller['address'] }}<br>
            IBAN {{ $seller['iban'] }}, {{ $seller['bank'] }}<br>{{ $seller['tax_note'] }}</td></tr>
    <tr><td><b>Покупець</b></td>
        <td>{{ $p->user->school_or_org_name ?: $p->user->contact_person_name }}@if($p->user->edrpou_tax_id), код ЄДРПОУ {{ $p->user->edrpou_tax_id }}@endif
            @if($p->user->legal_address)<br>{{ $p->user->legal_address }}@endif
            @if($p->user->iban_treasury)<br>IBAN {{ $p->user->iban_treasury }}@endif</td></tr>
</table>

<table class="items">
    <tr><th style="width:5%">№</th><th>Найменування</th><th style="width:12%">Од.</th><th style="width:10%">К-сть</th><th style="width:16%" class="r">Сума, {{ $p->currency }}</th></tr>
    <tr><td>1</td>
        <td>{{ $seller['service'] }}: {{ $p->grantLabel() }}</td>
        <td>послуга</td><td>1</td><td class="r">{{ number_format((float) $p->amount, 2, ',', ' ') }}</td></tr>
</table>
<div class="total"><b>Разом до сплати: {{ number_format((float) $p->amount, 2, ',', ' ') }} {{ $p->currency }}</b></div>
@if($p->currency === 'UAH')<div class="total">{{ $amountWords }}. {{ $seller['tax_note'] }}.</div>@endif

<p style="margin-top:18px">Призначення платежу: «Оплата за рахунком {{ $p->invoice_number }} від {{ $p->created_at->format('d.m.Y') }}». Доступ відкривається автоматично після зарахування коштів.</p>

<table class="sign"><tr><td>Виписав: <span class="line"></span> {{ $seller['signer'] }}</td><td></td></tr></table>
</body></html>
