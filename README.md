# OS2Web KLE Drupal module  [![Build Status](https://travis-ci.org/OS2web/os2web_kle.svg?branch=master)](https://travis-ci.org/OS2web/os2web_kle)

## Module purpose

The aim of this module is to import KLE terms into Drupal.

## How does it work

After enabling this module, KLE terms will be imported on the next cron.

The import is happening in two steps:
1. Remote XML is read, parse and stored locally (default locaton: ```public://kle.xml```)
    - default schedule is **every 30 days**
2. Feeds modules takes care of reading the local XML and saving the entities as Taxonomy terms.
    - default schedule is **every 4 weeks**

After import KLE terms are available as taxonomy terms: ```admin/structure/taxonomy/manage/os2web_kle/overview```

## Additional settings
Settings are available under ```admin/config/content/os2web-kle```
* **URL to KLE XML fil** - URL of the webservice for KLE import.
* **URL til lokal KLE XML fil** - Path to the file where the converted XML shall be placed (e.g. public://kle/kle.xml).
* **Base URL to retsinfo (MUST end with "/")** - For example, http://www.retsinfo.dk/_GETDOC_/ACCN/
* **Amount of days between imports** - Import will only be run if the specified amount of days has passed
* **Remove obsolete KLE from Feeds file** - Upon regeneration of Feeds file, the obsolete KLE will be removed (requires reimport)
* **Hide obsolete KLE from autocomplete list** - Obsolete KLE will not be part of autocomplete, when KLE is used as autocomplete term reference (does not require reimport)

## Install

1. Module is available to download via composer.
    ```
    composer require os2web/os2web_kle
    drush en os2web_kle
    ```

1. After activation, run cron to trigger the import.

## Installing module from existing configuration

There is an acknowledged problem when installing this module from existing configuration, namely the imported Feed is not being created automatically.

If that is the case, and you don't see a feed item present in `admin/content/feed`, then follow those simple steps to create it manually:

1. Go to ```feed/add/os2web_kle_import```
2. Create a new feed with the following values:
* **Title**: _KLE importer_
* **Path**: _public://kle.xml_

## Update
Updating process for OS2Web KLE module is similar to usual Drupal 8 module.
Use Composer's built-in command for listing packages that have updates available:

```
composer outdated os2web/os2web_kle
```

## Automated testing and code quality
See [OS2Web testing and CI information](https://github.com/OS2Web/docs#testing-and-ci)

## Contribution

Project is opened for new features and os course bugfixes.
If you have any suggestion or you found a bug in project, you are very welcome
to create an issue in github repository issue tracker.
For issue description there is expected that you will provide clear and
sufficient information about your feature request or bug report.

### Code review policy
See [OS2Web code review policy](https://github.com/OS2Web/docs#code-review)

### Git name convention
See [OS2Web git name convention](https://github.com/OS2Web/docs#git-guideline)
