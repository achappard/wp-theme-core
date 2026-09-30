# Changelog

Format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versionnement
[SemVer](https://semver.org/lang/fr/).

Chaque entrée porte une ligne **Impact projet**, seule information qu'on ne retrouve plus en relisant
le diff six mois plus tard :

- `aucun` — `composer update` suffit
- `à vérifier visuellement` — le rendu peut changer, passer la checklist de recette
- `nécessite une modification du provider` — le projet doit être modifié à la main

---

## [1.12.0] — 2026-09-30

### Ajouté

- `Features\Setup\ThemeSupport` — paramètre `extra_supports:` (défaut `[]`), pour déclarer les
  supports propres au projet en plus du socle structurel : un nom seul (`'responsive-embeds'`,
  `'align-wide'`), ou un nom associé à ses arguments (`'custom-logo' => [ 'height' => 80 ]`).
  `responsive-embeds` est nécessaire dès que le bloc Embed est autorisé : sans lui, le `<body>` du
  front n'a pas la classe `wp-embed-responsive`, et l'iframe garde la taille fixe renvoyée par oEmbed
  (500 × 281 pour YouTube) alors que l'éditeur l'affiche en pleine largeur

**Impact projet :** aucun par défaut — opt-in, `new ThemeSupport( extra_supports: [ 'responsive-embeds' ] )`
dans `SetupServiceProvider`.

## [1.11.0] — 2026-09-30

### Ajouté

- `Features\Admin\DisableFunctionalities\DisableUpdates` — paramètre `allow_minor_core:` (défaut
  `false`). À `true`, les mises à jour automatiques **mineures** du core (x.y.**z**, les correctifs de
  sécurité) ne sont plus bloquées : seuls `allow_major_auto_core_updates` reste à `false`, et
  `allow_minor_auto_core_updates` / `auto_update_core` ne sont plus filtrés. Le test Site Health
  `background_updates` est alors conservé, puisqu'il signale un vrai blocage. Extensions, thèmes et
  traductions restent bloqués dans tous les cas
- Le réglage du serveur garde le dernier mot : avec `AUTOMATIC_UPDATER_DISABLED` à `true` (ou
  `WP_AUTO_UPDATE_CORE` à `false`) dans `wp-config.php`, aucune mise à jour automatique n'a lieu

**Impact projet :** aucun par défaut — opt-in, `new DisableUpdates( allow_minor_core: true )` dans
`AdminServiceProvider` ; recommandé en production.

## [1.10.2] — 2026-09-30

### Sécurité

- `Features\Admin\SvgSupport` — XSS stockée dans l'assainissement des SVG. La liste noire maison
  laissait passer un `href` écrit `java&#9;script:…` (ou avec `&#10;`) : le parseur XML décode
  l'entité en tabulation, que ni le motif interdit ni le contrôle de schéma ne reconnaissaient, et le
  navigateur la retire de l'URL. Un clic sur le lien du SVG ouvert depuis `uploads/` exécutait alors
  du JavaScript sur l'origine du site. Même contournement avec un préfixe XLink arbitraire
  (`xmlns:x="http://www.w3.org/1999/xlink"` puis `x:href`), les attributs étant comparés sur leur nom
  qualifié. L'assainissement est désormais confié à **`enshrined/svg-sanitize`** (liste blanche
  d'éléments, d'attributs et de schémas d'URL, celle de Safe SVG et de WordPress VIP), références
  distantes retirées. Restent propres au socle : le rejet des `<!ENTITY`, le contrôle d'une racine
  `<svg>` avant et après nettoyage, et le refus d'un `<use>` autoréférent ou trop imbriqué (exception
  de la bibliothèque)
- `Features\Admin\SvgSupport` — l'assainissement n'était branché que sur
  `wp_handle_upload_prefilter` : un SVG envoyé en corps brut à `POST /wp/v2/media` (ou via
  `media_handle_sideload()`) passe par `wp_handle_sideload()` et atterrissait **sans aucun
  nettoyage**, pour tout compte ayant `upload_files`. Le même callback est désormais branché sur
  `wp_handle_sideload_prefilter`
