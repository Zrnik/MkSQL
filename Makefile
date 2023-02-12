restart: stop start

start:
	docker compose up -d

stop:
	docker compose down

composer-update:
	docker run --rm --interactive --tty \
      --volume $(shell pwd):/app \
      composer update

tests: run-tests
run-tests:
	cp ./.github/config/integrationTestDatabase.neon.docker ./.github/config/integrationTestDatabase.neon
	docker compose exec php /var/www/html/vendor/bin/phpunit
