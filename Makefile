restart: stop start

start:
	docker compose up -d

stop:
	docker compose down

composer-update:
	docker run --rm --interactive --tty \
      --volume $(shell pwd):/app \
      composer update

phpstan:
	docker compose exec php /var/www/html/vendor/bin/phpstan --memory-limit=4G

tests: run-tests
run-tests:
	docker compose exec database mysql -u root -proot -e "DROP DATABASE mk_sql_test"
	docker compose exec database mysql -u root -proot -e "CREATE DATABASE mk_sql_test"
	cp ./.github/config/integrationTestDatabase.neon.docker ./.github/config/integrationTestDatabase.neon
	docker compose exec php /var/www/html/vendor/bin/phpunit

