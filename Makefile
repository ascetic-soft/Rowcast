.PHONY: install test cs-fix cs-check phpstan ci help

install:
	composer install

test:
	./vendor/bin/phpunit

cs-fix:
	./vendor/bin/php-cs-fixer fix

cs-check:
	./vendor/bin/php-cs-fixer fix --dry-run --diff

phpstan:
	./vendor/bin/phpstan analyse

ci: cs-check phpstan test

help:
	@echo "Available targets:"
	@echo "  install   - composer install"
	@echo "  test     - run PHPUnit tests"
	@echo "  cs-fix   - fix code style (PHP CS Fixer)"
	@echo "  cs-check - check code style without fixing"
	@echo "  phpstan  - run PHPStan static analysis"
	@echo "  ci       - run all checks (cs-check, phpstan, test)"
