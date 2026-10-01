<?php

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Front\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Supprime les feuilles de styles Gutenberg inutiles côté front-end.
 *
 * Le thème utilise Tailwind CSS v4 et ne s'appuie pas sur theme.json.
 * Les styles globaux WordPress et le fallback classic-theme-styles
 * sont donc superflus et alourdissent inutilement le <head>.
 */
class DisableBlockStyles implements FeatureInterface {

    /** Supprime les styles au plus tôt, avant qu'ils ne soient enqueued. */
    public function register(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'dequeue' ], 100 );

        // global-styles est aussi accroché sur wp_footer — on supprime les deux
        remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
        remove_action( 'wp_footer',          'wp_enqueue_global_styles', 1 );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Retire du site public les feuilles de styles de l\'éditeur de blocs, inutiles au thème.', 'wp-theme-core' );
    }

    /**
     * Retire les styles inutiles en front-end.
     *
     * - classic-theme-styles      : fallback inutile pour un thème entièrement custom.
     * - wp-block-library          : variables admin/éditeur et CSS Gutenberg global.
     * - wp-block-heading/list/paragraph : supports color+typography désactivés via
     *                               DisableNativeBlockSupports — les classes
     *                               .has-background, .has-text-color, etc. ne sont
     *                               jamais émises, ces feuilles sont donc mortes.
     * - wp-block-quote            : bloc quote non utilisé dans ce thème.
     * - wp-img-auto-sizes-contain : fix WP 6.7 pour sizes="auto", sans objet ici
     *                               car les images sont gérées manuellement.
     */
    public function dequeue(): void {
        $handles = [
            'classic-theme-styles',
            'wp-block-library',
            'wp-block-heading',
            'wp-block-list',
            'wp-block-paragraph',
            'wp-block-quote',
            'wp-img-auto-sizes-contain',
        ];

        foreach ( $handles as $handle ) {
            wp_dequeue_style( $handle );
            wp_deregister_style( $handle );
        }
    }
}
