<?php
/**
 * Favicons et identité PWA
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Front;

use Deefuse\ThemeCore\Contracts\FeatureInterface;
use Deefuse\ThemeCore\Support\ViteManifest;

/**
 * Expose au contexte Twig global les URLs du jeu de favicons (mode clair et
 * sombre) et les couleurs de marque nécessaires au rendu du <head>.
 *
 * Front uniquement : aucune icône d'administration, aucune icône de page de
 * connexion. Sans rapport avec `Admin\DisableSiteIcon`, qui masque un champ
 * de réglage indépendant.
 *
 * Ne rend aucun HTML : `register()` s'ajoute au filtre `timber/context`, à
 * charge du template `partials/favicon.twig` du thème de traduire ce tableau
 * en balises. Les fichiers eux-mêmes sont produits par le script Node
 * `bin/generate-favicons.mjs` du thème, jamais par PHP — cette feature ne
 * fait que résoudre leurs URLs si elles existent.
 *
 * Sans build disponible (script de génération jamais lancé), expose
 * `favicons: null` au contexte : au template de s'effacer plutôt que de
 * pointer vers des fichiers inexistants.
 */
final class Favicons implements FeatureInterface {

	/** Répertoire des favicons générés, relatif à dist/assets/. */
	private const DEFAULT_PATH = 'favicons';

	/**
	 * Les couleurs de marque n'ont pas de valeur par défaut : elles décrivent
	 * l'identité du projet. Les omettre doit échouer au démarrage plutôt que
	 * livrer un site aux couleurs d'un autre client — même convention que
	 * `Admin\AdminColorScheme::$palette` et les trois arguments d'`Admin\AdminFooter`.
	 *
	 * Elles doublent le fichier `bin/favicons.config.mjs` du thème (qui
	 * construit `site.webmanifest`) et `resources/scss/_brand.scss` : aucun
	 * des trois n'est une source que les deux autres pourraient dériver
	 * automatiquement, la duplication est assumée à l'étape charte, comme
	 * pour la palette admin.
	 *
	 * @param string       $theme_color_light Couleur de la barre de navigateur, thème clair.
	 * @param string       $theme_color_dark  Couleur de la barre de navigateur, thème sombre.
	 * @param string       $favicons_path     Répertoire des favicons générés, relatif à dist/assets/.
	 * @param ViteManifest $manifest          Résolution des URLs sous dist/assets/.
	 */
	public function __construct(
		private readonly string $theme_color_light,
		private readonly string $theme_color_dark,
		private readonly string $favicons_path = self::DEFAULT_PATH,
		private readonly ViteManifest $manifest = new ViteManifest(),
	) {}

	/** Enregistre l'injection des données de favicon dans le contexte Twig global. */
	public function register(): void {
		add_filter( 'timber/context', [ $this, 'add_favicons_to_context' ] );
	}

	/** Courte description affichée sur la page « Fonctionnalités du thème ». */
	public function description(): string {
		return __( 'Fournit au thème les favicons (versions claire et sombre) et la couleur de l\'onglet du navigateur.', 'wp-theme-core' );
	}

	/**
	 * Injecte le tableau `favicons` dans le contexte global.
	 *
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public function add_favicons_to_context( array $context ): array {
		$context['favicons'] = $this->resolve();

		return $context;
	}

	/**
	 * Construit le tableau consommé par `partials/favicon.twig`, ou `null` si
	 * le jeu n'a jamais été généré (`npm run favicons:generate` /
	 * `npm run build` jamais lancé sur ce poste).
	 *
	 * @return array<string, mixed>|null
	 */
	private function resolve(): ?array {
		$svg_light = $this->file_url( 'favicon.svg' );

		if ( '' === $svg_light ) {
			return null;
		}

		$has_dark = '' !== $this->file_path( 'favicon-dark.svg' );

		return [
			'svg_light'         => $svg_light,
			'svg_dark'          => $has_dark ? $this->file_url( 'favicon-dark.svg' ) : null,
			'has_dark'          => $has_dark,
			'ico'               => $this->file_url( 'favicon.ico' ),
			'png_96'            => $this->file_url( 'favicon-96x96.png' ),
			'apple_touch_icon'  => $this->file_url( 'apple-touch-icon.png' ),
			'manifest_url'      => $this->file_url( 'site.webmanifest' ),
			'theme_color_light' => $this->theme_color_light,
			'theme_color_dark'  => $this->theme_color_dark,
		];
	}

	/** URL publique d'un fichier du dossier favicons, ou chaîne vide s'il est absent. */
	private function file_url( string $file ): string {
		return '' === $this->file_path( $file )
			? ''
			: $this->manifest->asset_url( trailingslashit( $this->favicons_path ) . $file );
	}

	/** Chemin disque d'un fichier du dossier favicons, ou chaîne vide s'il est absent. */
	private function file_path( string $file ): string {
		$path = get_theme_file_path( 'dist/assets/' . trailingslashit( $this->favicons_path ) . $file );

		return file_exists( $path ) ? $path : '';
	}
}
