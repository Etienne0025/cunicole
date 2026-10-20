<?php

namespace App\Http\Requests;

use App\Enums\OrigineReproducteur;
use App\Enums\SexeAnimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReproducteurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // l'accès est déjà contrôlé par le middleware de rôle
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['matricule' => Str::upper(trim((string) $this->input('matricule')))]);
    }

    public function rules(): array
    {
        $reproducteur = $this->route('reproducteur');
        $creation = $reproducteur === null;

        return [
            'matricule' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9\-\/]+$/',
                Rule::unique('reproducteurs', 'matricule')->ignore($reproducteur?->id),
            ],
            'nom' => ['nullable', 'string', 'max:50'],
            // Le sexe ne se modifie pas après la création (il détermine les cages autorisées)
            'sexe' => $creation ? ['required', Rule::enum(SexeAnimal::class)] : ['prohibited'],
            'race' => ['nullable', 'string', 'max:50'],
            'date_naissance' => ['required', 'date', 'before_or_equal:today'],
            'origine' => ['required', Rule::enum(OrigineReproducteur::class)],
            'date_entree' => ['required', 'date', 'after_or_equal:date_naissance', 'before_or_equal:today'],
            'mere_id' => [
                'nullable',
                Rule::exists('reproducteurs', 'id')->where('sexe', 'femelle'),
                ...($reproducteur ? [Rule::notIn([$reproducteur->id])] : []),
            ],
            'pere_id' => [
                'nullable',
                Rule::exists('reproducteurs', 'id')->where('sexe', 'male'),
                ...($reproducteur ? [Rule::notIn([$reproducteur->id])] : []),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'matricule' => 'matricule (tatouage)',
            'nom' => 'nom',
            'sexe' => 'sexe',
            'race' => 'race',
            'date_naissance' => 'date de naissance',
            'origine' => 'origine',
            'date_entree' => "date d'entrée",
            'mere_id' => 'mère',
            'pere_id' => 'père',
        ];
    }

    public function messages(): array
    {
        return [
            'matricule.regex' => 'Le matricule ne doit contenir que des lettres, chiffres, tirets ou barres obliques.',
            'matricule.unique' => 'Ce matricule est déjà attribué à un autre animal.',
        ];
    }
}