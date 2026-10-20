<x-layouts.gestionnaire title="Synchronisation">
    <div class="space-y-4">

        <section class="rounded-lg bg-white p-4 shadow">
            <h2 class="font-semibold">Saisies enregistrées sur ce téléphone</h2>
            <p class="mt-1 text-xs text-gray-500">
                Quand il n'y a pas de réseau, vos saisies sont gardées ici puis envoyées automatiquement.
                Le serveur les contrôle à l'arrivée : une saisie peut être refusée (stock insuffisant, lot déjà vendu...).
                Les saisies de plus de {{ $joursMax }} jours sont refusées.
            </p>

            <div id="liste-synchronisation" class="mt-3 space-y-2" aria-live="polite"></div>

            <button id="bouton-synchroniser" type="button" class="mt-3 w-full rounded bg-emerald-600 py-3 text-white disabled:opacity-50">
                Envoyer maintenant
            </button>
        </section>

        <section class="rounded-lg bg-white p-4 shadow">
            <h2 class="font-semibold">Préparer le mode hors ligne</h2>
            <p class="mt-1 text-sm text-gray-600">
                Télécharge sur le téléphone les pages utiles du terrain (tâches, élevage, cages, lots, alimentation, ventes...).
                À faire <strong>avec du réseau</strong>, par exemple le matin avant d'aller aux bâtiments.
                Cela se fait aussi automatiquement toutes les 12 heures.
            </p>
            <button id="bouton-preparer" type="button" class="mt-3 w-full rounded bg-slate-800 py-3 text-white disabled:opacity-50">
                Télécharger les pages pour le mode hors ligne
            </button>
            <p id="etat-preparation" class="mt-2 text-sm text-gray-600"></p>
        </section>

        <section class="rounded-lg bg-white p-4 text-sm shadow">
            <h2 class="mb-2 font-semibold">Ce qui fonctionne sans réseau</h2>
            <ul class="list-disc space-y-1 pl-5 text-gray-700">
                <li>Cocher une tâche, enregistrer la distribution d'aliment, les pesées, les palpations, les boîtes à nids, les mises bas, les décès et les dépenses</li>
                <li>Enregistrer une vente (le reçu PDF n'est disponible qu'après l'envoi)</li>
            </ul>
            <p class="mt-3 text-xs text-gray-500">
                Nécessitent le réseau : création de cages, sevrage, conversion en reproducteur, vaccinations,
                réception de sacs, encaissement d'un solde et clôture de journée.
                Le mode hors ligne exige une connexion sécurisée (HTTPS) sur le serveur de production.
            </p>
        </section>
    </div>
</x-layouts.gestionnaire>