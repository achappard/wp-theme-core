<?php
/**
 * Identité du thème, transmise aux providers
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore;

/**
 * Porte les quelques valeurs qui changent d'un projet à l'autre.
 *
 * Les features sont génériques ; ce qui les distingue d'un site à l'autre tient à
 * un slug, un préfixe de handle, un espace de noms de blocs. Plutôt que de figer ces
 * valeurs dans chaque classe, elles sont réunies ici et transmises aux providers, qui
 * les répartissent entre les features qu'ils instancient.
 *
 * Objet de configuration, non de service : il ne contient que des valeurs, jamais de
 * dépendance à injecter. Les features qui ont besoin d'un collaborateur (`ViteManifest`,
 * `ViteDevServer`) le reçoivent par leur propre constructeur.
 *
 * Les trois préfixes retombent sur le slug faute de mieux : un projet n'a
 * pratiquement jamais de raison de les faire diverger, mais rien ne l'en empêche.
 */
final readonly class ThemeContext {

    public string $text_domain;
    public string $asset_prefix;
    public string $block_namespace;

    /**
     * @param string   $slug            Identifiant du thème (dossier, scheme admin…).
     * @param string   $text_domain     Domaine de traduction. Défaut : le slug.
     * @param string   $asset_prefix    Préfixe des handles WordPress. Défaut : le slug.
     * @param string   $block_namespace Espace de noms des blocs custom. Défaut : le slug.
     * @param string[] $view_paths      Répertoires de templates Twig, relatifs à la racine du thème.
     */
    public function __construct(
        public string $slug,
        ?string $text_domain = null,
        ?string $asset_prefix = null,
        ?string $block_namespace = null,
        public array $view_paths = [ 'resources/views' ],
    ) {
        $this->text_domain     = $text_domain ?? $slug;
        $this->asset_prefix    = $asset_prefix ?? $slug;
        $this->block_namespace = $block_namespace ?? $slug;
    }

    /**
     * Construit un handle WordPress préfixé.
     *
     * @param string $name Suffixe du handle (ex. `app`, `login`).
     */
    public function handle( string $name ): string {
        return $this->asset_prefix . '-' . $name;
    }
}
