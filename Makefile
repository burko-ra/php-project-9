install:
	composer install

lint:
	composer exec --verbose phpcs -- --standard=PSR12 public src templates tests
	composer exec --verbose phpstan -- --level=8 analyse public src templates tests

start:
	php -S 0.0.0.0:${PORT:-8000} -t public

test:
	composer exec --verbose phpunit tests