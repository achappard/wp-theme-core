<?php
/**
 * Contrat pour tous les providers du thème
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Contracts;

use Theme\ThemeContext;

interface ServiceProviderInterface {
    /**
     * Retourne les features du provider, dans leur ordre d'enregistrement
     *
     * Le contexte porte l'identité du thème (slug, préfixes) : c'est au provider
     * d'en répartir les valeurs entre les features qu'il instancie.
     *
     * @param ThemeContext $context Identité du thème.
     * @return FeatureInterface[]
     */
    public static function features( ThemeContext $context ): array;
}