- Nouvelle dépendance `require` : `enshrined/svg-sanitize` `^1.0` (`ext-dom`, `ext-libxml`, sans autre
  dépendance)

**Impact projet :** aucun — `composer update deefuse/wp-theme-core` suffit (la nouvelle dépendance
suit). Les SVG déjà présents dans la médiathèque ne sont pas repassés : les ré-uploader si un doute
existe sur leur provenance.

## [1.10.1] — 2026-09-30

### Corrigé

- `Features\Admin\AdminFooter` — avec `agency_desc: ''`, le pied de page affichait un tiret orphelin
  (« Une création Agence — »). Une description vide (ou faite d'espaces) donne désormais
  « Une création Agence », via une chaîne traduisible distincte (`Une création %s`, traduite en `en_US`)

**Impact projet :** aucun — `composer update deefuse/wp-theme-core` suffit.

## [1.10.0] — 2026-09-29

### Ajouté

- `Features\Admin\ConvertUploadsToWebp` — convertit en WebP les JPEG et PNG déposés dans la
  médiathèque, sur le filtre `wp_handle_upload`, **avant** la génération des miniatures : fichier
  principal, version `-scaled` et toutes les tailles sortent en WebP, l'original est supprimé.
  Couvre l'upload de la médiathèque et celui de l'éditeur de blocs (REST, `sideload`)
  - JPEG → WebP à `quality:` (défaut 90), orientation EXIF appliquée aux pixels avant conversion
  - PNG encodé sans perte (Imagick, repli GD) **et** à `quality:` ; la version avec perte n'est gardée
    que si elle allège d'au moins `png_lossy_min_saving:` (défaut `0.5`, `1.0` = toujours sans perte) —
    une photo enregistrée en PNG passe avec perte, un logo ou une capture reste pixel pour pixel
  - `image_editor_output_format` mappe les mêmes types vers WebP pour les tailles générées plus tard
  - `wp_client_side_media_processing_enabled` forcé à `false` : le traitement des images dans le
    navigateur (WordPress 7.1, actif en HTTPS ou sur `localhost`) court-circuiterait la conversion

### Notes

- Nécessite Imagick ou GD compilé avec WebP sur le serveur ; sinon, ou en cas d'échec de conversion,
  l'upload se fait normalement dans son format d'origine
- Les métadonnées IPTC / EXIF (légende, crédit, appareil) ne survivent pas à la conversion
- Les médias déjà présents ne sont pas convertis

**Impact projet :** aucun — opt-in, `new ConvertUploadsToWebp()` à ajouter à `AdminServiceProvider`.

## [1.9.0] — 2026-09-25

### Modifié

- `Features\Admin\DisableFunctionalities\DisableLockAppearanceTools` :
  - la redirection laisse passer l'« Aperçu en direct » d'un **autre** thème que le thème actif
    (`customize.php?theme={slug}` pour un thème classique, `site-editor.php?wp_theme_preview={slug}`
    pour un thème à blocs), jusqu'ici renvoyé sur Apparence > Thèmes pour les thèmes à blocs ;
    Personnaliseur, Éditeur de site et Polices restent bloqués pour le thème actif
  - le bouton « Personnaliser » du thème actif (carte et fiche détaillée) et ses liens d'en-tête /
    d'arrière-plan sont masqués sur Apparence > Thèmes : ils menaient à la redirection, sans
    explication
  - `wp_redirect()` remplacé par `wp_safe_redirect()`

### Notes

- Pas de retrait de la capability `customize` : l'aperçu d'un thème classique passe par le
  Personnaliseur lui-même, il disparaîtrait avec

**Impact projet :** à vérifier visuellement — Apparence > Thèmes n'affiche plus de bouton
« Personnaliser » sur le thème actif ; « Aperçu en direct » des autres thèmes fonctionne.

## [1.8.0] — 2026-09-25

### Ajouté

- `Features\Admin\DisableFunctionalities\DisableGutenberg` — remplace l'éditeur de blocs par
  l'éditeur classique pour tous les types de contenus, CPT futurs compris (filtre
  `use_block_editor_for_post_type`), ainsi que sur l'écran des widgets (`use_widgets_block_editor`)

