<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AlerteStockAliment extends Notification
{
    /** @param array{aliment: \App\Models\Aliment, consommation: float, autonomie: ?float, bas: bool} $alerte */
    public function __construct(public array $alerte) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $aliment = $this->alerte['aliment'];
        $autonomie = $this->alerte['autonomie'];
        $seuil = config('cunicole.alimentation.jours_autonomie_alerte', 3);
        $stock = number_format((float) $aliment->stock_kg, 2, ',', ' ');

        $reste = ($autonomie !== null && $autonomie > 0)
            ? number_format($autonomie, 1, ',', ' ')." jour(s) d'autonomie"
            : 'stock épuisé';

        return [
            'message' => "Stock faible : {$aliment->nom}, {$reste} (seuil : {$seuil} jours, {$stock} kg restants).",
            'aliment_id' => $aliment->id,
            'autonomie' => $autonomie,
        ];
    }
}