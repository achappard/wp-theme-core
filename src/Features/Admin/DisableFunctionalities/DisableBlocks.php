<?php
/**
 * Restriction des blocs disponibles dans l'éditeur
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use WP_Block_Editor_Context;
use WP_Block_Type_Registry;

/** Restreint les blocs Gutenberg disponibles à une liste blanche de blocs autorisés. */
final class DisableBlocks implements FeatureInterface {

    /**
     * Blocs natifs accessibles par défaut dans l'éditeur.
     *
     * @var string[]
     */
    private const DEFAULT_ALLOWED_BLOCKS = [
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

    /**
     * @param string   $block_namespace Espace de noms des blocs du thème, tous autorisés d'office.
     * @param string[] $allowed_blocks  Blocs natifs accessibles dans l'éditeur.
     */
    public function __construct(
        private readonly string $block_namespace,
        private readonly array $allowed_blocks = self::DEFAULT_ALLOWED_BLOCKS,
    ) {}

    /** Enregistre le filtre sur la liste des blocs autorisés. */
    public function register(): void {
        add_filter( 'allowed_block_types_all', [ $this, 'allowed_blocks' ], 10, 2 );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Limite les blocs proposés dans l\'éditeur à une liste de blocs autorisés.', 'wp-theme-core' );
    }

    /**
     * Retourne les blocs de la liste blanche + tous les blocs custom du thème.
     *
     * @param bool|string[]           $allowed_block_types Blocs autorisés par défaut (true = tous).
     * @param WP_Block_Editor_Context $editor_context      Contexte de l'éditeur en cours.
     * @return string[]
     */
    public function allowed_blocks( bool|array $allowed_block_types, WP_Block_Editor_Context $editor_context ): array {
        $prefix = $this->block_namespace . '/';

        $theme_blocks = array_filter(
            array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ),
            fn( string $block ) => str_starts_with( $block, $prefix )
        );

        return array_values( array_merge( $this->allowed_blocks, $theme_blocks ) );
    }
}
