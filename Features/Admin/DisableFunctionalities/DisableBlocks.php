<?php
/**
 * Restriction des blocs disponibles dans l'éditeur
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Features\Admin\DisableFunctionalities;

use Theme\Contracts\FeatureInterface;
use WP_Block_Editor_Context;
use WP_Block_Type_Registry;

/** Restreint les blocs Gutenberg disponibles à une liste blanche de blocs autorisés. */
final class DisableBlocks implements FeatureInterface {

    /**
     * Seuls ces blocs natifs sont accessibles dans l'éditeur.
     * Ajouter ici les slugs des blocs à autoriser.
     *
     * @var string[]
     */
    private const ALLOWED_BLOCKS = [
        'core/paragraph',
        'core/heading',
        'core/list',
        'core/list-item',
//        'core/quote',
//        'core/image',
//        'core/table',
//        'core/buttons',
//        'core/button',
    ];

    /** Enregistre le filtre sur la liste des blocs autorisés. */
    public function register(): void {
        add_filter( 'allowed_block_types_all', [ $this, 'allowed_blocks' ], 10, 2 );
    }

    /**
     * Retourne les blocs de la liste blanche + tous les blocs custom du thème (mon-theme/*).
     *
     * @param bool|string[]           $allowed_block_types Blocs autorisés par défaut (true = tous).
     * @param WP_Block_Editor_Context $editor_context      Contexte de l'éditeur en cours.
     * @return string[]
     */
    public function allowed_blocks( bool|array $allowed_block_types, WP_Block_Editor_Context $editor_context ): array {
        $theme_blocks = array_filter(
            array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ),
            fn( string $block ) => str_starts_with( $block, 'mon-theme/' )
        );

        return array_values( array_merge( self::ALLOWED_BLOCKS, $theme_blocks ) );
    }
}
