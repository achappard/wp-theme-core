<?php
/**
 * Classe principale du thème
 *
 * @package Theme
 */

declare(strict_types=1);

namespace Theme;

use Theme\Providers\AdminServiceProvider;
use Theme\Providers\FrontServiceProvider;
use Theme\Providers\SeoServiceProvider;
use Theme\Providers\SetupServiceProvider;
use Timber\Timber;

class Theme {
    /**
     * Instance unique de la classe
     */
    private static ?Theme $instance = null;

    /**
     * Domaine de traduction du thème
     */
    private static string $textdomain = 'mon-theme';

    /**
     * Constructeur privé pour le pattern Singleton
     */
    private function __construct() {
        $this->init_timber();
        $this->load_features();
    }

    /**
     * Récupère l'instance unique de la classe
     */
    public static function get_instance(): Theme {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialise Timber
     */
    private function init_timber(): void {
        Timber::$dirname = [ 'resources/views' ];
        Timber::init();
        add_filter( 'timber/twig/environment/options', [ $this, 'set_twig_options' ] );
        add_filter( 'timber/context', [ $this, 'add_twig_context' ] );
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
     * Ajoute les données globales au contexte Twig
     */
    public function add_twig_context( array $context ): array {
        $context['textdomain'] = self::$textdomain;

        return $context;
    }

    /**
     * Charge toutes les features du thème, groupées par provider
     *
     * L'ordre des providers fait foi : Setup initialise le thème, puis Front,
     * Admin et Seo. Une feature s'ajoute dans le provider correspondant,
     * jamais ici.
     */
    private function load_features(): void {
        $features = array_merge(
            SetupServiceProvider::features(),
            FrontServiceProvider::features(),
            AdminServiceProvider::features(),
            SeoServiceProvider::features(),
        );

        foreach ( $features as $feature ) {
            $feature->register();
        }
    }

    /**
     * Récupère le domaine de traduction du thème
     */
    public static function getTextdomain(): string {
        return self::$textdomain;
    }
}
