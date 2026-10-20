@use('App\Support\Monnaie')

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; }
        h1 { font-size: 15pt; margin: 0; }
        .centre { text-align: center; }
        .petit { font-size: 8pt; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 4px 3px; border-bottom: 1px solid #ccc; text-align: left; vertical-align: top; }
        .d { text-align: right; }
        .total td { border-bottom: none; font-size: 12pt; font-weight: bold; }
        .cadre { border: 1px solid #111; padding: 6px; margin-top: 10px; text-align: center; font-weight: bold; }
    </style>
</head>
<body>
    <div class="centre">
        <h1>{{ config('cunicole.ferme.nom') }}</h1>
        @if (config('cunicole.ferme.contact'))
            <div class="petit">{{ config('cunicole.ferme.contact') }}</div>
        @endif
        <div style="margin-top: 8px; font-size: 12pt; font-weight: bold;">REÇU DE VENTE N° {{ $vente->numero }}</div>
    </div>

    <p>
        Date : <strong>{{ $vente->date_vente->format('d/m/Y') }}</strong><br>
        Client : <strong>{{ $vente->client?->nom ?? 'Client de passage' }}</strong>
        @if ($vente->client?->telephone) ({{ $vente->client->telephone }}) @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>Article</th>
                <th>Qté</th>
                <th>Poids</th>
                <th class="d">Prix</th>
                <th class="d">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($vente->lignes as $ligne)
                <tr>
                    <td>{{ $ligne->description }}</td>
                    <td>{{ $ligne->quantite }}</td>
                    <td>{{ $ligne->poids_kg !== null ? number_format($ligne->poids_kg, 2, ',', ' ').' kg' : '' }}</td>
                    <td class="d">{{ Monnaie::format($ligne->prix_applique, false) }} {{ $ligne->mode_prix->suffixe() }}</td>
                    <td class="d">{{ Monnaie::format($ligne->montant) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table>
        <tr><td>Total brut</td><td class="d">{{ Monnaie::format($vente->total_brut) }}</td></tr>
        @if ((float) $vente->remise_montant > 0)
            <tr><td>Remise ({{ rtrim(rtrim(number_format($vente->remise_pct, 2, ',', ''), '0'), ',') }} %)</td><td class="d">− {{ Monnaie::format($vente->remise_montant) }}</td></tr>
        @endif
        <tr class="total"><td>TOTAL À PAYER</td><td class="d">{{ Monnaie::format($vente->total_net) }}</td></tr>
        <tr><td>Montant payé</td><td class="d">{{ Monnaie::format($vente->montant_paye) }}</td></tr>
    </table>

    @if ($vente->paiements->isNotEmpty())
        <p class="petit">
            @foreach ($vente->paiements as $paiement)
                {{ $paiement->date_paiement->format('d/m/Y') }} : {{ Monnaie::format($paiement->montant) }}
                ({{ $paiement->mode->libelle() }}@if ($paiement->operateur), {{ $paiement->operateur }}@endif@if ($paiement->reference), réf. {{ $paiement->reference }}@endif)<br>
            @endforeach
        </p>
    @endif

    <div class="cadre">
        @if ($vente->solde() > 0)
            RESTE À PAYER : {{ Monnaie::format($vente->solde()) }}
        @else
            PAYÉ EN TOTALITÉ
        @endif
    </div>

    <p class="centre petit" style="margin-top: 14px;">
        Vendeur : {{ $vente->utilisateur?->nom_complet ?? '-' }} · Merci de votre confiance.
    </p>
</body>
</html>