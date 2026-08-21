<?php
/**
 * Pied de page de l'administration
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Admin;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/** Remplace le texte du pied de page de l'administration par les crédits de l'agence. */
final class AdminFooter implements FeatureInterface {

    /**
     * @param string $agency_name Nom de l'agence affiché dans le pied de page.
     * @param string $agency_url  URL du site de l'agence.
     * @param string $agency_desc Courte description de l'agence.
     */
    public function __construct(
        private readonly string $agency_name,
        private readonly string $agency_url,
        private readonly string $agency_desc,
    ) {}

    /** Enregistre le filtre de personnalisation du pied de page admin. */
    public function register(): void {
        add_filter( 'admin_footer_text', [ $this, 'replace_footer_text' ] );
    }

    /**
     * Retourne le texte de crédit affiché en bas de chaque page de l'administration.
     *
     * Le lien est assemblé hors de la chaîne traduisible : le traducteur ne
     * manipule que des marqueurs, jamais de balises. Une traduction fautive peut
     * au pire déplacer ou omettre un marqueur, pas produire du HTML cassé.
     */
    public function replace_footer_text(): string {
        $link = sprintf(
            '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
            esc_url( $this->agency_url ),
            esc_html( $this->agency_name )
        );

        return sprintf(
            /* translators: 1: lien vers le site de l'agence, 2: description de l'agence */
            __( 'Une création %1$s — %2$s', 'wp-theme-core' ),
            $link,
            esc_html( $this->agency_desc )
        );
    }
}
