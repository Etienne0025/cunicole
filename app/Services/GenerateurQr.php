<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class GenerateurQr
{
    public function svg(string $contenu, int $taille = 200): string
    {
        $rendu = new ImageRenderer(new RendererStyle($taille, 1), new SvgImageBackEnd());
        $svg = (new Writer($rendu))->writeString($contenu);

        // On retire l'en-tête XML pour pouvoir insérer le SVG dans une page HTML
        return trim(preg_replace('/^<\?xml.*?\?>/s', '', $svg));
    }

    public function svgBase64(string $contenu, int $taille = 200): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($contenu, $taille));
    }
}