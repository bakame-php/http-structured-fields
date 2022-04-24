# make install
install:
	@docker run --rm -it -v$(PWD):/app composer install

# make update
update:
	@docker run --rm -it -v$(PWD):/app composer update

# unit tests
phpunit:
	@docker run --rm -it -v$(PWD):/app composer phpunit

# phpstan
phpstan:
	@docker run --rm -it -v$(PWD):/app composer phpstan

# coding style fix
phpcs:
	@docker run --rm -it -v$(PWD):/app composer phpcs:fix

# test
test:
	@docker run --rm -it -v$(PWD):/app composer test

# downgrade
downgrade:
	@docker run --rm -it -v$(PWD):/app --workdir=/app php:8.1-cli-alpine vendor/bin/rector process src

# sandbox
sandbox:
	@docker run --rm -it -v$(PWD):/app php:8.1-cli-alpine php ./app/test.php

.PHONY: install update phpunit phpstan phpcs test sandbox downgrade