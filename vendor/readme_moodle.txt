General
----
List all outdated packages
composer outdated

Show complete list of packages.  Packages in need of update are colored red. The (still) up-to-date ones are colored green.
composer show -l

Check for updated composer packages without changing anything
composer update --dry-run

Update all composer packages
composer update


PHPCodeSniffer
--
cd ~/.config/composer
php ~/.config/composer/vendor/squizlabs/php_codesniffer/bin/phpcs -p -v --ignore=*/vendor/* --standard=PHPCompatibility /opt/lampp/apps/moodle/htdocs/blocks/integrityadvocate/


Monolog logging client
----
https://seldaek.github.io/monolog/
composer update monolog/monolog
