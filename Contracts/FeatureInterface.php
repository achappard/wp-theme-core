<?php
/**
 * Contrat pour toutes les features du thème
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme\Contracts;

interface FeatureInterface {
    /**
     * Enregistre les hooks WordPress de la feature
     */
    public function register(): void;
}
