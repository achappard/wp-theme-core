<?php

declare(strict_types=1);

namespace Theme\Features\Admin\DisableFunctionalities;

use Theme\Contracts\FeatureInterface;

/** Supprime le sélecteur de langue sur la page de connexion WordPress. */
class DisableLoginLanguageDropdown implements FeatureInterface
{

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        add_filter( 'login_display_language_dropdown', '__return_false' );
    }
}