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

    /** Affiche un tableau par provider : nom de chaque feature et sa description. */
    public function render(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Fonctionnalités du thème', 'wp-theme-core' ) . '</h1>';
        echo '<p>' . esc_html__( 'Fonctionnalités activées par le thème, regroupées par provider, dans leur ordre de chargement.', 'wp-theme-core' ) . '</p>';

        foreach ( $this->features as $provider => $features ) {
            echo '<h2>' . esc_html( $this->short_name( $provider ) ) . '</h2>';
            echo '<table class="widefat striped" style="margin-bottom: 2em;">';
            echo '<thead><tr>';
            echo '<th scope="col" style="width: 30%;">' . esc_html__( 'Fonctionnalité', 'wp-theme-core' ) . '</th>';
            echo '<th scope="col">' . esc_html__( 'Description', 'wp-theme-core' ) . '</th>';
            echo '</tr></thead><tbody>';

            if ( [] === $features ) {
                echo '<tr><td colspan="2">' . esc_html__( 'Aucune fonctionnalité.', 'wp-theme-core' ) . '</td></tr>';
            }

            foreach ( $features as $feature ) {
                $this->render_row( $feature );
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }

    /**
     * Affiche la ligne d'une feature
     *
     * Le nom complet de la classe reste accessible au survol ; une feature du projet
     * est signalée comme telle, pour la distinguer de celles du socle.
     */
    private function render_row( FeatureInterface $feature ): void {
        $class = $feature::class;

        echo '<tr><td>';
        printf( '<code title="%1$s">%2$s</code>', esc_attr( $class ), esc_html( $this->short_name( $class ) ) );

        if ( ! str_starts_with( $class, self::CORE_NAMESPACE ) ) {
            echo ' <em>' . esc_html__( '(projet)', 'wp-theme-core' ) . '</em>';
        }

        echo '</td><td>' . esc_html( $feature->description() ) . '</td></tr>';
    }

    /** Nom de la classe sans son namespace. */
    private function short_name( string $class ): string {
        $position = strrpos( $class, '\\' );

        return false === $position ? $class : substr( $class, $position + 1 );
    }
}
