@php
    $v = function (string $champ, $defaut = null) use ($protocole) {
        if ($protocole === null) {
            return old($champ, $defaut);
        }
        $valeur = $protocole->{$champ};

        return $valeur instanceof \BackedEnum ? $valeur->value : $valeur;
    };
@endphp

<input name="nom" value="{{ $v('nom') }}" placeholder="Nom du protocole" class="rounded border-gray-300" required>

<select name="produit_id" class="rounded border-gray-300" required>
    <option value="">Vaccin...</option>
    @foreach ($vaccins as $vaccin)
        <option value="{{ $vaccin->id }}" @selected($v('produit_id') == $vaccin->id)>{{ $vaccin->nom }}</option>
    @endforeach
</select>

<select name="cible" class="rounded border-gray-300" required>
    @foreach ($cibles as $cible)
        <option value="{{ $cible->value }}" @selected($v('cible') === $cible->value)>{{ $cible->libelle() }}</option>
    @endforeach
</select>

<input type="number" min="0" max="730" name="age_premiere_dose_jours" value="{{ $v('age_premiere_dose_jours', 0) }}"
       placeholder="Âge de la 1re dose (jours)" title="Âge de la première dose, en jours" class="rounded border-gray-300" required>

<input type="number" min="1" max="1095" name="intervalle_rappel_jours" value="{{ $v('intervalle_rappel_jours') }}"
       placeholder="Rappel tous les (jours), vide = une seule dose" title="Laissez vide pour une dose unique" class="rounded border-gray-300">

<input type="number" step="0.01" min="0.01" max="100" name="doses_par_animal" value="{{ $v('doses_par_animal', 1) }}"
       placeholder="Doses par animal" class="rounded border-gray-300" required>

<input name="notes" value="{{ $v('notes') }}" placeholder="Notes (ordonnance, voie d'administration...)" class="rounded border-gray-300 md:col-span-3">