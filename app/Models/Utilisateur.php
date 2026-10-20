<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Utilisateur extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'utilisateurs';

   protected $fillable = [
    'nom', 'prenom', 'email', 'password', 'role', 'actif', 'telephone',
    'doit_changer_mot_de_passe', 'derniere_connexion_le',
];

    protected $hidden = ['password', 'remember_token'];

   protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => Role::class,
        'actif' => 'boolean',
        'doit_changer_mot_de_passe' => 'boolean',
        'derniere_connexion_le' => 'datetime',
    ];
}

    protected function nomComplet(): Attribute
    {
        return Attribute::get(fn () => "{$this->prenom} {$this->nom}");
    }

    public function estProprietaire(): bool
    {
        return $this->role === Role::Proprietaire;
    }

    public function estGestionnaire(): bool
    {
        return $this->role === Role::Gestionnaire;
    }
}
