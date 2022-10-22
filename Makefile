install:
	composer install

lint:
	composer exec --verbose phpcs -- --standard=PSR12 public
	composer exec --verbose phpstan -- --level=8 analyse public

start:
	php -S 0.0.0.0:${PORT:-8000} -t public