### Notes

- Les features propres à l'éditeur de blocs deviennent sans effet une fois celle-ci active :
  `Admin\ShowHidePageTitle`, `Seo\SeoPanel`, `DisableBlocks`, `DisableNativeBlockSupports`. Retirer
  `SeoPanel` retire aussi l'interface de saisie des metas lues par `MetaDescription` et
  `SitemapExclusion`, qui continuent de fonctionner (repli sur l'extrait pour la description)

**Impact projet :** aucun — opt-in, `new DisableGutenberg()` à ajouter à `AdminServiceProvider`
pour les projets sans éditeur de blocs.

## [1.7.1] — 2026-08-27

### Corrigé

- Catalogue de traduction (`languages/`) régénéré : les chaînes de `Features\Seo\SeoPanel` (v1.7.0)
  n'avaient pas de traduction anglaise
- Commentaires `translators:` ajoutés pour les deux chaînes à placeholders de `SeoPanel`
- `Report-Msgid-Bugs-To` corrigé vers l'adresse `deefuse.fr`

**Impact projet :** aucun — `composer update` suffit.

## [1.7.0] — 2026-08-27

### Ajouté

- `Features\Seo\MetaDescription` — balise `<meta name="description">` sur les contenus singuliers,
  depuis une meta de post (`_deefuse_seo_description`), repli sur l'extrait de l'article
- `Features\Seo\SitemapExclusion` — exclusion individuelle du sitemap XML (meta
  `_deefuse_seo_exclude_from_sitemap`), via le filtre `wp_sitemaps_posts_query_args`
- `Features\Seo\OpenGraph` — balises `og:*` et `twitter:*` sur les contenus singuliers, filtre unique
  `deefuse/seo/open_graph_tags` pour surcharge par le projet
- `Features\Seo\SeoPanel` — panneau « SEO » du document Gutenberg (description + inclusion sitemap),
  seule classe des quatre à appeler `register_post_meta()`

### Notes

- `MetaDescription`, `SitemapExclusion` et `OpenGraph` fonctionnent en lecture seule
  (`get_post_meta()`), sans dépendre de `SeoPanel` pour s'exécuter — c'est `SeoPanel` qui dépend de
  leurs constantes `META_KEY`, jamais l'inverse
- `OpenGraph` traite son image de repli (`og:image`/`twitter:image`) exactement comme
  `Front\Favicons` traite ses fichiers générés : `ViteManifest` en dépendance optionnelle,
  vérification d'existence avant de construire l'URL, balises omises (pas cassées) si
  `dist/assets/image-og-default.png` n'a jamais été généré par `bin/generate-og-image.mjs` (nouveau
  script côté thème, voir sa documentation) — `new OpenGraph()` ne prend donc aucun argument
  obligatoire
- `image-og-default.png` est un placeholder généré (fond blanc, nom du thème en noir centré), à
  remplacer par une vraie image de marque 1200×630 dès que la charte existe
- Un plugin SEO tiers (Yoast, Rank Math) rend ces quatre features redondantes — pas de détection
  automatique, à retirer du provider en pareil cas

**Impact projet :** nécessite une modification du provider — ajouter `new MetaDescription()`,
`new SitemapExclusion()`, `new SeoPanel( $context )` et `new OpenGraph()` à `SeoServiceProvider` — et
porter `bin/generate-og-image.mjs` ainsi que le script `og-image:generate` de `package.json` (non
fournis par le socle, propres au thème comme `bin/generate-favicons.mjs`).

---

## [1.6.0] — 2026-08-27

### Ajouté

- `Features\Setup\TwigDump` — fonction Twig `dump()` rendue via Symfony VarDumper (`HtmlDumper`),
  remplace le `dump()` natif de Twig (`Twig\Extension\DebugExtension`, basé sur `var_dump`), y
  compris quand `WP_DEBUG`/le mode debug Twig est actif

