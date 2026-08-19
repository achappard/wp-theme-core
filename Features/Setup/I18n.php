<?php
/**
 * Internationalisation du thème
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Setup;

use Theme\Contracts\FeatureInterface;

/**
 * Enregistre le répertoire des traductions du thème.
 *
 * Les appels i18n du code utilisent le text-domain **littéral** `'mon-theme'`, seule
 * forme que `wp i18n make-pot` sait extraire. La constante ci-dessous doit rester
 * alignée sur ce littéral et sur l'en-tête `Text Domain:` de style.css.
 */
final class I18n implements FeatureInterface {

    /** Text-domain utilisé pour les traductions du thème. */
    private const TEXTDOMAIN = 'mon-theme';

    /** Répertoire des traductions, relatif à la racine du thème. */
    private const LANGUAGES_DIR = '/languages';

    /** Enregistre le chargement des traductions. */
    public function register(): void {
        add_action( 'after_setup_theme', [ $this, 'load_translations' ] );
    }

    /**
     * Enregistre le répertoire des traductions du thème.
     *
     * `get_template_directory()` et non `get_stylesheet_directory()` : le thème
     * est assumé comme thème parent unique (même choix que Front\Assets), un
     * thème enfant ne surchargerait donc pas ces traductions.
     *
     * Le chemin étant interne au thème, WordPress attend des fichiers nommés
     * d'après la seule locale — `languages/fr_FR.mo`, et non `mon-theme-fr_FR.mo`
     * (cf. wp-includes/l10n.php, _load_textdomain_just_in_time()).
     */
    public function load_translations(): void {
        load_theme_textdomain(
            self::TEXTDOMAIN,
            get_template_directory() . self::LANGUAGES_DIR
        );
    }
}
