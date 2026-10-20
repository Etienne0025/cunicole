<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AlerteMortalite extends Notification
{
    public function __construct(public array $etat) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Alerte mortalité : {$this->etat['taux']} % sur {$this->etat['jours']} jours (seuil : {$this->etat['seuil']} %, {$this->etat['deces']} décès).",
            'taux' => $this->etat['taux'],
            'seuil' => $this->etat['seuil'],
        ];
    }
}