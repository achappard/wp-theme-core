<?php
/**
 * Désactivation de la publication par e-mail
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Désactive la publication d'articles par e-mail (« Publier par e-mail »).
 *
 * Cette fonction héritée du core relève une boîte POP3 depuis `wp-mail.php` et publie chaque
 * message reçu comme un article : HTML brut, hors blocs, sans relecture si l'expéditeur
 * correspond à un compte habilité. Elle n'a pas d'usage sur un thème construit à l'éditeur de
 * blocs. Elle stocke aussi le mot de passe de la boîte en clair dans `wp_options`, et laisse
 * l'endpoint `wp-mail.php` joignable par tous.
 *
 * Le filtre `enable_post_by_email_configuration` suffit à tout couper : le core le consulte pour
 * afficher la section de *Réglages › Écriture* et son onglet d'aide, pour autoriser
 * l'enregistrement des options `mailserver_*` (`options.php`), et en tête de `wp-mail.php`, qui
 * répond alors 403 avant toute connexion. C'est d'ailleurs le réglage que le core applique
 * lui-même en multisite (`ms-default-filters.php`).
 *
 * Les options `mailserver_*` déjà enregistrées restent en base, simplement inutilisées.
 */
final class DisablePostByEmail implements FeatureInterface {

    /** Enregistre le filtre de désactivation. */
    public function register(): void {
        add_filter( 'enable_post_by_email_configuration', '__return_false' );
    }
}
