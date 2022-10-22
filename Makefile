install:
	composer install

lint:
	composer exec --verbose phpcs -- --standard=PSR12 src
	composer exec --verbose phpstan -- --level=8 analyse src tests
test:
	composer exec --verbose phpunit tests

test-coverage:
	composer exec --verbose phpunit tests -- --coverage-clover build/logs/clover.xml