# Directus2 Plugin

The **Directus2** Plugin is an extension for [Grav CMS](https://github.com/getgrav/grav) for using grav as frontend for a directus headless CMS.

With this plugin we import information from directus collections into flex-object collections. It is meant to run grav without the admin interface and fetch data only via webhooks, plus having some static content in your page tree (depending on your usage).

Tested with directus 9.x 

## Usage

### Suggested Content Structures

Curated Pages
content element collections
news, jobs, etc

### Creating Blueprints for Collections

blueprints in the configured folder will automatically be activated, no modification of flex-objects config neccassary.
Blueprints contain the bare minimum of information to make grav happy.
Additionally they contain parameters for the api requests and filtering/conditions
Also they can contain information on folder creation (if needed, still todo)

Example: `user/blueprints/flex-objects/directus/sjm_jobs.yaml`

```yaml
title: Jobs
description: Jobs
type: flex-objects
config:
  directus:
    depth: 3
    filter:
      sjm_group_members:
        mm_field: sjm_group_members_id
        value: 1
        operator: _in
  data:
    object: 'Grav\Common\Flex\Types\Generic\GenericObject'
    collection: 'Grav\Common\Flex\Types\Generic\GenericCollection'
    index: 'Grav\Common\Flex\Types\Generic\GenericIndex'
    storage:
      class: 'Grav\Framework\Flex\Storage\FolderStorage'
      options:
        folder: user-data://directus/sjm_jobs
        indexed: true

```
Explaining directus config in BP, examples (MN filter, etc)

TODO: CLI for adding BP

### Displaying Content for Pages

get the current curated page from flex objects compaging the slug in base.html.twig
loop elements in curated.html.twig

### Displaying Content from Collections

explain like news plguin

## Endpoints/Webhooks

### Complete Sync

`/your-prefix/sync`

### Pushing Changes

add
update
delete

### Creating Page Folders for specific Collections
todo!

### Accessing Media in Templates

directus_file()…

### Working with Translations

## Configuration

Before configuring this plugin, you should copy the `user/plugins/directus2/directus2.yaml` to `user/config/plugins/directus2.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true

disableCors: true
endpointName: your-prefix
blueprints: user/blueprints/flex-objects/directus
storage: user/data/directus

logging: false
lockfileLifetime: 120

directus:
  token: 1234567
  email: your@email.com
  password: supersavepassword
  directusAPIUrl: http://your.api.com
```

## Installation

Installing the Directus2 plugin can be done in one of three ways: The GPM (Grav Package Manager) installation method lets you quickly install the plugin with a simple terminal command, the manual method lets you do so via a zip file, and the admin method lets you do so via the Admin Plugin.

### GPM Installation (Preferred)

To install the plugin via the [GPM](https://learn.getgrav.org/cli-console/grav-cli-gpm), through your system's terminal (also called the command line), navigate to the root of your Grav-installation, and enter:

    bin/gpm install directus2

This will install the Directus2 plugin into your `/user/plugins`-directory within Grav. Its files can be found under `/your/site/grav/user/plugins/directus2`.

### Installation as dependency (skeleton)

To install the plugin automaticall with `bin/grav install`, add the following to the git section of your `user/.dependecies` file:

```
git:
    news:
        url: https://github.com/mindbox/grav-plugin-directus2
        path: user/plugins/directus2
        branch: main
```


### Manual Installation

To install the plugin manually, download the zip-version of this repository and unzip it under `/your/site/grav/user/plugins`. Then rename the folder to `directus2`. You can find these files on [GitHub](https://github.com//grav-plugin-directus2) or via [GetGrav.org](https://getgrav.org/downloads/plugins).

You should now have all the plugin files under

    /your/site/grav/user/plugins/directus2
	
> NOTE: This plugin is a modular component for Grav which may require other plugins to operate, please see its [blueprints.yaml-file on GitHub](https://github.com//grav-plugin-directus2/blob/main/blueprints.yaml).

### Admin Plugin

If you use the Admin Plugin, you can install the plugin directly by browsing the `Plugins`-menu and clicking on the `Add` button.

## Credits

**Did you incorporate third-party code? Want to thank somebody?**

## To Do

- [ ] Future plans, if any

