<?php
/**
 * Contrat pour toutes les features du thème
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Contracts;

interface FeatureInterface {
    /**
     * Enregistre les hooks WordPress de la feature
     */
    public function register(): void;
}
