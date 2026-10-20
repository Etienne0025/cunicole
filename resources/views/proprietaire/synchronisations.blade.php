<x-layouts.proprietaire title="Saisies hors ligne refusées">

    <div class="mb-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Saisies hors ligne envoyées (7 jours)</div>
            <div class="mt-1 text-3xl font-bold">{{ $appliquees7j }}</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Saisies refusées (7 jours)</div>
            <div class="mt-1 text-3xl font-bold {{ $refusees7j > 0 ? 'text-red-600' : '' }}">{{ $refusees7j }}</div>
        </div>
    </div>

    <p class="mb-4 text-sm text-gray-600">
        Une saisie faite sans réseau est contrôlée par le serveur au moment de son envoi. Quand une règle la refuse
        (stock insuffisant, lot déjà vendu, animal bloqué pour la vente...), elle n'est <strong>pas appliquée</strong> :
        elle apparaît ici avec le motif et les données saisies, pour que le gestionnaire la corrige ou la ressaisisse.
    </p>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Saisie faite le</th>
                    <th class="px-4 py-2">Gestionnaire</th>
                    <th class="px-4 py-2">Saisie</th>
                    <th class="px-4 py-2">Motif du refus</th>
                    <th class="px-4 py-2">Données</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($refusees as $action)
                    <tr class="border-t align-top">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $action->effectuee_le->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2">{{ $action->utilisateur?->nom_complet ?? '-' }}</td>
                        <td class="px-4 py-2">
                            {{ $action->type }}
                            <div class="text-xs text-gray-500">{{ $action->libelle }}</div>
                        </td>
                        <td class="px-4 py-2 text-red-700">{{ $action->message }}</td>
                        <td class="px-4 py-2">
                            <details>
                                <summary class="cursor-pointer text-blue-600 underline">Voir</summary>
                                <pre class="mt-1 max-w-xs whitespace-pre-wrap break-all text-xs">{{ json_encode($action->payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Aucune saisie refusée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $refusees->links() }}</div>
</x-layouts.proprietaire>