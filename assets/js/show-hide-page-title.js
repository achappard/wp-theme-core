/**
 * Interrupteur « Afficher le titre » du panneau de document de Gutenberg.
 *
 * Écrit sans JSX et sans imports : le socle n'a pas de build, ce fichier est lu
 * sur disque par `Features\Admin\ShowHidePageTitle` et injecté en script inline.
 * Les dépendances sont donc prises sur `window.wp`, que WordPress expose déjà.
 *
 * Le réglage est global : l'interrupteur écrit une option du site, immédiatement,
 * par la route REST du socle. Sa configuration — état initial, route, libellés
 * traduits, CSS — arrive par l'objet global posé juste avant lui par la feature,
 * qui n'enfile ce script que sur les écrans concernés et pour qui a le droit d'y
 * toucher : il n'y a donc rien à vérifier ici.
 */
( function ( wp, config ) {
    'use strict';

    if ( ! wp || ! wp.plugins || ! wp.element || ! wp.apiFetch ) {
        return;
    }

    var el       = wp.element.createElement;
    var useState = wp.element.useState;

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

    /** Dernier état connu, relu par la surveillance du canvas. */
    var current = !! config.hidden;

    /**
     * Pose la feuille dans un document, et y reflète l'état courant.
     *
     * @return {boolean} false si le document n'est pas exploitable.
     */
    function applyTo( doc, hidden ) {
        if ( ! doc || ! doc.body || ! doc.head ) {
            return false;
        }

        if ( ! doc.getElementById( config.styleId ) ) {
            var style = doc.createElement( 'style' );

            style.id          = config.styleId;
            style.textContent = config.css;
            doc.head.appendChild( style );
        }

        doc.body.classList.toggle( config.bodyClass, hidden );

        return true;
    }

    /** Document du canvas, quand il est iframé. */
    function canvasDocument() {
        var frame = document.querySelector( 'iframe[name="editor-canvas"]' );

        return frame && frame.contentDocument ? frame.contentDocument : null;
    }

    /**
     * Applique l'état au document de l'éditeur et à celui du canvas.
     *
     * Le champ titre vit dans le canvas, iframé depuis WP 6.5 ; sur les rendus plus
     * anciens, en place dans le document principal. Les deux sont donc traités, et
     * `applyTo()` est idempotent : il repose la feuille si elle a disparu.
     */
    function apply( hidden ) {
        current = hidden;

        applyTo( document, hidden );
        applyTo( canvasDocument(), hidden );
    }

    /**
     * Maintient l'état appliqué, quoi qu'il arrive au canvas.
     *
     * Deux mécanismes, parce qu'aucun ne suffit seul :
     *
     * L'observateur réagit aux mutations du document de l'éditeur — l'apparition de
     * l'iframe, notamment. Mais tout ce qui se passe ensuite a lieu *dans* le canvas,
     * hors de sa portée.
     *
     * D'où la vérification périodique. Quand l'iframe entre dans le DOM, son
     * `contentDocument` est encore le document transitoire `about:blank` : la feuille
     * s'y pose sans erreur, puis disparaît avec lui quand Gutenberg installe le sien.
     * Le canvas est de surcroît remonté à chaque changement de mode d'édition. Aucun
     * événement ne signale ces remplacements de façon fiable ; on repasse donc
     * régulièrement, ce qui coûte un `querySelector` et un `getElementById` — et rien
     * de plus tant que la feuille est en place et la classe correcte.
     */
    function keep() {
        if ( 'undefined' !== typeof MutationObserver ) {
            var scheduled = false;

            new MutationObserver( function () {
                if ( scheduled ) {
                    return;
                }

                scheduled = true;
                window.requestAnimationFrame( function () {
                    scheduled = false;
                    apply( current );
                } );
            } ).observe( document.body, { childList: true, subtree: true } );
        }

        window.setInterval( function () {
            apply( current );
        }, 200 );
    }

    // Appliqué dès le chargement, sans attendre le rendu du panneau : l'affichage du
    // titre ne dépend pas de la barre latérale, qui peut être repliée ou montée tard.
    apply( current );
    keep();

    function ShowHidePageTitle() {
        var hiddenState = useState( current );
        var hidden      = hiddenState[ 0 ];
        var setHidden   = hiddenState[ 1 ];

        var errorState = useState( '' );
        var error      = errorState[ 0 ];
        var setError   = errorState[ 1 ];

        /**
         * Bascule le réglage, en l'appliquant avant d'avoir la réponse du serveur.
         *
         * L'interface répond ainsi immédiatement ; un échec d'enregistrement défait
         * la bascule et affiche le message, plutôt que de laisser croire à un
         * réglage pris en compte.
         */
        function change( visible ) {
            var next = ! visible;

            setHidden( next );
            setError( '' );
            apply( next );

            wp.apiFetch( {
                path: config.restPath,
                method: 'POST',
                data: { hidden: next },
            } ).then( function ( response ) {
                // On se cale sur ce que la base contient réellement.
                var stored = response && 'boolean' === typeof response.hidden ? response.hidden : next;

                setHidden( stored );
                apply( stored );
            } ).catch( function () {
                setHidden( ! next );
                apply( ! next );
                setError( config.error );
            } );
        }

        return el(
            Panel,
            { name: 'deefuse-page-title', title: config.panelTitle },
            el( wp.components.ToggleControl, {
                label: config.toggleLabel,
                // L'interrupteur dit « afficher », l'option stocke « masquer » :
                // une option absente vaut donc titre visible.
                checked: ! hidden,
                help: error || ( hidden ? config.helpHidden : config.helpShown ),
                __nextHasNoMarginBottom: true,
                onChange: change,
            } )
        );
    }

    wp.plugins.registerPlugin( config.pluginName, { render: ShowHidePageTitle } );
} )( window.wp, window.DeefuseShowHidePageTitle || {} );
