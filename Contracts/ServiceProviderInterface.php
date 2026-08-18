<?php
/**
 * Contrat pour tous les providers du thème
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Contracts;

interface ServiceProviderInterface {
    /**
     * Retourne les features du provider, dans leur ordre d'enregistrement
     *
     * @return FeatureInterface[]
     */
    public static function features(): array;
}
