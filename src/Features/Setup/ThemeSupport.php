<?php
/**
 * Supports de thème déclarés auprès de WordPress
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Setup;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Déclare les fonctionnalités WordPress prises en charge par le thème.
 *
 * Socle structurel uniquement à ce stade. Les supports liés à l'éditeur de blocs
 * (`responsive-embeds`, `align-wide`, `editor-styles`, `appearance-tools`) et le
 * `custom-logo` restent à poser quand le design system sera défini.
 */
final class ThemeSupport implements FeatureInterface {

    /**
     * Éléments dont le balisage est délégué à WordPress en HTML5.
     *
     * Sans ce support, le core continue de produire du balisage XHTML pour le
     * formulaire de recherche, les commentaires, les légendes et les galeries.
     * `gallery` a un effet supplémentaire : il coupe la feuille de style inline
     * que le core injecte sinon avec chaque galerie.
     *
     * `style` et `script` ne figurent volontairement pas dans cette liste : leurs
     * arguments sont dépréciés et sans effet depuis WordPress 7.0, le balisage
     * correspondant étant désormais inconditionnel (cf. wp-includes/theme.php,
     * docbloc de add_theme_support()).
     */
    private const HTML5_FEATURES = [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'navigation-widgets',
    ];

    /** Enregistre la déclaration des supports de thème. */
    public function register(): void {
        add_action( 'after_setup_theme', [ $this, 'add_theme_supports' ] );
    }

    /**
     * Déclare les supports auprès de WordPress.
     *
     * `after_setup_theme` est le hook attendu : `title-tag` doit être connu
     * avant que `wp_head()` ne construise le document, et les supports posés
     * plus tard seraient ignorés.
     */
    public function add_theme_supports(): void {
        // Génération du <title> par WordPress (_wp_render_title_tag sur wp_head).
        add_theme_support( 'title-tag' );

        // Liens <link rel="alternate"> vers les flux du site dans le <head>.
        add_theme_support( 'automatic-feed-links' );

        // Balisage HTML5 pour les éléments produits par le core.
        add_theme_support( 'html5', self::HTML5_FEATURES );

        // Images à la une (get_the_post_thumbnail, post.thumbnail côté Timber).
        add_theme_support( 'post-thumbnails' );
    }
}
