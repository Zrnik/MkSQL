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

coverage:
	docker compose exec php /var/www/html/vendor/bin/phpunit --coverage-html ./temp/coverage-html --coverage-xml ./temp/coverage-xml
	@echo ""
	@echo "See coverage at: http://localhost:63342/MkSQL/temp/coverage-html/index.html"

tests: run-tests
run-tests:
	docker compose exec database mysql -u root -proot -e "DROP DATABASE mk_sql_test"
	docker compose exec database mysql -u root -proot -e "CREATE DATABASE mk_sql_test"
	cp ./.github/config/integrationTestDatabase.neon.docker ./.github/config/integrationTestDatabase.neon
	docker compose exec php /var/www/html/vendor/bin/phpunit

