<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Enums\ModePaiement;
use App\Enums\StatutLot;
use App\Enums\StatutReproducteur;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\LotEngraissement;
use App\Models\ParametreVente;
use App\Models\Reproducteur;
use App\Models\Tarif;
use App\Models\Traitement;
use App\Models\Vente;
use App\Services\BilanFinancier;
use App\Services\GestionVentes;
use App\Support\Monnaie;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VenteController extends Controller
{
    private const ATTRIBUTS = [
        'lignes' => 'articles',
        'lignes.*.type' => 'type',
        'lignes.*.id' => 'article',
        'lignes.*.quantite' => 'quantité',
        'lignes.*.poids_kg' => 'poids',
        'client_id' => 'client',
        'nouveau_client_nom' => 'nom du nouveau client',
        'nouveau_client_telephone' => 'téléphone',
        'remise_pct' => 'remise',
        'montant_paye' => 'montant payé',
        'mode_paiement' => 'mode de paiement',
        'operateur' => 'opérateur',
        'reference' => 'référence',
        'montant' => 'montant',
    ];

    public function index(BilanFinancier $bilan)
    {
        $duJour = Vente::with(['client', 'lignes'])
            ->whereDate('date_vente', today()->toDateString())
            ->latest('id')->get();

        return view('gestionnaire.ventes.index', [
            'ventesDuJour' => $duJour,
            'totalJour' => (float) $duJour->sum('total_net'),
            'aEncaisser' => Vente::with('client')
                ->whereColumn('montant_paye', '<', 'total_net')
                ->orderBy('date_vente')->limit(30)->get(),
            'objectifs' => $bilan->progressionObjectifs(),
        ]);
    }

    public function create()
    {
        $tarifs = Tarif::where('actif', true)->get();
        $parametres = ParametreVente::actuel();
        $aujourdhui = today()->toDateString();

        // Un seul passage pour repérer les animaux et lots bloqués par un délai d'attente
        $bloques = Traitement::where('date_debut', '<=', $aujourdhui)
            ->where('date_fin_attente', '>=', $aujourdhui)
            ->get()
            ->groupBy(fn (Traitement $t) => $t->traitable_type.'-'.$t->traitable_id)
            ->map(fn ($groupe) => $groupe->max('date_fin_attente'));

        $lots = LotEngraissement::where('statut', StatutLot::EnCours->value)
            ->where('effectif_actuel', '>', 0)
            ->orderBy('code')->get()
            ->map(fn (LotEngraissement $lot) => [
                'id' => $lot->id,
                'code' => $lot->code,
                'effectif' => $lot->effectif_actuel,
                'bloque_jusqu' => $bloques->get('lot-'.$lot->id)?->format('d/m/Y'),
            ])->values();

        $animaux = Reproducteur::with('cage')
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('statut', StatutReproducteur::Actif->value)->whereNotNull('valide_le'))
                ->orWhere('statut', StatutReproducteur::Reforme->value))
            ->orderBy('matricule')->get()
            ->map(fn (Reproducteur $a) => [
                'id' => $a->id,
                'matricule' => $a->matricule,
                'sexe' => $a->sexe->libelle(),
                'categorie' => $a->statut === StatutReproducteur::Actif ? 'reproducteur' : 'reforme',
                'cage' => $a->cage?->code,
                'bloque_jusqu' => $bloques->get('reproducteur-'.$a->id)?->format('d/m/Y'),
            ])->values();

        $lignes = collect(old('lignes', [['type' => 'lot', 'id' => '', 'quantite' => 1, 'poids_kg' => '']]))
            ->map(fn (array $l) => [
                'type' => $l['type'] ?? 'lot',
                'id' => $l['id'] ?? '',
                'quantite' => $l['quantite'] ?? 1,
                'poids_kg' => $l['poids_kg'] ?? '',
            ])->values();

        return view('gestionnaire.ventes.create', [
            'sansTarif' => $tarifs->isEmpty(),
            'clients' => Client::orderBy('nom')->get(),
            'config' => [
                'lots' => $lots,
                'animaux' => $animaux,
                'tarifs' => $tarifs->mapWithKeys(fn (Tarif $t) => [
                    $t->categorie->value => ['mode' => $t->mode->value, 'prix' => (float) $t->prix],
                ]),
                'remise_max' => (float) $parametres->remise_max_pct,
                'credit' => (bool) $parametres->credit_autorise,
                'devise' => config('cunicole.devise.symbole'),
                'decimales' => Monnaie::decimales(),
                'lignes' => $lignes,
                'remise' => old('remise_pct', 0),
                'paye' => old('montant_paye'),
                'payeManuel' => old('montant_paye') !== null,
                'modeClient' => old('nouveau_client_nom') ? 'nouveau' : (old('client_id') ? 'existant' : 'passage'),
                'modePaiement' => old('mode_paiement', 'especes'),
            ],
        ]);
    }

    public function store(Request $request, GestionVentes $ventes)
    {
        $donnees = $request->validate([
            'jeton' => ['required', 'uuid'],
            'lignes' => ['required', 'array', 'min:1', 'max:20'],
            'lignes.*.type' => ['required', 'in:lot,reproducteur'],
            'lignes.*.id' => ['required', 'integer'],
            'lignes.*.quantite' => ['nullable', 'integer', 'min:1', 'max:500'],
            'lignes.*.poids_kg' => ['nullable', 'numeric', 'min:0.1', 'max:5000'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'nouveau_client_nom' => ['nullable', 'string', 'max:100', 'prohibits:client_id'],
            'nouveau_client_telephone' => ['nullable', 'string', 'max:30'],
            'remise_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'montant_paye' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'mode_paiement' => ['nullable', Rule::enum(ModePaiement::class)],
            'operateur' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'lignes.required' => 'Ajoutez au moins un article à la vente.',
            'nouveau_client_nom.prohibits' => 'Choisissez un client existant OU saisissez un nouveau client, pas les deux.',
        ], self::ATTRIBUTS);

        // Formulaire déjà envoyé (double clic, réseau lent) : on retrouve la vente au lieu d'en créer une seconde
        if ($existante = Vente::where('jeton', $donnees['jeton'])->first()) {
            return redirect()->route('gestionnaire.ventes.show', $existante)
                ->with('status', "Cette vente était déjà enregistrée ({$existante->numero}).");
        }

        try {
            $vente = $ventes->enregistrer($donnees, $request->user());
        } catch (UniqueConstraintViolationException $e) {
            $vente = Vente::where('jeton', $donnees['jeton'])->first();

            if ($vente === null) {
                throw $e;
            }
        }

        return redirect()->route('gestionnaire.ventes.show', $vente)
            ->with('status', "Vente {$vente->numero} enregistrée. Remettez le reçu au client.");
    }

    public function show(Vente $vente)
    {
        $vente->load(['client', 'lignes', 'paiements.utilisateur', 'utilisateur']);

        return view('gestionnaire.ventes.show', ['vente' => $vente]);
    }

    public function paiement(Request $request, Vente $vente, GestionVentes $ventes)
    {
        $donnees = $request->validate([
            'montant' => ['required', 'numeric', 'min:0.01', 'max:1000000000'],
            'mode_paiement' => ['required', Rule::enum(ModePaiement::class)],
            'operateur' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
        ], [], self::ATTRIBUTS);

        $ventes->encaisser($vente, $donnees, $request->user());

        return back()->with('status', 'Paiement enregistré.');
    }
}