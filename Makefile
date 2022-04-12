# make install
install:
	@docker run --rm -it -v$(PWD):/app composer install

# unit tests
phpunit:
	@docker run --rm -it -v$(PWD):/app --workdir=/app php:8.1-cli-alpine vendor/bin/phpunit --coverage-text

# phpstan
phpstan:
	@docker run --rm -it -v$(PWD):/app --workdir=/app php:8.1-cli-alpine vendor/bin/phpstan analyse -l max -c phpstan.neon src --ansi

# coding style fix
phpcs:
	@docker run --rm -it -v$(PWD):/app --workdir=/app php:8.1-cli-alpine vendor/bin/php-cs-fixer fix -vvv --allow-risky=yes

# test
test:
	@docker run --rm -it -v$(PWD):/app --workdir=/app php:8.1-cli-alpine php ./test.php

.PHONY: install phpunit phpstan phpcs test