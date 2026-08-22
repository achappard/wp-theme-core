# deefuse/wp-theme-core

Socle réutilisable des thèmes WordPress Deefuse : noyau Timber, contrats de features, et un catalogue
de features génériques (sécurité, nettoyage du front, back-office, assets Vite).

**Le socle ne décide de rien.** Il expose des classes ; c'est le thème qui choisit lesquelles il
enregistre, dans quel ordre, et avec quelles valeurs. Aucune feature ne s'active toute seule.

- PHP ≥ 8.5 · WordPress ≥ 6.7 · Timber ^2.3
- Namespace `Deefuse\ThemeCore\`, text-domain `wp-theme-core`

---

## Installation

Dépôt privé : le déclarer dans le `composer.json` du **projet** (Composer ignore les `repositories`
déclarés par une dépendance — seul le package racine compte).

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:achappard/wp-theme-core.git",
            "no-api": true
        }
    ],
    "require": {
        "deefuse/wp-theme-core": "^1.0"
    }
}
```

> `"no-api": true` est **indispensable**. Sans lui, Composer interroge `api.github.com` pour récupérer
> une archive et réclame un token, même quand une clé SSH suffirait.

**Accès depuis un serveur** : une deploy key en lecture seule par serveur, ajoutée sur ce dépôt
(GitHub en accepte plusieurs). C'est ce qui permet à `composer install` de tourner au déploiement,
`vendor/` restant gitignoré côté projet.

---

## Utilisation

Le thème compose son propre démarrage dans `functions.php` :

```php
\Deefuse\ThemeCore\Kernel::boot(
    new \Deefuse\ThemeCore\ThemeContext( slug: 'monprojet' ),
    [
        \Theme\Providers\SetupServiceProvider::class,
        \Theme\Providers\SecurityServiceProvider::class,
        \Theme\Providers\FrontServiceProvider::class,
        \Theme\Providers\AdminServiceProvider::class,
        \Theme\Providers\SeoServiceProvider::class,
    ]
);
```

Chaque provider vit **dans le thème**, implémente `ServiceProviderInterface` et retourne ses features
dans leur ordre d'enregistrement :

```php
public static function features( ThemeContext $context ): array {
    return [
        new AdminFooter( agency_name: 'Deefuse', agency_url: '…', agency_desc: '…' ),
        new DisableBlocks( $context->block_namespace ),
    ];
}
```

### Les quatre façons de surcharger, par ordre de préférence

1. **Retirer la ligne** du provider — la feature n'existe plus.
2. **Changer un argument nommé** — la feature est paramétrée.
3. **Écrire sa propre classe** implémentant `FeatureInterface` et la mettre à la place.
4. ~~Hériter d'une classe du socle~~ — **à ne pas faire**. Les features sont `final` exprès : hériter
   d'une classe dont les internes changent en patch recrée exactement le risque que ce package existe
   pour supprimer. Le geste 3 coûte le même effort et ne crée aucune dette.

---

## Surface publique

Seuls ces éléments sont couverts par le versionnement sémantique :

- `Kernel::boot()`, `Kernel::get_instance()`, `Kernel::context()`
- `ThemeContext` — constructeur, propriétés publiques, `handle()`
- `Contracts\FeatureInterface`, `Contracts\ServiceProviderInterface`
- Les **signatures de constructeur** des classes `Features\*`
- `Support\ViteManifest`, `Support\ViteDevServer`

Tout le reste est interne et peut changer en patch.

**Une valeur par défaut fait partie de l'API.** Modifier le défaut de `schemes_to_remove` changerait
visuellement tout le parc au prochain `composer update` sans toucher une signature : un changement de
défaut est **MINOR au minimum**, avec une entrée dédiée au CHANGELOG.

**Toujours passer les arguments par leur nom** dans les providers. Un nouveau paramètre optionnel est
ajouté en fin de signature en MINOR ; des appels positionnels casseraient.

---

## Mettre à jour un projet livré

```bash
composer update deefuse/wp-theme-core
```

**Jamais `composer update` nu** : il mettrait aussi à jour Timber et le reste du graphe.

