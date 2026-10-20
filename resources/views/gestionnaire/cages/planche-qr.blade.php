<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        td { width: 33.33%; height: 62mm; border: 1px dashed #999; text-align: center; padding: 6px; }
        .code { font-size: 16pt; font-weight: bold; margin-top: 4px; }
        .sous { font-size: 8pt; color: #555; }
    </style>
</head>
<body>
    @foreach ($etiquettes->chunk(12) as $page)
        <table>
            @foreach ($page->chunk(3) as $ligne)
                <tr>
                    @foreach ($ligne as $etiquette)
                        <td>
                            <img src="{{ $etiquette['qr'] }}" width="150" height="150">
                            <div class="code">{{ $etiquette['cage']->code }}</div>
                            <div class="sous">{{ $etiquette['cage']->batiment->nom }}</div>
                        </td>
                    @endforeach
                    @for ($i = $ligne->count(); $i < 3; $i++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>
        @if (! $loop->last)
            <div style="page-break-after: always;"></div>
        @endif
    @endforeach
</body>
</html>