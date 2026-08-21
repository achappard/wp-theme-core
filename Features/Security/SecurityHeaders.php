<?php
/**
 * En-têtes de sécurité HTTP du front-end
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Security;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Ajoute des en-têtes de sécurité HTTP aux réponses publiques.
 *
 * WordPress n'envoie `X-Frame-Options` que sur l'admin (`admin_init`) et la page de connexion
 * (`login_init`) : le front reste encadrable en iframe, donc exposé au clickjacking. `nosniff`
 * et `Referrer-Policy` manquent, eux, partout côté public. Cette feature comble le front — le
 * seul périmètre exposé aux visiteurs — en se branchant sur `send_headers`, tiré par
 * `WP::send_headers()` sur chaque requête front-end.
 *
 * Trois en-têtes, tous des valeurs sûres qui ne cassent aucun usage légitime :
 *
 * - `X-Frame-Options: SAMEORIGIN` — interdit l'inclusion du site dans une iframe tierce
 *   (clickjacking). Même valeur que celle posée par le core sur l'admin.
 * - `X-Content-Type-Options: nosniff` — empêche le navigateur de deviner le type d'une
 *   ressource et de l'exécuter autrement que déclaré.
 * - `Referrer-Policy: strict-origin-when-cross-origin` — limite l'URL complète transmise en
 *   `Referer` lors d'une navigation vers un autre domaine (valeur par défaut des navigateurs
 *   modernes, rendue explicite ici).
 *
 * Volontairement absents : `Content-Security-Policy`, trop dépendant du contenu pour une
 * valeur générique sûre ; `Strict-Transport-Security` (HSTS), qui relève de la configuration
 * serveur (il engage tout le domaine et ses sous-domaines sur HTTPS).
 */
final class SecurityHeaders implements FeatureInterface {

    /**
     * En-têtes ajoutés aux réponses front-end, par nom.
     *
     * @var array<string, string>
     */
    private const HEADERS = [
        'X-Frame-Options'        => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
    ];

    /** Enregistre l'envoi des en-têtes sur les réponses front-end. */
    public function register(): void {
        add_action( 'send_headers', [ $this, 'send' ] );
    }

    /**
     * Émet les en-têtes de sécurité, si l'envoi n'a pas déjà commencé.
     *
     * `send_headers` se déclenche avant toute sortie ; le garde `headers_sent()` protège
     * néanmoins le cas où un autre code aurait déjà commencé à écrire le corps.
     */
    public function send(): void {
        if ( headers_sent() ) {
            return;
        }

        foreach ( self::HEADERS as $name => $value ) {
            header( $name . ': ' . $value );
        }
    }
}
