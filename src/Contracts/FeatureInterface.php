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

    /**
     * Courte description de la feature, destinée à un administrateur
     *
     * Affichée sur la page « Fonctionnalités du thème » (Outils). Une phrase, traduite
     * avec `__()`, text-domain en littéral : `'wp-theme-core'` dans le socle, celui du
     * projet dans un thème.
     */
    public function description(): string;
}
