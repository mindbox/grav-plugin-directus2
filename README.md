# Directus2 Plugin

The **Directus2** Plugin is an extension for [Grav CMS](https://github.com/getgrav/grav) for using grav as frontend for a directus headless CMS.

With this plugin we import information from directus collections into flex-object collections. It is meant to run grav without the admin interface and fetch data only via webhooks, plus having some static content in your page tree (depending on your usage).

> Requires PHP 8.0 or newer. Tested with directus 9.x

## Usage

### Our Scenario

Wir nutzen bei mehreren Projekten grav als Frontend für ein directus headless CMS. Dabei gib eine Collection in denen die Seiten verwaltet werden. Dort können diverse Daten und Metadaten gepflegt werden sowie eine Liste von Content-Elementen. Diese werden dann auf der jeweiligen Seite in etwa wie grav Modulars nacheinander dargestellt. Auch Mehrsprachigkeit ist bei einem der Projekte von Relevanz.

Für diese Seiten verwenden wir den Begriff „Kuratierte Seite”. Das rührt daher, dass wir bei unseren ersten Projekten die Erzeugung oder Darstellung von Seiten zu atomatisieren (z.B. durch Erkennung/Interpretation der bereitgestellten Inhalte). Allerdings wurde zu oft nach manuellen Eingriffen gefragt, weswegen wir dann auf diese Art der Seitenzusammenstellung umgeschwenkt sind.

Zu jeder Seite muss in der Regel eine passende Seite im Seitenbaum (user/pages/) angelegt werden, da es am einfachsten ist über die slug der jeweiligen Seite den passenden Eintrag aus der Collection zu bekommen.

Warum soviel Aufwand? directus bietet für einige unserer Kunden viele Vorteile, die weit über die Speicherung von Daten für die Website hinaus gehen. Zusätzlich kann die Website davon profitieren Zugang zu weiter reichenden Informationen aus dem Backend zu erhalten.

### Suggested Content Structures

Wir beschreiben hier nur die relevanten Felder und Eigenheiten der grundelgenden Collections.

#### Curated Pages

Neben den Dingen die eine Seite gebrauchen kann (Hero image, Meta description, Headline, etc.) ist das wichtigste Feld für uns `slug`. Damit legen wir fest, für welche Seite die Inhalte bestimmt sind.

Genau so wichtig ist aber auch das Feld `content_elements`. Das ist ein *Many to Any* Feld, dass die eigentlichen Inhalte der Seite definiert. 

#### Content Elements Collections

Aktuell (2003/2024) [wird empfohlen](https://docs.directus.io/guides/headless-cms/reusable-components.html) für jeden Content Typ eine eigene Collection bereit zu stellen. Beispielsweise nutzen wir Elemente für Bild&Text, Aufzählungen mit Icons, Ansprechpartner, Akkordeons, Slider, Galerien, Downloads und mehr.

Diese Struktur hat den Vorteil, dass man für jeden Typ nur die notwendigen Felder in der Collection vorhanden sind, anstatt eine Collection die unzählige Felder bereit stellt, weil sie jede erdenkliche Funktion abbilden muss.

Die Redaktreure können dann Elemente aus diesen Bereichen auswählen oder neu anlegen und die Reihenfolge festlegen.

#### Specific Collections

Für Inhalte wie News (Blog), können wir in den Einträgen auch auf die Content Elements Collections zurückgreifen. Um eine Übersicht der News anzuzeigen nutzen wir aber keine kuratierte Seite, sondern nutzen gravs Flex Object Funktionen.

Die lässt sich auch auf andere Datentypen wie Stellenangebote, Produkte Teammitglieder oder Ansprechpartner übertragen.

Dazu weiter unten mehr Details.

### Creating Blueprints for Collections

Blueprints in the configured folder will automatically be activated, no modification of flex-objects config neccassary. Blueprints only need to contain the bare minimum of information to make grav happy. Additionally they contain parameters for the API requests and filtering/conditions for directus.

Example: `user/blueprints/flex-objects/directus/sjm_jobs.yaml`

```yaml
title: Jobs
description: Jobs
type: flex-objects
config:
  directus:
    depth: 3
    filter:
      status:
        value: 'published'
        operator: _eq
      sjm_group_members:
        mm_field: sjm_group_members_id
        value: 1
        operator: _in
  data:
    object: 'Grav\Common\Flex\Types\Generic\GenericObject'
    collection: 'Grav\Common\Flex\Types\Generic\GenericCollection'
    index: 'Grav\Common\Flex\Types\Generic\GenericIndex'
    storage:
      class: 'Grav\Framework\Flex\Storage\FileStorage'
      options:
        folder: user-data://directus/sjm_jobs

```

`config.directus` is where we store the information about what we want to get from directus when we do a fetch (getting all content from directus). In the example we demand a `depth` of 3 levels so we might get a good amout of recursive data, which can be important for information about referenced files for example.

In the `filter` we can setup conditions on which data to include. In teh example we only want published entries (`filter.status`). The operators can be found in the [directus docs](https://docs.directus.io/reference/filter-rules.html#filter-operators).

In the example you can see the filter for the content of the relational field `sjm_group_members`. This field is a *many to many* field, for which we have the `mm_field` option. You will need to note the field name from the contingency collection. If it's set up as a *n:1* connection, you can use an `_eq 1` comparsion without the `mm_field`.

The `config.data` part is the regular Flex Object stuff. We decided to default the stroage folder to `user/data/directus` (`config.data.storage.folder`) in order to distinguish from other Flex Objects.

> We plan to add a CLI command to quickly create basic blueprints.  
> Also we might add custom classes for Flex Objects and Flex Collections, which may then alter the `config.data.object` and `config.data.collection` notation


### Displaying Content for Pages

In the themes `templates/partials/base.html.twig` one of the first lines is this:

```twig
{% set pageInfo = grav.get('flex').collection( 'curated_pages' ).filterBy( { 'slug': page.rawRoute } ).first %}
```

It querys the Flex Object matching the current pages's slug. This way we always have the information relating the page on hand. Depending on the thing you cover in your collection you can access metadata, page title, and so on.

If we want to output the related content elements, we use a template (`templates/curated.html.twig`) which's important part is this:

```twig
{% for row in pageInfo.content_elements %}
    {% set module = grav.get('flex').object( row.item.id, 'row.collection' )%}
    {% set module = directusTranslate( module.jsonSerialize(), currentLang ) %}

    {% include 'partials/directus/' ~ row.collection ~ '.html.twig' with { module: module } %}
{% endfor %}
```

Line by line:

* Loop through the entries
* Query the corresponding Flex Object by the item's ID from their collection (don't forget to add all blueprints needed!)
* Translate the Item (optional). `directusTranslate` will overwrite the item's contents with information available in the `translations` object inside the item.
* Include the template and pass the item to it

You can extend this to your preferences. For example you might need some layout specific settings in you collection. How you handle this depends on the impact these need to have. For example some collections might have *compact* or *extended* options, you could use the collection name as folder name and have a default.html.twig plus optional layouts:

```twig
    {% set layout = module.layout|default( 'text' ) %}
    {% include 'partials/directus/' ~ row.collection ~ '/' ~ '.html.twig' with { module: module } %}
```


### Displaying Content from Collections

WIP explain like news plguin

## Endpoints/Webhooks

WIP 

### Complete Sync

`/your-prefix/sync`

### Pushing Changes

add
update
delete
restore (if we encounter a server error, the revolved content might not be restored automatically, trigger it with this hook)
assets-reset (remove all stored assets in case of name mismatch or other issues)

### Creating Page Folders for specific Collections
maybe todo? but flex objects are more elegeant.
blueprints can contain information on folder creation (if needed, still todo)

### Accessing Media in Templates

WIP 
directus_file()…

### Working with Translations

WIP 

## Configuration

Before configuring this plugin, you should copy the `user/plugins/directus2/directus2.yaml` to `user/config/plugins/directus2.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true

disableCors: true
endpointName: your-prefix
blueprints: user/blueprints/flex-objects/directus
storage: user/data/directus
assets: user/data/assets

logging: false
lockfileLifetime: 120

directus:
  token: 1234567
  email: your@email.com
  password: supersavepassword
  directusAPIUrl: http://your.api.com
```

WIP Describe

### Enviroments and overrides

```yaml
env: preview
envOverrides:
  preview:
    status: ['preview', 'published']
      # in env preview, rewrite status:'preview' to status:'published'
```

WIP
config… preview server…


## Installation

Installing the Directus2 plugin can be done in one of three ways: The GPM (Grav Package Manager) installation method lets you quickly install the plugin with a simple terminal command, the manual method lets you do so via a zip file, and the admin method lets you do so via the Admin Plugin.

### Installation as dependency (skeleton)

To install the plugin automaticall with `bin/grav install`, add the following to the git section of your `user/.dependecies` file:

```
git:
    news:
        url: https://github.com/zebra-group/grav-plugin-directus2
        path: user/plugins/directus2
        branch: main
```

### Manual Installation

To install the plugin manually, download the zip-version of this repository and unzip it under `/your/site/grav/user/plugins`. Then rename the folder to `directus2`. You can find these files on [GitHub](https://github.com/zebra-group/grav-plugin-directus2).

You should now have all the plugin files under

    /your/site/grav/user/plugins/directus2
	
> NOTE: This plugin is a modular component for Grav which may require other plugins to operate, please see its [blueprints.yaml-file on GitHub](https://github.com/zebra-group/grav-plugin-directus2/blob/main/blueprints.yaml).

## Credits

**Did you incorporate third-party code? Want to thank somebody?**
Eric…

## To Do

- [ ] CLI command for creating blueprints
- [ ] Custom Flex Classes for Collection or Item
- [ ] Future plans, if any