### Notes

- `symfony/var-dumper` est déclarée en `require-dev` du socle, sans effet transitif chez un
  consommateur : c'est le `require-dev` du **projet** qui rend la classe disponible. Sans elle, la
  feature se neutralise silencieusement (`class_exists()`) — comportement attendu en production
  (`composer install --no-dev`)
- Aucun conflit de nom avec le `dump()` de Twig : les fonctions ajoutées via `addFunction()` passent
  par une extension de staging initialisée en dernier, qui écrase donc celle des extensions
  déclarées

**Impact projet :** nécessite une modification du provider — ajouter `new TwigDump()` à
`SetupServiceProvider`, et déclarer `symfony/var-dumper` dans le `require-dev` du projet si ce n'est
pas déjà fait.

---

## [1.5.0] — 2026-08-27

### Ajouté

- `Features\Seo\DocumentTitle` accepte désormais un séparateur au constructeur (`$separator`), au
  lieu d'une constante figée

### Modifié

- Le séparateur par défaut passe de `★` à `/`

**Impact projet :** à vérifier visuellement — `composer update` suffit à faire fonctionner le code,
mais tout projet qui instancie `new DocumentTitle()` sans argument voit son `<title>` changer de
séparateur. Passer `new DocumentTitle( separator: '★' )` pour conserver l'ancien rendu.

---

## [1.4.0] — 2026-08-25

### Ajouté

- `Features\Front\Favicons` — expose au contexte Twig global, sous la clé `favicons`, les URLs du
  jeu de favicons (mode clair et sombre) et les couleurs de marque `theme_color_light`/
  `theme_color_dark`. Ne génère aucun fichier ni aucun HTML : les fichiers sont produits par
  `bin/generate-favicons.mjs` (thème), le rendu par `partials/favicon.twig` (thème)

### Notes

- **Front uniquement** — aucune icône d'administration ni de page de connexion. Sans rapport avec
  `Admin\DisableSiteIcon`, qui masque un champ de réglage indépendant
- `theme_color_light`/`theme_color_dark` n'ont pas de valeur par défaut, comme
  `AdminColorScheme::$palette` : les omettre lève une `ArgumentCountError` au démarrage
- La variante sombre (`favicon-dark.svg`) est détectée par présence de fichier sur disque, jamais
  par un booléen déclaré au constructeur : évite qu'un flag diverge du contenu réel de
  `dist/assets/favicons/` généré par le script Node
- Sans build disponible (`npm run favicons:generate`/`npm run build` jamais lancé), le contexte
  expose `favicons: null` — au template de s'effacer
- URLs résolues via `Support\ViteManifest::asset_url()`, déjà générique pour tout fichier statique
  sous `dist/assets/` — aucune nouvelle classe de résolution

**Impact projet :** nécessite une modification du provider — ajouter
`new Favicons( theme_color_light: …, theme_color_dark: … )` à `FrontServiceProvider` — **et** le
portage manuel de `bin/generate-favicons.mjs`, `bin/favicons.config.mjs`,
`partials/favicon.twig` (+ son inclusion dans `base.twig`), et les sources SVG
(`resources/images/favicon-light.svg`), le socle ne les fournissant pas. Sans ces gestes la feature
reste inerte.

---

## [1.3.0] — 2026-08-24

### Ajouté

- `Features\Setup\Menus` — déclare les emplacements de menus du thème auprès de WordPress et
  injecte, pour chacun, l'objet `Timber\Menu` correspondant dans le contexte Twig global, sous la
  clé `{emplacement}_menu`

### Notes

