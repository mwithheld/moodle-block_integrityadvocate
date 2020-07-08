General
----
List all outdated packages
composer outdated

Show complete list of packages.  Packages in need of update are colored red. The (still) up-to-date ones are colored green.
composer show -l


Guzzle HTTP client
----
We use Guzzle 6.x b/c Guzzle 7.x requires PHP7.2, which is above Moodle 3.5 requirements (Moodle 7.0).
http://docs.guzzlephp.org/en/stable/index.html
composer update guzzlehttp/guzzle


Monolog logging client
----
https://seldaek.github.io/monolog/
composer update monolog/monolog
