CONTAINER_NAME=test-project
DIR_DOCKER=docker

# spustí docker kontejner na popředí, pro ukončení je nutné dát CTRL+C (spustí se i po zadání `make` bez parametru)
up:
	cd "${DIR_DOCKER}" && docker-compose pull && docker-compose up

# spustí docker kontejner na popředí a provede build image, pro ukončení je nutné dát CTRL+C
up-build:
	cd "${DIR_DOCKER}" && docker-compose pull && docker-compose up --build

# spustí docker kontejner na pozadí, uživatel nevidí průběh spouštění
up-daemon:
	cd "${DIR_DOCKER}" && docker-compose pull && docker-compose up -d

# spustí docker kontejner na pozadí a provede build image, uživatel nevidí průběh spouštění
up-daemon-build:
	cd "${DIR_DOCKER}" && docker-compose pull && docker-compose up -d --build

# vypne docker kontejner
down:
	cd "${DIR_DOCKER}" && docker-compose down

# vypne docker kontejner a smaze images
down-rmi:
	cd "${DIR_DOCKER}" && docker-compose down --rmi all

# restartuje docker
restart r:
	cd "${DIR_DOCKER}" && docker-compose restart

# smaze nette cache
delete-cache dc:
	docker exec -it "${CONTAINER_NAME}" bash -c 'rm -rf temp/cache'

# phpstan
phpstan ps:
	docker exec -it "${CONTAINER_NAME}" bash -c 'composer phpstan'

# phpstan create baseline
phpstan-create-baseline pscb:
	docker exec -it "${CONTAINER_NAME}" bash -c 'composer phpstan:create-baseline'

# zobrazí errory
error e:
	docker exec -it "${CONTAINER_NAME}" bash -c 'tail -f /var/log/apache2/error.log'

# vleze do bashe spuštěného docker kontejneru
bash b:
	docker exec -it "${CONTAINER_NAME}" bash -c "umask 000 && bash"

# composer install
composer-install:
	docker exec -it "${CONTAINER_NAME}" bash -c "composer install"
