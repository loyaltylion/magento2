### Local set-up

1. Ensure you have PHP 7, Composer, and Yarn installed

2. `composer install && yarn install`

### Linting

`./vendor/bin/phpcs -ns .`

### Running tests

`./vendor/bin/phpunit Test/`

### Versioning

There are two main versions to be aware of:

* Our Composer package version (specified in composer.json)

* Our setup_version (specified in etc/module.xml)

Updates should only change the Composer version unless strictly necessary, as incrementing the setup_version will normally require a merchant to schedule downtime.

### Releasing on the Marketplace

1. Ensure that you've bumped the composer package version

2. Build a zip with `make zip`

3. Submit a new patch at https://developer.magento.com/extensions/, with the version matching the Composer package version on Master
