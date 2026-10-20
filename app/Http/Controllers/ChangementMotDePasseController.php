<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ChangementMotDePasseController extends Controller
{
    public function edit(Request $request)
    {
        if (! $request->user()->doit_changer_mot_de_passe) {
            return redirect()->route('accueil');
        }

        return view('auth.changer-mot-de-passe');
    }

    public function update(Request $request)
    {
        $donnees = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ], [
            'password.different' => 'Le nouveau mot de passe doit être différent du mot de passe provisoire.',
        ], [
            'current_password' => 'mot de passe provisoire',
            'password' => 'nouveau mot de passe',
        ]);

        $request->user()->update([
            'password' => $donnees['password'],
            'doit_changer_mot_de_passe' => false,
        ]);

        return redirect()->route('accueil')->with('status', 'Mot de passe enregistré. Bienvenue !');
    }
}