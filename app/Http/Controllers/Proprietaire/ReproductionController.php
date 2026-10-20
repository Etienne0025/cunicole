<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\SexeAnimal;
use App\Enums\StatutSaillie;
use App\Http\Controllers\Controller;
use App\Models\BlocageConsanguinite;
use App\Models\ObjectifNaissance;
use App\Models\Portee;
use App\Models\Reproducteur;
use App\Models\Saillie;
use App\Services\PlanningElevage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReproductionController extends Controller
{
    public function index(PlanningElevage $planning)
    {
        $maintenant = today();

        $objectif = ObjectifNaissance::where('annee', $maintenant->year)
            ->where('mois', $maintenant->month)
            ->value('objectif');

        $naissances = (int) Portee::whereBetween('date_mise_bas', [
            $maintenant->copy()->startOfMonth()->toDateString(),
            $maintenant->copy()->endOfMonth()->toDateString(),
        ])->sum('nes_vivants');

        // Taux d'échec des saillies sur les 90 derniers jours
        $depuis = $maintenant->copy()->subDays(90)->toDateString();
        $palpees = Saillie::whereDate('date_saillie', '>=', $depuis)
    ->whereNotIn('statut', [StatutSaillie::EnAttente->value, StatutSaillie::Interrompue->value])->count();
        $echecs = Saillie::whereDate('date_saillie', '>=', $depuis)
            ->where('statut', StatutSaillie::NonGestante->value)->count();

        $tauxEchec = $palpees > 0 ? round($echecs / $palpees * 100, 1) : null;
        $alerteEchec = $tauxEchec !== null
            && $palpees >= config('cunicole.reproduction.echantillon_min_echec')
            && $tauxEchec > config('cunicole.reproduction.seuil_echec_saillies');

        $prolificite = DB::table('portees')
            ->join('saillies', 'saillies.id', '=', 'portees.saillie_id')
            ->join('reproducteurs', 'reproducteurs.id', '=', 'saillies.femelle_id')
            ->selectRaw('reproducteurs.id, reproducteurs.matricule, count(*) as nb_portees, avg(portees.nes_vivants) as moyenne, sum(portees.nes_vivants) as vivants, sum(portees.morts_nes) as morts_nes')
            ->groupBy('reproducteurs.id', 'reproducteurs.matricule')
            ->orderBy('reproducteurs.matricule')
            ->get();

        return view('proprietaire.reproduction.index', [
            'objectif' => $objectif,
            'naissances' => $naissances,
            'misesBasAttendues' => $planning->misesBasAttendues(7),
            'tauxEchec' => $tauxEchec,
            'alerteEchec' => $alerteEchec,
            'seuil' => config('cunicole.reproduction.seuil_echec_saillies'),
            'saillesEnCours' => Saillie::whereIn('statut', StatutSaillie::valeursEnCours())->count(),
            'prolificite' => $prolificite,
            'blocages' => BlocageConsanguinite::with(['femelle', 'male', 'utilisateur'])->latest()->limit(20)->get(),
        ]);
    }

    public function objectif(Request $request)
    {
        $donnees = $request->validate([
            'objectif' => ['required', 'integer', 'min:0', 'max:5000'],
        ], [], ['objectif' => 'objectif de naissances']);

        ObjectifNaissance::updateOrCreate(
            ['annee' => today()->year, 'mois' => today()->month],
            ['objectif' => $donnees['objectif']],
        );

        return back()->with('status', 'Objectif du mois enregistré.');
    }

    public function femelle(Reproducteur $reproducteur)
    {
        abort_unless($reproducteur->sexe === SexeAnimal::Femelle, 404);

        $portees = Portee::with('saillie.male')
            ->whereHas('saillie', fn ($q) => $q->where('femelle_id', $reproducteur->id))
            ->orderBy('date_mise_bas')
            ->get();

        return view('proprietaire.reproduction.femelle', [
            'femelle' => $reproducteur,
            'portees' => $portees,
        ]);
    }
}