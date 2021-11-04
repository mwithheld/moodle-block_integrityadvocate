PHPCodeSniffer
--
cd ~/.config/composer
php ~/.config/composer/vendor/squizlabs/php_codesniffer/bin/phpcs -p -v --ignore=*/vendor/* --standard=PHPCompatibility /opt/lampp/apps/moodle/htdocs/blocks/integrityadvocate/


RSync into docker
----
https://registry.hub.docker.com/r/instrumentisto/rsync-ssh/

docker run --rm -i -v  downloads_moodle_data:/volume -v ~/Downloads/webdevt/integrityadvocate/ia-moodle:/mnt instrumentisto/rsync-ssh rsync --exclude '"'"'config/config.inc.php'"'"' --exclude '"'"'tmp/*'"'"' --no-perms --delete --chown=1001:root -avz /mnt/availability/condition/integrityadvocate /volume/availability/condition

docker run --rm -i -v  downloads_moodle_data:/volume -v ~/Downloads/webdevt/integrityadvocate/ia-moodle:/mnt instrumentisto/rsync-ssh rsync --exclude '"'"'config/config.inc.php'"'"' --exclude '"'"'tmp/*'"'"' --no-perms --delete --chown=1001:root -avz /mnt/blocks/integrityadvocate /volume/blocks/

#One-time
docker exec -u 0 -it --workdir / 80adc0247992 apt-get update && apt-get install nano rsync

docker exec -u 0 -it --workdir / 80adc0247992 rsync -avz --exclude '"'"'tmp/*'"'"' --exclude '"'"'config/config.inc.php'"'"' --delete /bitnami/moodle/blocks/integrityadvocate /opt/bitnami/moodle/blocks/integrityadvocate


Edit files in-place on docker
----
echo '' > /opt/bitnami/moodle/blocks/integrityadvocate/api/index.php && nano -w /opt/bitnami/moodle/blocks/integrityadvocate/api/index.php && php -l /opt/bitnami/moodle/blocks/integrityadvocate/api/index.php && httpd -k graceful
No syntax errors detected in /opt/bitnami/moodle/blocks/integrityadvocate/api/index.php