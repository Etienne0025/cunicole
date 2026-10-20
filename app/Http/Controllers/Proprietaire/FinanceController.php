<?php

namespace App\Http\Controllers\Proprietaire;

use App\Http\Controllers\Controller;
use App\Services\BilanFinancier;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function index(Request $request, BilanFinancier $bilan)
    {
        $periode = BilanFinancier::resoudrePeriode($request->input('periode'));

        return view('proprietaire.finances.index', [
            'periode' => $periode,
            'bilan' => $bilan->periode($periode['debut'], $periode['fin']),
            'serie' => $bilan->serieMensuelle(6),
            'objectifs' => $bilan->progressionObjectifs(),
            'creances' => $bilan->creances(),
        ]);
    }
}