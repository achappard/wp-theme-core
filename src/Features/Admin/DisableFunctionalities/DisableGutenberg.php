<?php
/**
 * Désactivation de l'éditeur de blocs
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Remplace l'éditeur de blocs par l'éditeur classique, pour tous les types de contenus
 * — articles, pages et CPT, y compris ceux déclarés après coup — ainsi que sur l'écran
 * des widgets.
 *
 * Filtre par type de contenu plutôt que `show_in_rest => false` sur chaque CPT : ce
 * dernier désactive aussi l'API REST du type, et serait à répéter à chaque déclaration.
 *
 * Incompatible avec les features qui ne vivent que dans l'éditeur de blocs
 * (`Admin\ShowHidePageTitle`, `Seo\SeoPanel`, `Admin\DisableFunctionalities\DisableBlocks`,
 * `Admin\DisableFunctionalities\DisableNativeBlockSupports`) : elles restent sans effet,
 * à retirer du provider.
 */
final class DisableGutenberg implements FeatureInterface {

    /** Enregistre les filtres de désactivation. */
    public function register(): void {
        // Priorité haute : l'emporte sur une extension qui réactiverait l'éditeur.
        add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );
        add_filter( 'use_widgets_block_editor', '__return_false', 100 );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string {
        return __( 'Remplace l\'éditeur de blocs par l\'éditeur classique, pour tous les contenus et pour les widgets.', 'wp-theme-core' );
    }
}