Ce qui se propage et ce qui ne se propage pas :

| | Propagé par `composer update` |
|---|---|
| Correction de bug dans une feature déjà enregistrée | **oui** |
| Nouvelle feature ajoutée au socle | **non** — le provider est dans le thème, il faut l'y déclarer |
| Changement de valeur par défaut | oui, d'où la règle MINOR ci-dessus |

Le mode de défaillance à connaître : **une classe supprimée ou renommée provoque un écran blanc sur
tout le site, wp-admin compris**, puisque le point d'échec est le bootstrap. Jamais de suppression en
MINOR — `@deprecated` et `class_alias()` pendant un majeur complet.

---

## Développer le socle depuis un projet

Sans cela, chaque modification du socle impose un `composer update` et l'architecture est abandonnée
en deux semaines. Dans le `composer.json` du projet, **en local uniquement** :

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../../../../Développement Wordpress/Socle/wp-theme-core",
            "options": { "symlink": true }
        }
    ]
}
```

> ⚠️ En mode symlink, PHP résout les liens : `__DIR__` sort du thème, et WordPress cherche alors
> `wp-theme-core-{locale}.mo` au lieu de `{locale}.mo`. C'est la raison d'être des quatre fichiers de
> traduction ci-dessous.

---

## Traductions

Les chaînes sources sont **rédigées en français** : sans catalogue, `__()` renvoie la source et
l'affichage est correct. Le `.pot` ne sert donc qu'aux autres langues.

```bash
composer install                 # installe wp-cli/i18n-command
vendor/bin/wp i18n make-pot . languages/wp-theme-core.pot --domain=wp-theme-core --slug=wp-theme-core
# traduire languages/{locale}.po sous Poedit
vendor/bin/wp i18n make-mo languages
vendor/bin/wp i18n make-php languages
```

**Livrer quatre fichiers par langue**, et les commiter :

```
languages/fr_FR.mo                 languages/wp-theme-core-fr_FR.mo
languages/fr_FR.l10n.php           languages/wp-theme-core-fr_FR.l10n.php
```

`_load_textdomain_just_in_time()` choisit le nom selon que le répertoire est sous le thème ou non — et
ce test bascule entre une installation Composer normale et un montage `repository: path` en symlink.
Les deux noms couvrent les deux cas. Voir le docblock de `Kernel::init_core_translations()`.

---

## Ce que le socle attend du thème

Le socle ne construit ni n'enfile d'assets lui-même : il lit ce que le build du thème produit.

- `dist/hot` — témoin du dev server, écrit et supprimé par le plugin `wordpressHot` de `vite.config.js`
- `dist/assets/.vite/manifest.json` — résolution des noms hachés
- Entrées attendues par défaut : `resources/js/app.js`, `resources/js/login.js`,
  `resources/scss/admin-colors.scss` (paramétrable via `AdminColorScheme( entry: … )`),
  `resources/scss/editor-gutenberg.scss` (paramétrable via `EditorStyles( entry: … )`)

Les deux entrées SCSS sont des **entrées CSS pures**, sans JS d'accompagnement, et sont toujours
servies depuis `dist/assets/` : c'est WordPress qui les lit, jamais le navigateur via le dev server.
Les modifier impose un `npm run build`, même en `npm run dev`.

---

## Checklist de recette

À rejouer après chaque `composer update` du socle sur un projet :

- [ ] Front en `npm run dev` (HMR actif), puis après `npm run build` (URLs hachées)
- [ ] `wp-login.php` : logo, couleurs, message d'erreur unifié sur mauvais identifiants
- [ ] Jeu de couleurs admin appliqué, pastilles correctes dans le sélecteur du profil
- [ ] Pied de page de l'administration
- [ ] Éditeur : liste blanche de blocs respectée, supports couleur/typographie retirés
- [ ] Éditeur : le canvas reprend les styles du thème (`editor-gutenberg.scss` inlinée dans la page)
- [ ] Upload d'un SVG valide accepté, d'un SVG malformé refusé
- [ ] Aucun notice avec `WP_DEBUG` activé
