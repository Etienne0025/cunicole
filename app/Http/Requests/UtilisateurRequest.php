<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // l'accès est déjà contrôlé par le middleware de rôle
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'nom' => trim((string) $this->input('nom')),
            'prenom' => trim((string) $this->input('prenom')),
        ]);
    }

    public function rules(): array
    {
        $utilisateur = $this->route('utilisateur');

        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('utilisateurs', 'email')->ignore($utilisateur?->id)],
            'telephone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'nom' => 'nom',
            'prenom' => 'prénom',
            'email' => 'adresse email',
            'telephone' => 'téléphone',
            'role' => 'rôle',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Un compte existe déjà avec cette adresse email.',
        ];
    }
}