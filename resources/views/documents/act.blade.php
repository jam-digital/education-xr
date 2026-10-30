<!doctype html>
<html lang="uk"><head><meta charset="utf-8">@include('documents._style')</head>
<body>
<h1>Акт виконаних робіт (наданих послуг) № {{ $p->invoice_number }}</h1>
<div class="muted">від {{ ($p->paid_at ?? now())->format('d.m.Y') }}, до рахунку {{ $p->invoice_number }} від {{ $p->created_at->format('d.m.Y') }}</div>

<p style="margin-top:14px">Ми, що нижче підписалися, представник Виконавця <b>{{ $seller['name'] }}</b> (код ЄДРПОУ {{ $seller['edrpou'] }})
    та представник Замовника <b>{{ $p->user->school_or_org_name ?: $p->user->contact_person_name }}</b>@if($p->user->edrpou_tax_id) (код ЄДРПОУ {{ $p->user->edrpou_tax_id }})@endif,
    склали цей акт про те, що Виконавець надав, а Замовник прийняв такі послуги:</p>

<table class="items">
    <tr><th style="width:5%">№</th><th>Найменування послуги</th><th style="width:10%">К-сть</th><th style="width:16%" class="r">Сума, {{ $p->currency }}</th></tr>
    <tr><td>1</td><td>{{ $seller['service'] }}: {{ $p->grantLabel() }}</td><td>1</td>
        <td class="r">{{ number_format((float) $p->amount, 2, ',', ' ') }}</td></tr>
</table>
<div class="total"><b>Загальна вартість: {{ number_format((float) $p->amount, 2, ',', ' ') }} {{ $p->currency }}</b></div>
@if($p->currency === 'UAH')<div class="total">{{ $amountWords }}. {{ $seller['tax_note'] }}.</div>@endif

<p>Сторони претензій одна до одної не мають.</p>

<table class="sign"><tr>
    <td><b>Від Виконавця</b><br><br><span class="line"></span><br>{{ $seller['signer'] }}</td>
    <td><b>Від Замовника</b><br><br><span class="line"></span><br>{{ $p->user->contact_person_name }}</td>
</tr></table>
</body></html>
