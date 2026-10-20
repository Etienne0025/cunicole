<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre') - {{ config('app.name') }}</title>
    @yield('tete')
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f3f4f6; color: #1f2937; margin: 0; padding: 24px; }
        main { max-width: 440px; margin: 48px auto; background: #fff; border-radius: 12px; padding: 28px 24px; box-shadow: 0 1px 4px rgba(0,0,0,.15); text-align: center; }
        .code { font-size: 3rem; font-weight: 700; color: #047857; margin: 0; }
        h1 { font-size: 1.25rem; margin: 8px 0 12px; }
        p { line-height: 1.5; margin: 0 0 8px; }
        a, button { display: block; width: 100%; box-sizing: border-box; margin-top: 12px; padding: 12px; border-radius: 8px; border: 0; font-size: 1rem; text-decoration: none; cursor: pointer; }
        .principal { background: #047857; color: #fff; }
        .secondaire { background: #e5e7eb; color: #1f2937; }
    </style>
</head>
<body>
    <main>
        <p class="code">@yield('code')</p>
        <h1>@yield('titre')</h1>
        <p>@yield('message')</p>
        <a class="principal" href="{{ url('/') }}">Retour à l'accueil</a>
        <button class="secondaire" type="button" onclick="location.reload()">Réessayer</button>
    </main>
</body>
</html>