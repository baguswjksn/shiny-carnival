<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login · Sewing Scheduler</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<main style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px">
    <form method="POST" action="{{ route('login') }}" class="table-wrap" style="width:min(360px,100%);padding:24px">
        @csrf
        <h1 style="margin-bottom:16px">Sewing Scheduler</h1>

        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   @class(['invalid' => $errors->has('email')])>
            @error('email') <small class="field-error">{{ $message }}</small> @enderror
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
            @error('password') <small class="field-error">{{ $message }}</small> @enderror
        </div>

        <label class="check" style="display:flex;gap:8px;align-items:center;margin-bottom:16px">
            <input type="checkbox" name="remember"> Ingat saya
        </label>

        <button type="submit" class="btn primary" style="width:100%">Masuk</button>
    </form>
</main>
</body>
</html>
