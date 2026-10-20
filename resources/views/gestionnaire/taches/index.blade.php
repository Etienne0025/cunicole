@use('App\Enums\StatutTache')

<x-layouts.gestionnaire title="Tâches du jour">

    @foreach ($joursNonClotures as $jourPasse)
        <a href="{{ route('gestionnaire.taches.cloture', ['date' => $jourPasse]) }}"
           class="mb-3 block rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            La journée du {{ \Illuminate\Support\Carbon::parse($jourPasse)->format('d/m/Y') }} n'est pas clôturée. Justifiez les tâches restantes →
        </a>
    @endforeach

    <div class="mb-4 rounded-lg bg-white p-4 shadow">
        <div class="mb-1 flex justify-between text-sm">
            <span>{{ today()->translatedFormat('l d F') }}</span>
            <span class="font-semibold">{{ $progression['faites'] }} / {{ $progression['total'] }} faites</span>
        </div>
        <div class="h-3 rounded bg-gray-200">
            <div class="h-3 rounded bg-emerald-500" style="width: {{ $progression['pourcentage'] ?? 0 }}%"></div>
        </div>
        @if ($cloturee)
            <p class="mt-2 text-sm text-emerald-700">Journée clôturée.</p>
        @endif
    </div>

    <div class="space-y-2">
        @forelse ($taches as $tache)
            @php $faite = $tache->statut === StatutTache::Faite; @endphp

            <div class="rounded-lg bg-white p-3 shadow {{ $faite ? 'opacity-70' : '' }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-medium {{ $faite ? 'line-through' : '' }}">{{ $tache->titre }}</div>
                        @if ($tache->description)
                            <div class="text-xs text-gray-500">{{ $tache->description }}</div>
                        @endif

                        @if ($faite)
                            <div class="mt-1 text-xs text-emerald-700">
                                ✔ à {{ $tache->faite_le->format('H:i') }}{{ $tache->automatique ? ' (enregistrée automatiquement)' : '' }}
                            </div>
                        @elseif ($tache->statut === StatutTache::NonFaite)
                            <div class="mt-1 text-xs text-red-700">Non faite : {{ $tache->justification }}</div>
                        @endif
                    </div>

                    @unless ($cloturee)
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            @if ($tache->statut === StatutTache::AFaire)
                               @if ($tache->type->boutonFait())
                                   <form method="POST" action="{{ route('gestionnaire.taches.cocher', $tache) }}"
      @if ($tache->type->cochableManuellement())
          data-hors-ligne="tache_cocher" data-cle="tache:{{ $tache->id }}" data-apres="masquer"
          data-libelle="{{ 'Tâche faite : '.$tache->titre }}"
      @endif>
    @csrf
    <input type="hidden" name="tache_id" value="{{ $tache->id }}">
                                       {{ $tache->type === \App\Enums\TypeTache::Vaccination ? 'Vaccin fait' : 'Marquer comme faite' }}
                                    </form>
                                @endif
                                @if ($lien = $tache->lien())
                                    <a href="{{ $lien }}" class="rounded border px-3 py-2 text-sm">Ouvrir</a>
                                @endif
                            @elseif ($faite && ! $tache->automatique)
                                <form method="POST" action="{{ route('gestionnaire.taches.decocher', $tache) }}">
                                    @csrf
                                    <button class="text-xs text-gray-500 underline">Annuler</button>
                                </form>
                            @endif
                        </div>
                    @endunless
                </div>
            </div>
        @empty
            <p class="rounded-lg bg-white p-4 text-sm text-gray-500 shadow">Aucune tâche aujourd'hui.</p>
        @endforelse
    </div>

    @unless ($cloturee)
        <a href="{{ route('gestionnaire.taches.cloture') }}" class="mt-4 block rounded bg-slate-800 py-3 text-center text-white">
            Clôturer la journée
        </a>
    @endunless
</x-layouts.gestionnaire>