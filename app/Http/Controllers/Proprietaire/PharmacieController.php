<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\TypeProduit;
use App\Http\Controllers\Controller;
use App\Models\MouvementPharmacie;
use App\Models\ProduitVeterinaire;
use App\Services\Pharmacie;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PharmacieController extends Controller
{
    private const ATTRIBUTS = [
        'nom' => 'nom du produit',
        'unite' => 'unité',
        'seuil_alerte' => "seuil d'alerte",
        'delai_attente_jours' => "délai d'attente",
        'quantite' => 'quantité',
        'date_achat' => "date d'achat",
        'cout_total' => 'coût total',
        'motif' => 'motif',
    ];

    public function index()
    {
        return view('proprietaire.pharmacie.index', [
            'produits' => ProduitVeterinaire::orderByDesc('actif')->orderBy('nom')->get(),
            'mouvements' => MouvementPharmacie::with(['produit', 'utilisateur'])
                ->latest('date_mouvement')->latest('id')->limit(30)->get(),
            'types' => TypeProduit::cases(),
        ]);
    }

    public function storeProduit(Request $request)
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:100', 'unique:produits_veterinaires,nom'],
            'type' => ['required', Rule::enum(TypeProduit::class)],
            'unite' => ['required', 'string', 'max:20'],
            'seuil_alerte' => ['required', 'numeric', 'min:0', 'max:100000'],
            'delai_attente_jours' => ['required', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], self::ATTRIBUTS);

        ProduitVeterinaire::create($donnees);

        return back()->with('status', 'Produit ajouté. Enregistrez un premier achat pour alimenter le stock.');
    }

    public function updateProduit(Request $request, ProduitVeterinaire $produit)
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:100', Rule::unique('produits_veterinaires', 'nom')->ignore($produit->id)],
            'type' => ['required', Rule::enum(TypeProduit::class)],
            'unite' => ['required', 'string', 'max:20'],
            'seuil_alerte' => ['required', 'numeric', 'min:0', 'max:100000'],
            'delai_attente_jours' => ['required', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], self::ATTRIBUTS);

        $produit->update([...$donnees, 'actif' => $request->boolean('actif')]);

        return back()->with('status', 'Produit modifié.');
    }

    public function achat(Request $request, ProduitVeterinaire $produit, Pharmacie $pharmacie)
    {
        $donnees = $request->validate([
            'quantite' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'date_achat' => ['required', 'date', 'before_or_equal:today'],
            'fournisseur' => ['nullable', 'string', 'max:100'],
            'cout_total' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ], [], self::ATTRIBUTS);

        $pharmacie->entrer(
            $produit,
            (float) $donnees['quantite'],
            Carbon::parse($donnees['date_achat']),
            $donnees['fournisseur'] ?? null,
            isset($donnees['cout_total']) ? (float) $donnees['cout_total'] : null,
            $request->user(),
        );

        return back()->with('status', "Achat enregistré : le stock de {$produit->nom} est mis à jour.");
    }

    public function ajustement(Request $request, ProduitVeterinaire $produit, Pharmacie $pharmacie)
    {
        $donnees = $request->validate([
            'quantite' => ['required', 'numeric', 'not_in:0', 'between:-100000,100000'],
            'motif' => ['required', 'string', 'min:3', 'max:200'],
        ], [], self::ATTRIBUTS);

        $pharmacie->ajuster($produit, (float) $donnees['quantite'], $donnees['motif'], $request->user());

        return back()->with('status', 'Inventaire corrigé.');
    }
}