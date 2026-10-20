<?php

namespace App\Http\Controllers\Proprietaire;

use App\Http\Controllers\Controller;
use App\Models\Aliment;
use App\Models\MouvementAliment;
use App\Notifications\AlerteStockAliment;
use App\Services\GestionAliments;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AlimentController extends Controller
{
    private const ATTRIBUTS = [
        'nom' => "nom de l'aliment",
        'poids_sac_kg' => "poids d'un sac",
        'quantite' => 'variation',
        'motif' => 'motif',
    ];

    public function index(Request $request, GestionAliments $aliments)
    {
        return view('proprietaire.aliments.index', [
            'etats' => $aliments->etatStocks(),
            'mouvements' => MouvementAliment::with(['aliment', 'utilisateur'])
                ->latest('date_mouvement')->latest('id')->limit(30)->get(),
            'alertes' => $request->user()->unreadNotifications()
                ->where('type', AlerteStockAliment::class)->limit(10)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:100', 'unique:aliments,nom'],
            'poids_sac_kg' => ['nullable', 'numeric', 'min:0.1', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], self::ATTRIBUTS);

        Aliment::create($donnees);

        return back()->with('status', 'Aliment ajouté. Renseignez son stock de départ avec « Corriger l\'inventaire », puis définissez ses rations.');
    }

    public function update(Request $request, Aliment $aliment)
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:100', Rule::unique('aliments', 'nom')->ignore($aliment->id)],
            'poids_sac_kg' => ['nullable', 'numeric', 'min:0.1', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], self::ATTRIBUTS);

        $actif = $request->boolean('actif');

        if (! $actif && $aliment->actif && $aliment->rations()->where('actif', true)->exists()) {
            throw ValidationException::withMessages([
                'actif' => "Impossible de désactiver {$aliment->nom} : il fait partie de formules de rationnement actives. Désactivez d'abord ces rations.",
            ]);
        }

        $aliment->update([...$donnees, 'actif' => $actif]);

        return back()->with('status', 'Aliment modifié.');
    }

    public function ajustement(Request $request, Aliment $aliment, GestionAliments $aliments)
    {
        $donnees = $request->validate([
            'quantite' => ['required', 'numeric', 'not_in:0', 'between:-100000,100000'],
            'motif' => ['required', 'string', 'min:3', 'max:200'],
        ], [], self::ATTRIBUTS);

        $aliments->ajuster($aliment, (float) $donnees['quantite'], $donnees['motif'], $request->user());

        return back()->with('status', 'Inventaire corrigé.');
    }

    public function alertesLues(Request $request)
    {
        $request->user()->unreadNotifications()
            ->where('type', AlerteStockAliment::class)
            ->get()
            ->markAsRead();

        return back()->with('status', 'Alertes de stock marquées comme lues.');
    }
}