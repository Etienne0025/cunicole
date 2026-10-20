<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#047857">
    <title>Hors ligne - {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f3f4f6; color: #1f2937; display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 1rem; }
        .carte { background: #fff; border-radius: .75rem; box-shadow: 0 1px 4px rgba(0, 0, 0, .15); padding: 1.5rem; max-width: 26rem; }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
        p { line-height: 1.5; font-size: .95rem; }
        a, button { display: block; width: 100%; box-sizing: border-box; text-align: center; margin-top: .75rem; padding: .75rem; border-radius: .5rem; border: 0; font-size: 1rem; text-decoration: none; cursor: pointer; }
        .principal { background: #047857; color: #fff; }
        .secondaire { background: #e5e7eb; color: #111827; }
    </style>
</head>
<body>
    <div class="carte">
        <h1>Vous êtes hors ligne</h1>
        <p>
            Cette page n'a pas encore été enregistrée sur ce téléphone. Retournez à une page déjà consultée :
            les saisies que vous ferez resteront sur le téléphone et seront envoyées dès que le réseau reviendra.
        </p>
        <a class="principal" href="/gestionnaire">Retour à l'accueil</a>
        <a class="secondaire" href="/gestionnaire/synchronisation">Saisies en attente</a>
        <button class="secondaire" type="button" onclick="location.reload()">Réessayer</button>
    </div>
</body>
</html>