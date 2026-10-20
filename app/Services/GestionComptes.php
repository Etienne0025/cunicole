<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GestionComptes
{
    // Sans caractères ambigus (0/O, 1/l/I) pour faciliter la dictée du mot de passe
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    /**
     * @param  array{nom: string, prenom: string, email: string, telephone?: ?string, role: string}  $donnees
     * @return array{0: Utilisateur, 1: string} le compte et son mot de passe provisoire (à afficher une seule fois)
     */
    public function creer(array $donnees): array
    {
        $motDePasse = $this->genererMotDePasse();

        $utilisateur = new Utilisateur([
            'nom' => $donnees['nom'],
            'prenom' => $donnees['prenom'],
            'email' => $donnees['email'],
            'telephone' => $donnees['telephone'] ?? null,
            'role' => $donnees['role'],
            'password' => $motDePasse,
            'actif' => true,
            'doit_changer_mot_de_passe' => true,
        ]);
        $utilisateur->email_verified_at = now();
        $utilisateur->save();

        return [$utilisateur, $motDePasse];
    }

    public function modifier(Utilisateur $utilisateur, array $donnees, Utilisateur $par): Utilisateur
    {
        $role = Role::from($donnees['role']);

        if ($role !== $utilisateur->role) {
            if ($utilisateur->is($par)) {
                $this->refuser('role', 'Vous ne pouvez pas modifier votre propre rôle.');
            }
            if ($this->estDernierProprietaireActif($utilisateur)) {
                $this->refuser('role', 'Il doit toujours rester au moins un propriétaire actif.');
            }
        }

        $utilisateur->update([
            'nom' => $donnees['nom'],
            'prenom' => $donnees['prenom'],
            'email' => $donnees['email'],
            'telephone' => $donnees['telephone'] ?? null,
            'role' => $role,
        ]);

        return $utilisateur;
    }

    public function desactiver(Utilisateur $utilisateur, Utilisateur $par): void
    {
        if ($utilisateur->is($par)) {
            $this->refuser('action', 'Vous ne pouvez pas désactiver votre propre compte.');
        }
        if ($this->estDernierProprietaireActif($utilisateur)) {
            $this->refuser('action', 'Il doit toujours rester au moins un propriétaire actif.');
        }
        if (! $utilisateur->actif) {
            return;
        }

        $utilisateur->update(['actif' => false]);
        $this->fermerSessions($utilisateur);
    }

    public function reactiver(Utilisateur $utilisateur): void
    {
        $utilisateur->update(['actif' => true]);
    }

    /** Génère un nouveau mot de passe provisoire et ferme toutes les sessions ouvertes du compte. */
    public function reinitialiserMotDePasse(Utilisateur $utilisateur, ?Utilisateur $par = null): string
    {
        if ($par !== null && $utilisateur->is($par)) {
            $this->refuser('action', 'Pour changer votre propre mot de passe, utilisez la page « Mon compte ».');
        }

        $motDePasse = $this->genererMotDePasse();

        $utilisateur->forceFill([
            'password' => $motDePasse,
            'doit_changer_mot_de_passe' => true,
        ])->save();

        $this->fermerSessions($utilisateur);

        return $motDePasse;
    }

    /** Au moins une minuscule, une majuscule et un chiffre. */
    public function genererMotDePasse(int $longueur = 10): string
    {
        do {
            $mot = '';

            for ($i = 0; $i < $longueur; $i++) {
                $mot .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (! preg_match('/[a-z]/', $mot) || ! preg_match('/[A-Z]/', $mot) || ! preg_match('/\d/', $mot));

        return $mot;
    }

    private function estDernierProprietaireActif(Utilisateur $utilisateur): bool
    {
        return $utilisateur->estProprietaire()
            && $utilisateur->actif
            && ! Utilisateur::where('role', Role::Proprietaire->value)
                ->where('actif', true)
                ->whereKeyNot($utilisateur->id)
                ->exists();
    }

    private function fermerSessions(Utilisateur $utilisateur): void
    {
        // Invalide aussi la reconnexion automatique (« se souvenir de moi »)
        $utilisateur->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $utilisateur->id)->delete();
        }
    }

    private function refuser(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}