<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\UtilisateurRequest;
use App\Models\Utilisateur;
use App\Services\GestionComptes;
use Illuminate\Http\Request;

class UtilisateurController extends Controller
{
    public function index()
    {
        $utilisateurs = Utilisateur::orderByDesc('actif')->orderByDesc('role')->orderBy('nom')->get();

        return view('proprietaire.utilisateurs.index', [
            'utilisateurs' => $utilisateurs,
            'gestionnairesActifs' => $utilisateurs->filter(fn (Utilisateur $u) => $u->actif && $u->role === Role::Gestionnaire)->count(),
            'proprietairesActifs' => $utilisateurs->filter(fn (Utilisateur $u) => $u->actif && $u->role === Role::Proprietaire)->count(),
        ]);
    }

    public function create()
    {
        return view('proprietaire.utilisateurs.create', ['utilisateur' => null, 'roles' => Role::cases()]);
    }

    public function store(UtilisateurRequest $request, GestionComptes $comptes)
    {
        [$utilisateur, $motDePasse] = $comptes->creer($request->validated());

        return redirect()->route('proprietaire.utilisateurs.index')
            ->with('status', 'Compte créé.')
            ->with('identifiants', [
                'nom' => $utilisateur->nom_complet,
                'email' => $utilisateur->email,
                'mot_de_passe' => $motDePasse,
            ]);
    }

    public function edit(Utilisateur $utilisateur)
    {
        return view('proprietaire.utilisateurs.edit', ['utilisateur' => $utilisateur, 'roles' => Role::cases()]);
    }

    public function update(UtilisateurRequest $request, Utilisateur $utilisateur, GestionComptes $comptes)
    {
        $comptes->modifier($utilisateur, $request->validated(), $request->user());

        return redirect()->route('proprietaire.utilisateurs.index')->with('status', 'Compte modifié.');
    }

    public function desactiver(Request $request, Utilisateur $utilisateur, GestionComptes $comptes)
    {
        $comptes->desactiver($utilisateur, $request->user());

        return back()->with('status', "Le compte de {$utilisateur->nom_complet} est désactivé : il est déconnecté et ne peut plus se connecter.");
    }

    public function reactiver(Utilisateur $utilisateur, GestionComptes $comptes)
    {
        $comptes->reactiver($utilisateur);

        return back()->with('status', "Le compte de {$utilisateur->nom_complet} est réactivé.");
    }

    public function motDePasse(Request $request, Utilisateur $utilisateur, GestionComptes $comptes)
    {
        $motDePasse = $comptes->reinitialiserMotDePasse($utilisateur, $request->user());

        return redirect()->route('proprietaire.utilisateurs.index')
            ->with('status', 'Mot de passe réinitialisé. Le compte est déconnecté de tous ses appareils.')
            ->with('identifiants', [
                'nom' => $utilisateur->nom_complet,
                'email' => $utilisateur->email,
                'mot_de_passe' => $motDePasse,
            ]);
    }
}