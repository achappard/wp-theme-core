<?php
/**
 * Noyau du thème : initialisation de Timber et chargement des providers
 *
 * @package Deefuse\ThemeCore
 */

declare(strict_types=1);

namespace Deefuse\ThemeCore;

use Deefuse\ThemeCore\Contracts\ServiceProviderInterface;
use InvalidArgumentException;
use Timber\Timber;

class Kernel {

    /**
     * Text-domain des chaînes du socle, distinct de celui du thème.
     *
     * Littéral et fixe : `wp i18n make-pot` n'extrait que cette forme. Les features
     * du socle vivant hors du thème, leurs chaînes ne peuvent pas partager son
     * domaine — elles seraient absentes du `.pot` du projet, et retraduites à
     * chaque nouveau site.
     */
    private const CORE_TEXTDOMAIN = 'wp-theme-core';

    /**
     * Instance unique de la classe
     */
    private static ?Kernel $instance = null;

    /**
     * Constructeur privé pour le pattern Singleton
     *
     * @param ThemeContext           $context   Identité du thème.
     * @param class-string<ServiceProviderInterface>[] $providers Providers, dans leur ordre de chargement.
     */
    private function __construct(
        private readonly ThemeContext $context,
        private readonly array $providers,
    ) {
        $this->init_core_translations();
        $this->init_timber();
        $this->load_features();
    }

    /**
     * Enregistre le répertoire des traductions du socle.
     *
     * WordPress ne le fait que pour le thème, d'après les en-têtes de `style.css` :
     * le domaine du socle, lui, doit être déclaré explicitement.
     *
     * `load_theme_textdomain()` et non `load_textdomain()` : le second exigerait de
     * résoudre la locale ici, or `determine_locale()` appelle `get_user_locale()` en
     * back-office, donc `wp_get_current_user()` — bien avant que WordPress n'ait
     * initialisé l'utilisateur courant. Depuis WP 6.7 le premier ne fait qu'enregistrer
     * un chemin, le chargement ayant lieu au premier `__()`.
     *
     * ⚠️ Le nom attendu du `.mo` dépend de l'emplacement de ce répertoire :
     * `fr_FR.mo` s'il est sous le thème, `wp-theme-core-fr_FR.mo` sinon
     * (cf. `_load_textdomain_just_in_time()` dans wp-includes/l10n.php). Installé en
     * dépendance Composer classique il est sous le thème ; monté en `repository: path`
     * avec `symlink`, il ne l'est plus. Livrer **les deux noms**, en `.mo` et en
     * `.l10n.php`, est la seule façon d'être correct dans les deux cas.
     */
    private function init_core_translations(): void {
        $path = dirname( __DIR__ ) . '/languages';

        add_action(
            'after_setup_theme',
            static fn() => load_theme_textdomain( self::CORE_TEXTDOMAIN, $path )
        );
    }

    /**
     * Démarre le thème
     *
     * L'ordre des providers est décidé par l'appelant (`functions.php`) et non ici :
     * le thème fournit un catalogue de features, le projet choisit lesquelles il
     * enregistre et dans quel ordre.
     *
     * @param ThemeContext $context   Identité du thème.
     * @param class-string<ServiceProviderInterface>[] $providers Providers, dans leur ordre de chargement.
     */
    public static function boot( ThemeContext $context, array $providers ): Kernel {
        return self::$instance ??= new self( $context, $providers );
    }

    /**
     * Récupère l'instance du thème, ou null s'il n'a pas encore démarré
     */
    public static function get_instance(): ?Kernel {
        return self::$instance;
    }

    /**
     * Identité du thème
     */
    public function context(): ThemeContext {
        return $this->context;
    }

    /**
     * Initialise Timber
     */
    private function init_timber(): void {
        Timber::$dirname = $this->context->view_paths;
        Timber::init();
        add_filter( 'timber/twig/environment/options', [ $this, 'set_twig_options' ] );
    }

    /**
     * Configure l'environnement Twig
     *
     * Active l'échappement automatique (Timber le désactive par défaut) : toute
     * sortie `{{ }}` est échappée sauf mention explicite `|raw`, réservée aux
     * valeurs qui contiennent volontairement du HTML (post.content, post.excerpt…).
     */
    public function set_twig_options( array $options ): array {
        $options['autoescape'] = 'html';

        return $options;
    }

    /**
     * Charge toutes les features du thème, provider par provider
     *
     * L'ordre reçu fait foi, à l'intérieur d'un provider comme entre providers.
     * Une feature s'ajoute dans le provider correspondant à son domaine, jamais ici.
     *
     * @throws InvalidArgumentException Si un provider n'honore pas le contrat — mieux
     *                                  vaut une erreur nette au démarrage qu'un appel
     *                                  statique sur une classe inattendue.
     */
    private function load_features(): void {
        foreach ( $this->providers as $provider ) {
            if ( ! is_subclass_of( $provider, ServiceProviderInterface::class, true ) ) {
                throw new InvalidArgumentException(
                    sprintf( '%s n\'implémente pas ServiceProviderInterface.', (string) $provider )
                );
            }

            foreach ( $provider::features( $this->context ) as $feature ) {
                $feature->register();
            }
        }
    }
}
