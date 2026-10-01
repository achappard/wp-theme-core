<?php
/**
 * Page Outils › Fonctionnalités du thème
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Internal;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use Deefuse\ThemeCore\Contracts\ServiceProviderInterface;
use Deefuse\ThemeCore\ThemeContext;

/**
 * Liste, pour les administrateurs, les features enregistrées par le thème.
 *
 * Montée par le `Kernel`, jamais par un provider : la page décrit le socle, elle ne
 * rend aucun service au site, et n'est donc pas une feature. Elle affiche exactement
 * ce que le `Kernel` a enregistré, provider par provider, dans l'ordre de
 * `functions.php` — une feature retirée d'un provider disparaît de la page.
 *
 * Interne : hors de la surface publique, peut changer en patch.
 */
final class ListingFeaturesPage {

    /** Préfixe des classes du socle : toute autre classe appartient au projet. */
    private const CORE_NAMESPACE = 'Deefuse\\ThemeCore\\';

    /** Paramètre d'URL portant la vue active. */
    private const SCOPE_PARAM = 'scope';

    /** Vue des features propres au projet, affichée par défaut. */
    private const SCOPE_PROJECT = 'project';

    /** Vue des features génériques, fournies par le socle. */
    private const SCOPE_GENERIC = 'generic';

    /**
     * @param ThemeContext $context Identité du thème.
     * @param array<class-string<ServiceProviderInterface>, FeatureInterface[]> $features Features enregistrées, par provider.
     */
    public function __construct(
        private readonly ThemeContext $context,
        private readonly array $features,
    ) {}

    /** Enregistre la page dans le menu Outils. */
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_page' ] );
    }

    /** Ajoute la page sous Outils, réservée aux administrateurs. */
    public function add_page(): void {
        add_management_page(
            __( 'Fonctionnalités du thème', 'wp-theme-core' ),
            __( 'Fonctionnalités du thème', 'wp-theme-core' ),
            'manage_options',
            $this->context->handle( 'features' ),
            [ $this, 'render' ]
        );
    }

    /**
     * Affiche les vues Projet / Générique, puis un tableau par provider
     *
     * Seules les features de la vue active sont listées ; un provider qui n'en compte
     * aucune n'est pas affiché. Les compteurs portent, eux, sur toutes les features.
     */
    public function render(): void {
        $scope = $this->current_scope();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Fonctionnalités du thème', 'wp-theme-core' ) . '</h1>';
        echo '<p>' . esc_html__( 'Fonctionnalités activées par le thème, regroupées par provider, dans leur ordre de chargement.', 'wp-theme-core' ) . '</p>';

        $this->render_views( $scope );

        $empty = true;

        foreach ( $this->features as $provider => $features ) {
            $features = array_filter( $features, fn( FeatureInterface $feature ): bool => $this->scope_of( $feature ) === $scope );

            if ( [] === $features ) {
                continue;
            }

            $empty = false;

            echo '<h2>' . esc_html( $this->short_name( $provider ) ) . '</h2>';
            echo '<table class="widefat striped" style="margin-bottom: 2em;">';
            echo '<thead><tr>';
            echo '<th scope="col" style="width: 30%;">' . esc_html__( 'Fonctionnalité', 'wp-theme-core' ) . '</th>';
            echo '<th scope="col">' . esc_html__( 'Description', 'wp-theme-core' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $features as $feature ) {
                $this->render_row( $feature );
            }

            echo '</tbody></table>';
        }

        if ( $empty ) {
            echo '<p>' . esc_html__( 'Aucune fonctionnalité dans cette catégorie.', 'wp-theme-core' ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Affiche les liens de filtrage, avec le markup de `WP_List_Table::views()`
     *
     * Même rendu que « Tous (1) | Publié (1) » sur la liste des articles : la vue
     * active porte `current` et `aria-current`.
     */
    private function render_views( string $scope ): void {
        $counts = [ self::SCOPE_PROJECT => 0, self::SCOPE_GENERIC => 0 ];

        foreach ( $this->features as $features ) {
            foreach ( $features as $feature ) {
                ++$counts[ $this->scope_of( $feature ) ];
            }
        }

        $labels = [
            /* translators: %s : nombre de fonctionnalités propres au projet. */
            self::SCOPE_PROJECT => _n_noop( 'Projet <span class="count">(%s)</span>', 'Projet <span class="count">(%s)</span>', 'wp-theme-core' ),
            /* translators: %s : nombre de fonctionnalités génériques, fournies par le socle. */
            self::SCOPE_GENERIC => _n_noop( 'Générique <span class="count">(%s)</span>', 'Générique <span class="count">(%s)</span>', 'wp-theme-core' ),
        ];

        $page_url = menu_page_url( $this->context->handle( 'features' ), false );
        $links    = [];

        foreach ( $labels as $view => $label ) {
            $current = $view === $scope ? ' class="current" aria-current="page"' : '';

            $links[] = sprintf(
                "\t<li class='%1\$s'><a href=\"%2\$s\"%3\$s>%4\$s</a>",
                esc_attr( $view ),
                esc_url( add_query_arg( self::SCOPE_PARAM, $view, $page_url ) ),
                $current,
                wp_kses(
                    sprintf( translate_nooped_plural( $label, $counts[ $view ], 'wp-theme-core' ), number_format_i18n( $counts[ $view ] ) ),
                    [ 'span' => [ 'class' => true ] ]
                )
            );
        }

        echo "<ul class='subsubsub'>\n" . implode( " |</li>\n", $links ) . "</li>\n</ul>";
        echo '<br class="clear">';
    }

    /** Vue demandée dans l'URL ; Projet par défaut, et pour toute valeur inconnue. */
    private function current_scope(): string {
        // Page en lecture seule : pas de nonce, la valeur est seulement comparée à une liste.
        $scope = isset( $_GET[ self::SCOPE_PARAM ] ) ? sanitize_key( wp_unslash( $_GET[ self::SCOPE_PARAM ] ) ) : '';

        return self::SCOPE_GENERIC === $scope ? self::SCOPE_GENERIC : self::SCOPE_PROJECT;
    }

    /** Vue à laquelle appartient une feature : Générique pour une classe du socle, Projet sinon. */
    private function scope_of( FeatureInterface $feature ): string {
        return str_starts_with( $feature::class, self::CORE_NAMESPACE ) ? self::SCOPE_GENERIC : self::SCOPE_PROJECT;
    }

    /** Affiche la ligne d'une feature ; le nom complet de la classe reste accessible au survol. */
    private function render_row( FeatureInterface $feature ): void {
        $class = $feature::class;

        printf(
            '<tr><td><code title="%1$s">%2$s</code></td><td>%3$s</td></tr>',
            esc_attr( $class ),
            esc_html( $this->short_name( $class ) ),
            esc_html( $feature->description() )
        );
    }

    /** Nom de la classe sans son namespace. */
    private function short_name( string $class ): string {
        $position = strrpos( $class, '\\' );

        return false === $position ? $class : substr( $class, $position + 1 );
    }
}
