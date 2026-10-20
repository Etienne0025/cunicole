@use('App\Enums\Role')

<x-layouts.proprietaire title="Comptes utilisateurs">

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if (session('identifiants'))
        @php $identifiants = session('identifiants'); @endphp
        <div class="mb-6 rounded-lg border-2 border-emerald-400 bg-emerald-50 p-5">
            <h2 class="font-semibold text-emerald-900">Identifiants à transmettre à {{ $identifiants['nom'] }}</h2>
            <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                <div>
                    <dt class="text-gray-600">Adresse email (identifiant)</dt>
                    <dd class="font-mono text-base">{{ $identifiants['email'] }}</dd>
                </div>
                <div>
                    <dt class="text-gray-600">Mot de passe provisoire</dt>
                    <dd class="font-mono text-lg font-bold">{{ $identifiants['mot_de_passe'] }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-emerald-900">
                Notez-le maintenant : il ne sera plus jamais affiché. Transmettez-le directement à la personne.
                Elle devra choisir son propre mot de passe à sa première connexion.
            </p>
        </div>
    @endif

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Gestionnaires actifs</div>
            <div class="mt-1 text-3xl font-bold">{{ $gestionnairesActifs }}</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Propriétaires actifs</div>
            <div class="mt-1 text-3xl font-bold">{{ $proprietairesActifs }}</div>
        </div>
        <div class="flex items-center">
            <a href="{{ route('proprietaire.utilisateurs.create') }}" class="w-full rounded bg-emerald-600 py-3 text-center text-white">+ Nouveau compte</a>
        </div>
    </div>

    <div class="mb-6 overflow-x-auto rounded-lg bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Nom</th>
                    <th class="px-4 py-2">Rôle</th>
                    <th class="px-4 py-2">Statut</th>
                    <th class="px-4 py-2">Dernière connexion</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($utilisateurs as $compte)
                    <tr class="border-t {{ $compte->actif ? '' : 'bg-gray-50 text-gray-500' }}">
                        <td class="px-4 py-2">
                            <div class="font-medium">{{ $compte->nom_complet }}@if ($compte->is(auth()->user())) <span class="text-xs font-normal text-gray-500">(vous)</span>@endif</div>
                            <div class="text-xs text-gray-500">{{ $compte->email }}@if ($compte->telephone) · {{ $compte->telephone }}@endif</div>
                        </td>
                        <td class="px-4 py-2">{{ $compte->role->libelle() }}</td>
                        <td class="px-4 py-2">
                            @if (! $compte->actif)
                                <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700">Désactivé</span>
                            @elseif ($compte->doit_changer_mot_de_passe)
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Mot de passe provisoire</span>
                            @else
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Actif</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $compte->derniere_connexion_le?->diffForHumans() ?? 'Jamais' }}</td>
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <a class="text-blue-600 underline" href="{{ route('proprietaire.utilisateurs.edit', $compte) }}">Modifier</a>

                                @unless ($compte->is(auth()->user()))
                                    @if ($compte->actif)
                                        <form method="POST" action="{{ route('proprietaire.utilisateurs.desactiver', $compte) }}"
                                              onsubmit="return confirm('Désactiver ce compte ? La personne sera déconnectée immédiatement.')">
                                            @csrf
                                            <button class="text-red-600 underline">Désactiver</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('proprietaire.utilisateurs.reactiver', $compte) }}">
                                            @csrf
                                            <button class="text-emerald-700 underline">Réactiver</button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('proprietaire.utilisateurs.mot_de_passe', $compte) }}"
                                          onsubmit="return confirm('Générer un nouveau mot de passe provisoire ? L\'ancien ne fonctionnera plus.')">
                                        @csrf
                                        <button class="text-amber-700 underline">Réinitialiser le mot de passe</button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div x-data="{ ouvert: false }" class="rounded-lg bg-white p-5 shadow">
        <button type="button" @click="ouvert = !ouvert" class="font-semibold text-slate-800">Droits d'accès de chaque rôle</button>

        <div x-show="ouvert" x-cloak class="mt-3 grid gap-6 text-sm md:grid-cols-2">
            <div>
                <h3 class="mb-1 font-semibold">{{ Role::Gestionnaire->libelle() }} (espace terrain, mobile)</h3>
                <ul class="list-disc space-y-1 pl-5 text-gray-700">
                    <li>Bâtiments, cages et QR codes, scan des cages</li>
                    <li>Saillies, palpations, mises bas, sevrages, lots, conversions en reproducteur</li>
                    <li>Pesées, vaccinations faites, traitements, déclaration des décès</li>
                    <li>Distribution d'aliment et réception des sacs</li>
                    <li>Ventes, encaissements, dépenses courantes</li>
                    <li>Checklist du jour et clôture de journée</li>
                </ul>
            </div>
            <div>
                <h3 class="mb-1 font-semibold">{{ Role::Proprietaire->libelle() }} (espace supervision, web)</h3>
                <ul class="list-disc space-y-1 pl-5 text-gray-700">
                    <li>Consultation de toutes les données et de tous les rapports</li>
                    <li>Réglages : tarifs, rations, protocoles vaccinaux, pharmacie, critères de sélection, objectifs</li>
                    <li>Validation des nouveaux reproducteurs</li>
                    <li>Finances : charges fixes, créances, bénéfice net</li>
                    <li>Gestion des comptes et des accès</li>
                </ul>
            </div>
            <p class="text-xs text-gray-500 md:col-span-2">
                Les deux espaces sont séparés : un propriétaire n'ouvre pas les écrans de saisie du gestionnaire, et inversement.
                Un compte désactivé perd tout accès, immédiatement.
            </p>
        </div>
    </div>
</x-layouts.proprietaire>