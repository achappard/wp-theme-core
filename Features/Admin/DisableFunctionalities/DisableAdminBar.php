<?php

declare(strict_types=1);

namespace Theme\Features\Admin\DisableFunctionalities;

use Theme\Contracts\FeatureInterface;
use WP_Admin_Bar;

/** Masque la barre d'administration WordPress côté front et supprime le logo WP dans l'admin. */
class DisableAdminBar implements FeatureInterface
{
    /** Enregistre les hooks nécessaires à la désactivation de la barre d'administration. */
    public function register(): void
    {
        add_action('after_setup_theme', [$this, 'disableAdminBar']);
        add_action('admin_bar_menu', [$this, 'removeWpLogo'], 999);
        add_filter('show_admin_bar', '__return_false');
        add_action('admin_print_styles-profile.php', [$this, 'hideAdminBarProfileOption']);
    }

    /** Désactive la barre d'administration pour tous les visiteurs côté front. */
    public function disableAdminBar(): void
    {
        show_admin_bar(false);
    }

    /** Supprime le logo WordPress et son menu déroulant de la barre d'administration. */
    public function removeWpLogo(WP_Admin_Bar $wpAdminBar): void
    {
        $wpAdminBar->remove_node('wp-logo');
    }

    /** Injecte un style inline pour masquer l'option "Barre d'outils" dans le profil utilisateur. */
    public function hideAdminBarProfileOption(): void
    {
        echo '<style>.show-admin-bar { display: none !important; }</style>';
    }
}
