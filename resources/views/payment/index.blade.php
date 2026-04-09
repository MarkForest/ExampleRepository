<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список платежей</title>
</head>
<body>
<h1>Список платежей</h1>

@if (session('success'))
    <div style="color: #0a7a34;">
        {{ session('success') }}
    </div>
@endif

<p>
    <a href="{{ route('payment.create') }}">Добавить новый платеж</a>
</p>

<table border="1" cellpadding="8" cellspacing="0">
    <thead>
    <tr>
        <th>ID</th>
        <th>ID пользователя</th>
        <th>Email пользователя</th>
        <th>Сумма</th>
        <th>Валюта</th>
        <th>Статус</th>
        <th>Дата создания</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($payments as $payment)
        <tr>
            <td>{{ $payment->id }}</td>
            <td>{{ $payment->user_id }}</td>
            <td>{{ $payment->user?->email ?? '—' }}</td>
            <td>{{ number_format((float) $payment->amount, 2, '.', ' ') }}</td>
            <td>{{ strtoupper($payment->currency) }}</td>
            <td>{{ $payment->status }}</td>
            <td>{{ $payment->created_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="7">Платежей пока нет.</td>
        </tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
