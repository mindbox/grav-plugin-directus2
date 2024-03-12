# Directus2 Plugin

The **Directus2** Plugin is an extension for [Grav CMS](https://github.com/getgrav/grav) for using grav as frontend for a directus headless CMS.

With this plugin we import information from directus collections into flex-object collections. It is meant to run grav without the admin interface and fetch data only via webhooks, plus having some static content in your page tree (depending on your usage).

> Requires PHP 8.0 or newer. Tested with directus 9.x

## Usage

### Our Scenario

We use grav as the front end for a directus headless CMS in several projects. There is a collection in which the pages are managed. Various data and metadata can be maintained there, as well as a list of content elements. These are then displayed one after the other on the respective page in a similar way to grav Modulars. Multilingualism is also relevant for one of the projects.

We use the term "curated page" for these pages. This stems from the fact that in our first projects we tried to automate the creation or presentation of pages (e.g. by recognising/interpreting the content provided). However, we were asked too often for manual intervention, which is why we then switched to this type of page composition.

As a rule, a matching page must be created in the page tree (user/pages/) for each page, as it is easiest to get the matching entry from the collection via the slug of the respective page.

Why so much effort? directus offers many advantages for some of our customers that go far beyond the storage of data for a website. In addition, the website can benefit from gaining access to more extensive information from the backend.

### Suggested Content Structures

We only describe the relevant fields and characteristics of the basic collections here.

#### Curated Pages

In addition to the things that a page can use (hero image, meta description, headline, etc.), the most important field for us is 'slug'. We use it to specify which page the content is intended for.

Just as important is the `content_elements` field. This is a *Many to Any* field that defines the actual content of the page.

#### Content Elements Collections

Currently (2003/2024) [it is recommended](https://docs.directus.io/guides/headless-cms/reusable-components.html) to provide a separate collection for each content type. For example, we use elements for image & text, enumerations with icons, contact persons, accordions, sliders, galleries, downloads and more.

This structure has the advantage that only the necessary fields are available in the collection for each type, instead of a collection that provides countless fields because it has to map every conceivable function.

The editors can then select elements from these areas or create new ones and define the sequence.

#### Specific Collections

For content such as news (blog), we can also use the Content Elements Collections in the entries. However, we do not use a curated page to display an overview of the news, but use grav's Flex Object functions.

This can also be transferred to other data types such as job vacancies, products, team members or contact persons.

More details below.

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
    object: 'Grav\Plugin\Directus2\Flex\Types\Directus2\Directus2Object'
    collection: 'Grav\Plugin\Directus2\Flex\Types\Directus2\Directus2Collection'
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

Since we store the data from directus just like normal Flex Objects, we can query them in Twig like any other collection.

```twig
{% set services = grav.get('flex').collection( 'services' ).filterBy( { 'status': 'published' } ) %}

{% for service in services %}
    {% set service = localize( service.jsonSerialize(), currentLang ) %}
    {% set data = {
        icon: service.icon,
        title: service.name,
        text: service.short_description.
    } %}
    {% include 'partials/service-overview.html.twig' with { item: data } %}
{% endfor %}
```

## Endpoints/Webhooks

The Enpoints will be populated under the `endpointName` from the config. For example: example.com/your-prefix/sync.

|Endpoint|Function|
|---|---|
| add | Add a new item. Requires Payload, needs to be called via directus webhooks. |
| update | Update one ore more items. Requires Payload, needs to be called via directus webhooks. |
| delete | Delete one or more items. Requires Payload, needs to be called via directus webhooks. |
| sync | Clear the current Flex Objects (managed by this plugin) and get all the content fresh from the directus server. |
| restore | If we encounter a server error, the revolved content might not be restored automatically, trigger it with this enpoint. |
| assets-reset | Remove all stored assets in case of name mismatch or other issues. |

### Creating Page Folders for specific Collections

In the old directus plugin we used to have an action that created folders per entry of specific collections like blog entries (relict of pre Flex Objects times). This can be done with dynamic page creation now.

TODO: Expample Code

### Accessing Media in Templates

The Twig function `directus_file()` will download the requested file, saves the file in the accets folder (`assets` in plugin configuration) and outputs the URL to the file inside grav's file structure.

In the following example we request the first image from the field sjm_images in an element. We provide the function with the whole image object (includes id, filename_disk, filename_download, etc.).

```twig
<img class="card__thumbnail"
    src="{{ directusFile( post.sjm_images[0].directus_files_id, { width: '200', height: '300', quality: 70 } ) }}"
    width="200"
    height="300"
    loading="lazy"
    decoding="async"
    alt="{{ post.sjm_images[0].directus_files_id.description }}" />
```
The file is going to be saved as `user/data/assets/imagefilename-592d40567ccab4aef750b7a1a3f555a8.png`. The second part of the filename is a hash of the options (like size and quality).

For files like PDFs you just omit the options in the function call.

```twig
<a class="download__link" href="{{ directusFile( post.manual_file.directus_files_id ) }}">
    Download Instructions
</a>
```

### Working with Translations

To work with translations you set up your grav as usual. In directus, you setup translations for you collections, which will provide a `translations` object in every API response for these collections.

The Twig function `directusTranslate` will provide you a copy of the original entry but overwrites all fields available in the translation.

```twig
{% set translated = directusTranslate( post.jsonSerialize(), 'en' ) %}
{{ translated.sjm_description|markdown|raw }}
```

The language string `'en'` from the example should be replaced with a variable holding the current language. It depends on the way you handle this in your theme.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/directus2/directus2.yaml` to `user/config/plugins/directus2.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true

disableCors: true
endpointName: d2action
blueprints: user/blueprints/flex-objects/directus
storage: user/data/directus
assets: user/data/assets

logging: false
lockfileLifetime: 120

directus:
  token: 1234567
  email: test@example.com
  password: supersavepassword
  directusAPIUrl: http://your.api.com
```

| Configuration Key | Meaning/Notes |
| --- | --- |
| disableCors | CORS can be an issue of connection problems. For time your DevOps figure this out, you can disable it. |
| endpointName | Defines the slug where your API endpoints are located. For example http://example.com/d2action/sync |
| blueprints | Location where the Flex Object Blueprints related to directus are. |
| storage | Location where the Flex Object data is stored. Needs to match the data.storage.options.folder setting in your blueprints and will be used to create new blueprints via CLI. Do not use the same folder as redular flex objects, since this folder will be emptied in the process of a complete sync. |
| assets | Location where requested files are stored. |
| logging | Creates extensive log files. You should only use this in development or for debugging. |
| lockfileLifetime | Lifetime of the Lock File in seconds |
| directus.token | API Access Token. If set email and passwort are unnecessary |
| directus.email | Email (username) to access the API |
| directus.password | password to access the API |
| directus.directusAPIUrl | URL of your directus server. |

### Enviroments and overrides

You might need to have some kind of preview system, where editors can see their changes before publishing. Depending on your infrastructure and workflows the enviromental overrides might be helpful.

The example below can be added to your enviroment config like `user/env/preview.example.com/config/plugins/directus2.yaml`.

It assumes that you have a custom status 'preview'. This is not displayed in the live system because it only looks for 'published'. The env configuration changes the status preview to published and displays it on the system as if it were live.

```yaml
env: preview
envOverrides:
  preview:
    status: ['preview', 'published']
      # in env preview, rewrite status:'preview' to status:'published'
```


## Installation

Installing the Directus2 plugin can be done in one of three ways: The GPM (Grav Package Manager) installation method lets you quickly install the plugin with a simple terminal command, the manual method lets you do so via a zip file, and the admin method lets you do so via the Admin Plugin.

### Installation as dependency (skeleton)

To install the plugin automaticall with `bin/grav install`, add the following to the git section of your `user/.dependecies` file:

```
git:
    directus2:
        url: https://github.com/mindbox/grav-plugin-directus2
        path: user/plugins/directus2
        branch: main
```

### Manual Installation

To install the plugin manually, download the zip-version of this repository and unzip it under `/your/site/grav/user/plugins`. Then rename the folder to `directus2`. You can find these files on [GitHub](https://github.com/mindbox/grav-plugin-directus2).

You should now have all the plugin files under

    /your/site/grav/user/plugins/directus2
	
> NOTE: This plugin is a modular component for Grav which may require other plugins to operate, please see its [blueprints.yaml-file on GitHub](https://github.com/mindbox/grav-plugin-directus2/blob/main/blueprints.yaml).

## Credits

Big thanks to [Erik Konrad](https://github.com/erik-konrad), who created the original directus plugin which this on relies heavily on.

## To Do

- [ ] CLI command for creating blueprints
- [x] Custom Flex Classes for Collection or Item

