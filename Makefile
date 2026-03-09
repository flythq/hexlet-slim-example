start:
	php -S localhost:8888 -t public public/index.php

lint:
	composer exec --verbose phpcs -- --standard=PSR12 public src templates
	vendor/bin/phpstan analyse

fix:
	composer exec --verbose phpcbf -- --standard=PSR12 public src templates
