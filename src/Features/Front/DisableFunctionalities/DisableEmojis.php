<?php

declare(strict_types=1);

namespace Deefuse\ThemeCore\Features\Front\DisableFunctionalities;

use Deefuse\ThemeCore\Contracts\FeatureInterface;

/**
 * Désactive complètement le système d'emojis natif de WordPress.
 *
 * WordPress injecte par défaut un script de détection, une feuille de style
 * et un DNS prefetch vers s.w.org — inutiles sur un site professionnel
 * qui n'utilise pas les emojis dans son contenu.
 */
class DisableEmojis implements FeatureInterface
{

    /** Enregistre le hook d'initialisation. */
    public function register(): void
    {
        add_action( 'init', [ $this, 'disableEmojis' ] );
    }

    /** Courte description affichée sur la page « Fonctionnalités du thème ». */
    public function description(): string
    {
        return __( 'Désactive les scripts et styles d\'emojis ajoutés par WordPress.', 'wp-theme-core' );
    }

    /** Supprime tous les scripts, styles et filtres liés aux emojis. */
    public function disableEmojis(): void {
        remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script'    );
        remove_action( 'wp_print_styles',     'print_emoji_styles'              );
        remove_action( 'admin_print_styles',  'print_emoji_styles'              );
        remove_filter( 'the_content_feed',    'wp_staticize_emoji'              );
        remove_filter( 'comment_text_rss',    'wp_staticize_emoji'              );
        remove_filter( 'wp_mail',             'wp_staticize_emoji_for_email'    );
        add_filter( 'tiny_mce_plugins',       [ $this, 'remove_tinymce_emoji' ] );
        add_filter( 'wp_resource_hints',      [ $this, 'remove_dns_prefetch'  ], 10, 2 );
        add_filter( 'emoji_svg_url',          '__return_false'                  );
    }

    /**
     * Retire le plugin wpemoji de la liste des plugins TinyMCE.
     *
     * @param array<string> $plugins
     * @return array<string>
     */
    public function remove_tinymce_emoji( array $plugins ): array {
        return array_diff( $plugins, [ 'wpemoji' ] );
    }

    /**
     * Supprime le DNS prefetch vers s.w.org utilisé pour charger les emojis SVG.
     *
     * @param array<string> $urls
     * @param string        $relation_type
     * @return array<string>
     */
    public function remove_dns_prefetch( array $urls, string $relation_type ): array {
        if ( $relation_type === 'dns-prefetch' ) {
            $urls = array_filter( $urls, fn( string $url ) => ! str_contains( $url, 'emoji' ) );
        }
        return $urls;
    }

}