- **Les libellés sont fournis par une closure, jamais un tableau.** `functions.php` — donc les
  providers — s'exécute avant `after_setup_theme`. Un `__()` évalué à cet endroit fait charger le
  catalogue de traduction trop tôt et déclenche le `_doing_it_wrong` de WordPress 6.7 dès qu'un
  `.mo` existe pour la locale courante (cf. `_load_textdomain_just_in_time()` dans
  `wp-includes/l10n.php`). La closure diffère cette évaluation jusqu'à `register_locations()`,
  appelée sur `after_setup_theme`. Le text-domain reste un littéral à l'intérieur : `wp i18n
  make-pot` continue de l'extraire normalement
- **`Timber::get_menu_by( 'location', … )` et non `Timber::get_menu( $location )`** :
  `MenuFactory::from()` retombe sur une résolution par slug puis par nom si aucun menu n'est
  affecté à l'emplacement, avec le risque documenté par la classe elle-même de retourner un menu
  dont le slug ou le nom coïncide avec l'emplacement demandé
- **Les tirets d'un emplacement sont normalisés en underscore** dans la clé de contexte
  (`main-nav` → `main_nav_menu`) : Twig lirait `{{ main-nav_menu }}` comme une soustraction
- Aucun défaut : `locations` est obligatoire, une `ArgumentCountError` sanctionne l'omission — les
  emplacements et leurs libellés appartiennent au projet, rien n'est deviné à sa place

**Impact projet :** nécessite une modification du provider — ajouter `new Menus( locations: … )` à
`SetupServiceProvider`. Sans cela la feature reste inerte.

---

## [1.2.0] — 2026-08-22

### Ajouté

- `Features\Admin\ShowHidePageTitle` — interrupteur « Afficher le titre » dans le panneau de
  document de Gutenberg, qui masque le champ titre du canvas. **Réglage global** : l'état vit dans
  une option (`deefuse_hide_post_title` par défaut) et vaut pour tous les types déclarés. Trois
  paramètres : `post_types` (défaut `['page']`), `option`, `capability` (défaut `edit_posts`)
- Route REST `deefuse/v1/hide-post-title` — écriture du réglage, permission portée par `capability`
- `assets/js/` dans le package — le script du panneau, **lu sur disque**, jamais servi par une URL

### Notes

- **Périmètre strictement éditorial** : le socle ne lit l'option nulle part ailleurs, le rendu
  public reste l'affaire des templates du thème
- **Une route REST plutôt que `register_setting()`** : `/wp/v2/settings` exige `manage_options`,
  écrit en dur dans `WP_REST_Settings_Controller` et qu'aucun filtre n'abaisse — y adosser l'option
  interdirait le réglage à toute personne n'administrant pas le site
- L'écriture est **immédiate**, non différée jusqu'au « Mettre à jour » : ce bouton enregistre le
  post, pas les réglages du site
- Le script n'est pas enfilé par URL : `vendor/` n'est pas garanti accessible en HTTP d'un
  hébergement à l'autre, et une balise pointant dessus marcherait en local pour échouer en
  production sans message. Il part donc en inline, sur un handle sans source
- La feuille n'est pas un fichier mais quatre lignes construites depuis la constante `BODY_CLASS` :
  un fichier CSS aurait redit cette classe en dur, sans que rien ne vérifie qu'elle la suive
- Le script pose la feuille lui-même dans le document de l'éditeur **et** dans celui du canvas
  iframé, plutôt que par `add_editor_style()` : le core y réécrit les sélecteurs en les préfixant de
  `.editor-styles-wrapper`, ce qui déplacerait la classe conditionnelle et casserait la règle
- Il surveille le montage du canvas (`MutationObserver`) et s'applique dès son chargement, sans
  attendre le rendu de la barre latérale : la première passe a lieu avant que l'iframe n'existe, et
  l'état ne changeant pas au rechargement, rien ne la rejouerait
- Écrit sans JSX ni imports, sur `window.wp` : le socle n'a pas de build, et n'en gagne pas un
- Comme `languages/`, `assets/js/` doit être commité et présent dans le tag — l'installation d'un
  package Composer ne déclenche aucune étape de build

> ⚠️ **Tag republié.** Une première v1.2.0 stockait l'état dans une post meta `_hide_post_title`, et
> exposait un paramètre `meta_key`. Le tag a été déplacé sur la présente version plutôt que
> d'ouvrir une 1.3.0 pour une API que rien n'avait consommée. Un poste ayant installé la première
> v1.2.0 garde l'ancien code sans aucun signal : y forcer une réinstallation
> (`rm -rf vendor/deefuse/wp-theme-core && composer install`) est le seul moyen de s'aligner.

**Impact projet :** nécessite une modification du provider — ajouter `new ShowHidePageTitle( $context )`
à `AdminServiceProvider`. Sans cela la feature reste inerte.

---

## [1.1.0] — 2026-08-22

### Ajouté

- `Features\Admin\EditorStyles` — applique au canvas de l'éditeur de blocs la feuille
  `resources/scss/editor-gutenberg.scss`, résolue par le manifest Vite. Pose
  `add_theme_support( 'editor-styles' )`, sans lequel `get_block_editor_theme_styles()` ignore
  la feuille : `add_editor_style()` ne déclare que `editor-style`, le support de l'éditeur classique
- `Support\ViteManifest::entry_relative_path()` — chemin d'un fichier émis relatif à la racine du
  thème. `add_editor_style()` traite toute URL absolue par `wp_remote_get()` : une URL y ferait
  appeler le site par lui-même en HTTP à chaque ouverture de l'éditeur

### Notes

- La feuille est **toujours servie depuis `dist/assets/`**, comme `admin-colors.scss` : WordPress lit
  le fichier sur disque et inline son contenu. Aucun HMR dans le canvas — modifier les styles de
  l'éditeur impose un `npm run build`
- Sans build disponible, `EditorStyles` ne déclare rien, pas même le support : `editor-styles` fait
  aussi passer le sous-menu Apparence de « Compositions » à « Design » sur un thème classique
- Le core préfixe les sélecteurs par `.editor-styles-wrapper` et inline le CSS dans la page de
  l'éditeur : ne pas importer Bootstrap en entier dans cette feuille
- Une feuille qui ne produit **aucune règle** ne fait émettre aucun `.css` par Vite : le manifest
  pointe alors vers le module JavaScript fantôme émis à la place. `EditorStyles` vérifie donc
  l'extension avant de déclarer quoi que ce soit — sans quoi le core inlinerait ce JS dans le canvas

**Impact projet :** nécessite une modification du provider — ajouter `new EditorStyles()` à
`AdminServiceProvider`, créer `resources/scss/editor-gutenberg.scss` et déclarer l'entrée
`'editor-gutenberg'` dans `vite.config.js`. Sans ces trois gestes, la feature reste inerte.

---

## [1.0.0] — 2026-08-21

Première version : extraction du socle depuis un thème client, dont l'historique complet est
conservé (34 commits, de l'initialisation à l'extraction).

### Ajouté

- `Kernel` — initialisation de Timber, échappement Twig automatique, chargement des providers dans
  l'ordre reçu, enregistrement du text-domain `wp-theme-core`
- `ThemeContext` — `slug`, `text_domain`, `asset_prefix`, `block_namespace`, `view_paths`, `handle()`
- `Contracts\FeatureInterface`, `Contracts\ServiceProviderInterface`
- `Support\ViteManifest`, `Support\ViteDevServer`
- 27 features : 5 `Security`, 6 `Front`, 14 `Admin`, 1 `Setup`, 1 `Seo`

### Notes

- L'ordre des providers appartient au thème (`functions.php`), pas au socle
- Les features sont `final` : la surcharge passe par le retrait, le paramétrage ou le remplacement
- `palette` et `icon_colors` d'`AdminColorScheme`, et les trois arguments d'`AdminFooter`, n'ont pas
  de valeur par défaut — les omettre lève une `ArgumentCountError` au démarrage plutôt que de livrer
  un site aux couleurs d'un autre client
- Aucune feature d'internationalisation du thème : WordPress s'en charge depuis les en-têtes de
  `style.css`, avant même l'inclusion de `functions.php`

**Impact projet :** aucun — version initiale.
