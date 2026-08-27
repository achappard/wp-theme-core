/**
 * Panneau « SEO » du document de Gutenberg.
 *
 * Écrit sans JSX et sans imports : le socle n'a pas de build. Sa
 * configuration — clés de meta, libellés traduits, limite indicative —
 * arrive par l'objet global posé juste avant lui par `Features\Seo\SeoPanel`.
 *
 * Les deux champs sont câblés sur le magasin `core/editor` : lecture via
 * `getEditedPostAttribute( 'meta' )`, écriture via `editPost( { meta } )`.
 * Contrairement au réglage global de `show-hide-page-title.js`, il n'y a ici
 * aucune route REST à appeler : c'est le bouton « Mettre à jour » du post qui
 * persiste la valeur, comme n'importe quel autre champ de l'éditeur.
 */
( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.data || ! wp.components ) {
		return;
	}

	var el          = wp.element.createElement;
	var useSelect   = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;

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

	function SeoPanel() {
		var meta = useSelect( function ( select ) {
			var editor = select( 'core/editor' );

			return editor ? editor.getEditedPostAttribute( 'meta' ) : {};
		}, [] ) || {};

		var editPost = useDispatch( 'core/editor' ).editPost;

		var description       = meta[ config.metaKeys.description ] || '';
		var excludeFromSitemap = !! meta[ config.metaKeys.excludeSitemap ];

		var length    = description.length;
		var overLimit = length > config.descriptionLimit;
		var VisualLabel = wp.components.BaseControl && wp.components.BaseControl.VisualLabel;

		function setMeta( key, value ) {
			var patch = {};

			patch[ key ] = value;
			editPost( { meta: patch } );
		}

		// Le compteur affiche « saisi/limite » ; au-delà, le texte change lui-même
		// (accessibilité : le signal ne repose pas que sur la couleur qui l'accompagne).
		var help = overLimit
			? config.descriptionOver.replace( '%1$d', length ).replace( '%2$d', config.descriptionLimit )
			: config.descriptionHelp.replace( '%1$d', length ).replace( '%2$d', config.descriptionLimit );

		return el(
			Panel,
			{ name: config.editorPluginName, title: config.panelTitle },
			el( wp.components.TextareaControl, {
				label: config.descriptionLabel,
				value: description,
				rows: 4,
				help: el( 'span', { style: overLimit ? { color: '#b32d2e' } : undefined }, help ),
				__nextHasNoMarginBottom: true,
				onChange: function ( value ) {
					setMeta( config.metaKeys.description, value );
				},
			} ),
			el( 'div', { style: { marginTop: '24px' } },
				/*
				 * Le style visuel d'un libellé de champ (ex. « Meta description » sur
				 * le TextareaControl ci-dessus) ne vient pas de la seule classe statique
				 * `components-base-control__label` : `@wordpress/components` l'habille
				 * d'une classe Emotion générée à l'exécution, imprévisible et absente
				 * si on ne rend qu'un `<span>` à la main. `BaseControl.VisualLabel` est
				 * le composant public prévu pour un libellé sans champ associé — il
				 * réutilise exactement le même rendu. Repli sur un style approché si
				 * l'export est absent (ancien WordPress).
				 */
				VisualLabel
					? el( VisualLabel, {}, config.sitemapTitle )
					: el( 'span', {
						style: { display: 'block', fontSize: '11px', fontWeight: '500', textTransform: 'uppercase' },
					}, config.sitemapTitle ),
				el( wp.components.ToggleControl, {
					label: config.sitemapLabel,
					checked: ! excludeFromSitemap,
					help: config.sitemapHelp,
					__nextHasNoMarginBottom: true,
					onChange: function ( included ) {
						setMeta( config.metaKeys.excludeSitemap, ! included );
					},
				} )
			)
		);
	}

	wp.plugins.registerPlugin( config.editorPluginName, { render: SeoPanel } );
} )( window.wp, window.DeefuseSeoPanel || {} );
