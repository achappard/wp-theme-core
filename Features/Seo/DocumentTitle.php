<?php
/**
 * Composition du titre du document
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Seo;

use Theme\Contracts\FeatureInterface;

/**
 * Point d'entrée unique pour façonner le `<title>` du site.
 *
 * Seul le séparateur est arrêté à ce stade. La composition des parties reste
 * neutre — `filter_parts()` renvoie la valeur du core telle quelle — en attendant
 * que les règles de titrage soient définies.
 *
 * Prérequis : `add_theme_support( 'title-tag' )`, posé par `Setup\ThemeSupport`.
 * Sans lui, `wp_get_document_title()` n'est jamais appelée et ces filtres non plus.
 *
 * Attention : un plugin SEO (Yoast, Rank Math) court-circuite la construction du
 * titre via `pre_get_document_title` et rendrait cette feature inopérante.
 */
final class DocumentTitle implements FeatureInterface {

    /**
     * Séparateur inséré entre les parties du titre.
     *
     * Étoile pleine (★, U+2605). Ce n'est pas une chaîne de langue mais un choix
     * typographique : il ne passe pas par les fonctions i18n.
     */
    private const SEPARATOR = '★';

    /** Enregistre les filtres de composition du titre. */
    public function register(): void {
        add_filter( 'document_title_separator', [ $this, 'filter_separator' ] );
        add_filter( 'document_title_parts', [ $this, 'filter_parts' ] );
    }

    /**
     * Impose le séparateur du thème à la place du `-` du core.
     *
     * Sans ce filtre, `document_title` passe par `wptexturize`
     * (cf. wp-includes/default-filters.php), qui convertit le `-` entouré
     * d'espaces en `&#8211;` via une chaîne traduisible
     * (`_x( '&#8211;', 'en dash' )`) — le rendu dépendrait donc de la locale.
     * La constante donne un résultat déterministe.
     *
     * @param string $separator Séparateur par défaut du core.
     * @return string Séparateur à utiliser.
     */
    public function filter_separator( string $separator ): string {
        return self::SEPARATOR;
    }

    /**
     * Parties composant le titre, avant assemblage.
     *
     * Clés fournies par le core (cf. `wp_get_document_title()`) :
     * - `title`   — titre de la page, de l'article ou de l'archive
     * - `page`    — « Page N », uniquement en pagination
     * - `tagline` — slogan du site, uniquement sur la page d'accueil
     * - `site`    — nom du site
     *
     * L'assemblage se fait par `implode( " $sep ", array_filter( $title ) )` :
     * retirer une clé — ou la vider — suffit à la faire disparaître sans laisser
     * de séparateur orphelin.
     *
     * @param array<string, string> $parts Parties du titre construites par le core.
     * @return array<string, string> Parties à assembler.
     */
    public function filter_parts( array $parts ): array {
        return $parts;
    }
}
