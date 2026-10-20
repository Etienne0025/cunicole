<?php

namespace App\Http\Controllers\Proprietaire;

use App\Http\Controllers\Controller;
use App\Services\TableauDeBord;
use Illuminate\Http\Request;

class TableauDeBordController extends Controller
{
    public function __invoke(Request $request, TableauDeBord $tableau)
    {
        $donnees = $tableau->donnees();

        return view('proprietaire.tableau-de-bord', [
            'd' => $donnees,
            'alertes' => $tableau->alertes($donnees),
            'notifications' => $request->user()->unreadNotifications()->limit(5)->get(),
        ]);
    }

    public function notificationsLues(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Notifications marquées comme lues.');
    }
}