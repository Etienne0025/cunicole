<?php

namespace App\Http\Controllers;

use App\Models\Vente;
use Barryvdh\DomPDF\Facade\Pdf;

class RecuVenteController extends Controller
{
    public function __invoke(Vente $vente)
    {
        $vente->load(['client', 'lignes', 'paiements', 'utilisateur']);

        return Pdf::loadView('ventes.recu', ['vente' => $vente])
            ->setPaper('a5')
            ->stream("recu-{$vente->numero}.pdf");
    }
}