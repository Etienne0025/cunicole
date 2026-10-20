<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Enums\CauseMortalite;
use App\Enums\StatutTache;
use App\Enums\TypeTache;
use App\Http\Controllers\Controller;
use App\Models\LotEngraissement;
use App\Models\Mortalite;
use App\Models\Portee;
use App\Models\ProduitVeterinaire;
use App\Models\ProtocoleVaccinal;
use App\Models\Reproducteur;
use App\Models\Tache;
use App\Models\Traitement;
use App\Services\GestionTaches;
use App\Services\SoinsVeterinaires;
use App\Services\SuiviMortalite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SanteController extends Controller
{
    public function index(GestionTaches $gestion)
    {
        $gestion->synchroniser();

        $aujourdhui = today()->toDateString();

        return view('gestionnaire.sante.index', [
            'vaccinations' => Tache::with('source')
                ->where('type', TypeTache::Vaccination->value)
                ->whereDate('date_prevue', $aujourdhui)
                ->where('statut', StatutTache::AFaire->value)
                ->orderBy('titre')->get(),
            'traitements' => Traitement::with('traitable')
                ->where('date_debut', '<=', $aujourdhui)->where('date_fin_traitement', '>=', $aujourdhui)
                ->orderBy('date_fin_attente')->get(),
            'bloques' => Traitement::with('traitable')
                ->where('date_debut', '<=', $aujourdhui)->where('date_fin_attente', '>=', $aujourdhui)
                ->orderBy('date_fin_attente')->get(),
            'deces' => Mortalite::latest('date_deces')->latest('id')->limit(10)->get(),
            'produits' => ProduitVeterinaire::where('actif', true)->orderBy('nom')->get(),
        ]);
    }

    public function traiter(Request $request, SoinsVeterinaires $soins)
    {
        $donnees = $request->validate([
            'cible_type' => ['required', 'in:reproducteur,lot'],
            'cible_id' => ['required', 'integer'],
            'produit_id' => ['nullable', Rule::exists('produits_veterinaires', 'id')
                ->where(fn ($q) => $q->where('actif', true)->whereIn('type', ['traitement', 'autre']))],
            'nom_traitement' => ['nullable', 'required_without:produit_id', 'string', 'max:100'],
            'symptomes' => ['required', 'string', 'min:3', 'max:500'],
            'date_debut' => ['required', 'date', 'before_or_equal:today'],
            'duree_jours' => ['required', 'integer', 'min:1', 'max:60'],
            'delai_attente_jours' => ['required', 'integer', 'min:0', 'max:365'],
            'quantite' => ['nullable', 'required_with:produit_id', 'numeric', 'min:0.01', 'max:100000'],
        ], [], [
            'nom_traitement' => 'nom du traitement',
            'symptomes' => 'symptômes observés',
            'date_debut' => 'date de début',
            'duree_jours' => 'durée du traitement',
            'delai_attente_jours' => "délai d'attente",
            'quantite' => 'quantité utilisée',
            'produit_id' => 'produit',
        ]);

        $traitement = $soins->traiter($this->cible($donnees['cible_type'], (int) $donnees['cible_id']), $donnees, $request->user());

        return back()->with('status', "Traitement enregistré. Bloqué pour la vente jusqu'au {$traitement->date_fin_attente->format('d/m/Y')}.");
    }

    public function vaccinationAnterieure(Request $request, SoinsVeterinaires $soins)
    {
        $donnees = $request->validate([
            'cible_type' => ['required', 'in:reproducteur,lot'],
            'cible_id' => ['required', 'integer'],
            'protocole_id' => ['required', 'exists:protocoles_vaccinaux,id'],
            'date_vaccination' => ['required', 'date', 'before_or_equal:today'],
        ], [], ['protocole_id' => 'protocole', 'date_vaccination' => 'date de vaccination']);

        $soins->vaccinationAnterieure(
            $this->cible($donnees['cible_type'], (int) $donnees['cible_id']),
            ProtocoleVaccinal::findOrFail($donnees['protocole_id']),
            Carbon::parse($donnees['date_vaccination']),
            $request->user(),
        );

        return back()->with('status', 'Vaccination antérieure enregistrée. Le calendrier est mis à jour.');
    }

    public function deces(Request $request, SuiviMortalite $suivi)
    {
        $donnees = $request->validate([
            'cible_type' => ['required', 'in:reproducteur,lot,portee'],
            'cible_id' => ['required', 'integer'],
            'nombre' => ['nullable', 'integer', 'min:1', 'max:100'],
            'date_deces' => ['required', 'date', 'before_or_equal:today'],
            'cause' => ['required', Rule::enum(CauseMortalite::class)],
            'precisions' => ['nullable', 'string', 'max:200'],
        ], [
            'cause.required' => 'Indiquez la cause suspectée du décès.',
        ], [
            'date_deces' => 'date du décès',
            'cause' => 'cause suspectée',
            'nombre' => 'nombre de morts',
        ]);

        $suivi->declarer(
            $this->cible($donnees['cible_type'], (int) $donnees['cible_id']),
            (int) ($donnees['nombre'] ?? 1),
            Carbon::parse($donnees['date_deces']),
            CauseMortalite::from($donnees['cause']),
            $donnees['precisions'] ?? null,
            $request->user(),
        );

        return back()->with('status', 'Décès enregistré. Le propriétaire est prévenu automatiquement si le seuil critique est dépassé.');
    }

    private function cible(string $type, int $id): Model
    {
        $classe = match ($type) {
            'reproducteur' => Reproducteur::class,
            'lot' => LotEngraissement::class,
            'portee' => Portee::class,
        };

        return $classe::findOrFail($id);
    }
}