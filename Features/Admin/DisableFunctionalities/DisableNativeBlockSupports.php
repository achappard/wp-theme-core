<?php
/**
 * Retrait des supports natifs des blocs du core
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Retire les panneaux Couleur et Typographie de la barre latérale de l'éditeur,
 * pour les blocs natifs listés dans `BLOCKS`.
 *
 * La typographie et les couleurs relèvent du thème, pas de la saisie : laisser
 * ces réglages accessibles produit des styles inline et des classes de preset
 * sur lesquels les feuilles du thème n'ont pas la main, et que Bootstrap ne
 * connaît pas. L'éditeur n'expose donc que ce que le thème sait rendre.
 *
 * Ne retire que les supports déclarés par le core : un bloc ajouté à `$blocks`
 * sans support `color` ni `typography` est simplement laissé tel quel.
 */
final class DisableNativeBlockSupports implements FeatureInterface {

    /**
     * Blocs dont on supprime les supports couleur et typographie, par défaut.
     *
     * @var string[]
     */
    private const DEFAULT_BLOCKS = [
        'core/heading',
        'core/list',
        'core/list-item',
        'core/paragraph',
        'core/quote',
        'core/buttons',
        'core/button',
    ];

    /**
     * @param string[] $blocks Blocs dont on supprime les supports couleur et typographie.
     */
    public function __construct(
        private readonly array $blocks = self::DEFAULT_BLOCKS,
    ) {}

    /** Enregistre le filtre sur les arguments des types de blocs. */
    public function register(): void {
        add_filter( 'register_block_type_args', [ $this, 'remove_supports' ], 10, 2 );
    }

    /**
     * Supprime les supports color et typography sur les blocs ciblés.
     *
     * @param array  $args       Arguments du bloc (supports, attributes…).
     * @param string $block_type Slug du bloc (ex : core/heading).
     * @return array
     */
    public function remove_supports( array $args, string $block_type ): array {
        if ( ! in_array( $block_type, $this->blocks, true ) ) {
            return $args;
        }

        $args['supports']['color'] = [
            'text'       => false,
            'background' => false,
            'link'       => false,
            'heading'    => false,
            'gradient'   => false,
        ];

        $args['supports']['typography'] = [
            'fontSize'       => false,
            'lineHeight'     => false,
            'fontStyle'      => false,
            'fontWeight'     => false,
            'letterSpacing'  => false,
            'textDecoration' => false,
            'textTransform'  => false,
        ];

        return $args;
    }
}
