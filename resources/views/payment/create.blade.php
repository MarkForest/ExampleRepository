<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создание платежа</title>
</head>
<body>
<h1>Добавить платеж</h1>

@if ($errors->any())
    <div style="color: #b00020;">
        <h2>Ошибка валидации</h2>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('success'))
    <div style="color: #008000;">
        <h2>{{ session('success') }}</h2>
    </div>
@endif

<form method="POST" action="{{ route('payment.store') }}">
    @csrf

    <div>
        <label for="user_id">ID пользователя</label>
        <select id="user_id" name="user_id" required>
            <option value="">Виберіть користувача</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="amount">Сумма</label>
        <input
            id="amount"
            name="amount"
            type="number"
            min="0"
            step="0.01"
            value="{{ old('amount') }}"
            required
        >
    </div>

    <div>
        <label for="status">Статус</label>
        <select id="status" name="status" required>
            <option value="">Выберите статус</option>
            <option value="pending" @selected(old('status') === 'pending')>pending</option>
            <option value="completed" @selected(old('status') === 'completed')>completed</option>
            <option value="failed" @selected(old('status') === 'failed')>failed</option>
        </select>
    </div>

    <div>
        <label for="currency">Валюта (ISO-3)</label>
        <input
            id="currency"
            name="currency"
            type="text"
            minlength="3"
            maxlength="3"
            value="{{ old('currency', 'UAH') }}"
            required
        >
    </div>

    <button type="submit">Зберегти</button>
</form>
</body>
</html>
