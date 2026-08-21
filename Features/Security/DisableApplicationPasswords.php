<?php
/**
 * Désactivation des mots de passe d'application
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Security;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Désactive entièrement les mots de passe d'application.
 *
 * Introduits en WordPress 5.6, ils permettent à un logiciel externe (application mobile,
 * client de publication, intégration tierce) de s'authentifier à l'API REST avec un jeton
 * dédié, sans jamais transmettre le mot de passe du compte. Utile pour qui pilote son site à
 * distance — sans usage sur ce projet, qui n'expose aucune intégration de ce type.
 *
 * Faute d'usage, c'est une surface d'authentification en trop : un canal de connexion à l'API
 * parallèle au formulaire de `wp-login.php`, donc hors de portée des protections posées
 * dessus. Le filtre `wp_is_application_passwords_available` (renvoyé à `false`) coupe tout
 * d'un coup : le mécanisme d'authentification, ses routes REST, et le bloc « Mots de passe
 * d'application » de la page de profil.
 *
 * À rétablir si un jour une application externe légitime doit se connecter à l'API.
 */
final class DisableApplicationPasswords implements FeatureInterface {

    /** Enregistre la désactivation des mots de passe d'application. */
    public function register(): void {
        add_filter( 'wp_is_application_passwords_available', '__return_false' );
    }
}
