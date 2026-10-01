<?php
/**
 * Suppression du lien « Politique de confidentialité » au login
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Retire le lien vers la politique de confidentialité que le core imprime au pied de
 * `wp-login.php` (connexion, mot de passe oublié…), via `the_privacy_policy_link()`
 * dans `login_footer()`.
 *
 * Le filtre `the_privacy_policy_link` n'est posé que sur `login_init`, qui ne se
 * déclenche que dans `wp-login.php` : un appel à `the_privacy_policy_link()` ailleurs
 * (pied de page du front, par exemple) garde son lien.
 *
 * Ce lien informe des traitements de données avant leur saisie (RGPD, art. 13). Son
 * retrait suppose une page de connexion réservée à l'équipe : à ne pas déclarer si
 * l'inscription est ouverte (`users_can_register`) ou si des personnes extérieures
 * se connectent.
 */
final class DisableLoginPrivacyPolicyLink implements FeatureInterface {

    /** Enregistre le retrait du lien, sur la seule page de connexion. */
    public function register(): void {
        add_action( 'login_init', [ $this, 'hide_link' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Retire le lien « Politique de confidentialité » du pied de la page de connexion.', 'wp-theme-core' );
    }

    /** Vide le lien imprimé par `the_privacy_policy_link()`. */
    public function hide_link(): void {
        add_filter( 'the_privacy_policy_link', '__return_empty_string' );
    }
}
