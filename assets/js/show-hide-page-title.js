/**
 * Interrupteur « Afficher le titre » du panneau de document de Gutenberg.
 *
 * Écrit sans JSX et sans imports : le socle n'a pas de build, ce fichier est lu
 * sur disque par `Features\Admin\ShowHidePageTitle` et injecté en script inline.
 * Les dépendances sont donc prises sur `window.wp`, que WordPress expose déjà.
 *
 * Sa configuration — types de contenu, clé de meta, libellés traduits, CSS —
 * arrive par l'objet global posé juste avant lui par la feature.
 */
( function ( wp, config ) {
    'use strict';

    if ( ! wp || ! wp.plugins || ! wp.element || ! wp.data || ! wp.coreData ) {
        return;
    }

    var el        = wp.element.createElement;
    var useEffect = wp.element.useEffect;

    /*
     * `PluginDocumentSettingPanel` a migré de `@wordpress/edit-post` vers
     * `@wordpress/editor` en WP 6.6, l'ancien emplacement restant exposé le
     * temps de la dépréciation. On accepte les deux plutôt que d'imposer une
     * version minimale du core.
     */
    var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel )
        || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

    if ( ! Panel ) {
        return;
    }

    /**
     * Les deux documents à traiter : celui de l'éditeur, et celui du canvas
     * quand il est iframé — le champ titre vit dans le second, mais les
     * anciennes versions du core rendent le canvas dans le premier.
     */
    function documents() {
        var list  = [ document ];
        var frame = document.querySelector( 'iframe[name="editor-canvas"]' );

        if ( frame && frame.contentDocument ) {
            list.push( frame.contentDocument );
        }

        return list;
    }

    /** Pose la feuille une seule fois par document, et y reflète l'état courant. */
    function apply( hidden ) {
        documents().forEach( function ( doc ) {
            if ( ! doc.body ) {
                return;
            }

            if ( ! doc.getElementById( config.styleId ) ) {
                var style = doc.createElement( 'style' );

                style.id          = config.styleId;
                style.textContent = config.css;
                doc.head.appendChild( style );
            }

            doc.body.classList.toggle( config.bodyClass, hidden );
        } );
    }

    function ShowHidePageTitle() {
        var postType = wp.data.useSelect( function ( select ) {
            var editor = select( 'core/editor' );

            return editor ? editor.getCurrentPostType() : null;
        }, [] );

        // Tous les hooks sont appelés avant le moindre retour : `postType` peut
        // être nul le temps du premier rendu, ce que `useEntityProp` tolère.
        var entity  = wp.coreData.useEntityProp( 'postType', postType, 'meta' );
        var meta    = entity[ 0 ] || {};
        var setMeta = entity[ 1 ];

        var supported = config.postTypes.indexOf( postType ) !== -1;
        var hidden    = !! meta[ config.metaKey ];

        useEffect( function () {
            if ( ! supported ) {
                return;
            }

            apply( hidden );

            /*
             * Le panneau peut être monté avant l'iframe du canvas, et celle-ci
             * est remontée à chaque changement de mode d'édition : une seconde
             * passe à la frame suivante rattrape le document manquant, sans
             * installer de surveillance permanente.
             */
            var frame = window.requestAnimationFrame( function () {
                apply( hidden );
            } );

            return function () {
                window.cancelAnimationFrame( frame );
            };
        }, [ hidden, supported ] );

        if ( ! supported ) {
            return null;
        }

        return el(
            Panel,
            { name: 'deefuse-page-title', title: config.panelTitle },
            el( wp.components.ToggleControl, {
                label: config.toggleLabel,
                help: hidden ? config.helpHidden : config.helpShown,
                // L'interrupteur dit « afficher », la meta stocke « masquer » :
                // une meta absente vaut donc titre visible, ce qui laisse les
                // contenus existants inchangés.
                checked: ! hidden,
                __nextHasNoMarginBottom: true,
                onChange: function ( visible ) {
                    var next = {};

                    next[ config.metaKey ] = ! visible;
                    setMeta( Object.assign( {}, meta, next ) );
                },
            } )
        );
    }

    wp.plugins.registerPlugin( config.pluginName, { render: ShowHidePageTitle } );
} )( window.wp, window.DeefuseShowHidePageTitle || {} );
