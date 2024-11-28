Behat on Docker
----
# Ref https://github.com/moodlehq/moodle-docker#use-containers-for-running-behat-tests .
bin/moodle-docker-compose exec webserver php admin/tool/behat/cli/init.php
bin/moodle-docker-compose exec -u www-data webserver php admin/tool/behat/cli/run.php --tags=@block_integrityadvocate_course 
# Some options for admin/cli/run.php:
# ... --format=pretty --colors --no-snippets
# ... --format=pretty --format-settings '{"formats": "html,image", "dir_permissions": "0777"}'

# Tests that interact with the Integrity Advocate API or use the IA UI...
# require an API key and AppId in config.php like below. Contact IntegrityAdvocate for more information.
$CFG->block_integrityadvocate_appid = '0235403f-934b-4785-a54f-546a8465ea8c';
$CFG->block_integrityadvocate_apikey = 'Oe6fs54bgMGTQ...O4y/ygdgs4qvkhEw=';
$CFG->behat_extraallowedsettings = array('block_integrityadvocate_appid', 'block_integrityadvocate_apikey');

PHPCodeSniffer
----
cd blocks/integrityadvocate
curl -OL https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar
curl -OL https://squizlabs.github.io/PHP_CodeSniffer/phpcbf.phar
git clone https://github.com/PHPCompatibility/PHPCompatibility ./tests/PHPCompatibility
git clone https://github.com/PHPCSStandards/PHPCSUtils.git ./tests/PHPCSUtils
php phpcs.phar -p -v --config-set installed_paths $(pwd -LP)/tests/PHPCompatibility,$(pwd -LP)/tests/PHPCSUtils

php phpcs.phar -p -v --ignore=*/vendor/*,*/node_modules/*,*/tests/* --standard=PHPCompatibility ./


PHPCSStandards
----
cd <blah>/moodle/blocks/integrityadvocate
#Note the config in phpstan.neon
curl -OL https://github.com/phpstan/phpstan/releases/download/1.12.3/phpstan.phar
php phpstan.phar analyse --memory-limit=1G ./
php /var/www/html/phpstan.phar --debug --memory-limit=1G --level=8 --configuration=phpstan.neon analyze classes/Api.php


Docker: SSH into the machine
----
docker ps
docker exec -it downloads_moodle_1 bash


Docker: RSync files into docker from host machine
----
Get the docker from https://registry.hub.docker.com/r/instrumentisto/rsync-ssh/
#Setup some tools
docker exec -u 0 -it --workdir / 80adc0247992 apt-get update && apt-get install nano rsync

#Sync in the availability
docker run --rm -i -v  downloads_moodle_data:/volume \
  -v ~/Downloads/webdevt/integrityadvocate/ia-moodle:/mnt instrumentisto/rsync-ssh rsync --filter='P phpstan.phar' \
  --exclude '"'"'config/config.inc.php'"'"' --exclude '"'"'tmp/*'"'"' --no-perms --delete --chown=1001:root \
  -avz /mnt/availability/condition/integrityadvocate /volume/availability/condition

#Sync in the block
docker run --rm -i -v  downloads_moodle_data:/volume \
  -v ~/Downloads/webdevt/integrityadvocate/ia-moodle:/mnt instrumentisto/rsync-ssh rsync --filter='P phpstan.phar' \
  --exclude '"'"'config/config.inc.php'"'"' --exclude '"'"'tmp/*'"'"' --no-perms --delete --chown=1001:root \
  -avz /mnt/blocks/integrityadvocate /volume/blocks/

#Sometimes you need to sync the folders within docker
docker exec -u 0 -it --workdir / 80adc0247992 rsync -avz --filter='P phpstan.phar' \
  --exclude '"'"'tmp/*'"'"' --exclude '"'"'config/config.inc.php'"'"' --delete \
  /bitnami/moodle/blocks/integrityadvocate /opt/bitnami/moodle/blocks/integrityadvocate


Docker: Edit files in the docker
----
echo '' > /opt/bitnami/moodle/blocks/integrityadvocate/api/index.php && \
  nano -w /opt/bitnami/moodle/blocks/integrityadvocate/api/index.php && \
  php -l /opt/bitnami/moodle/blocks/integrityadvocate/api/index.php && httpd -k graceful
No syntax errors detected in /opt/bitnami/moodle/blocks/integrityadvocate/api/index.php


Cypress tests
----
#Setup a Moodle - I use https://github.com/bitnami/bitnami-docker-moodle
#If not using the above image, setup an admin user with username=user; password=bitnami
#Create a course with fullname and shortname set to 'ia-automated-tests'
#Go into a course > Restore > User private backup area and Upload ia-automated-tests.mbz

#Install prereqs
apt-get install per docs: https://docs.cypress.io/guides/getting-started/installing-cypress#System-requirements

#Make sure npm installed
apt-get install npm

#Install node version required
nvm install 18
nvm use 18

#Install Cypress and modules on local machine (not docker)
cd <blah>/moodle/blocks/integrityadvocate
npm install --save-dev #cypress@8.7.0
npx browserslist@latest --update-db
npm i --save-dev cypress-fail-fast

#Set this baseUrl below to point at the root of your Moodle install.

#Run the test using the Cypress GUI
./node_modules/.bin/cypress open 

#Run the tests using headless CLI
cd <blah>/moodle/blocks/integrityadvocate
./node_modules/.bin/cypress run
