.DEFAULT_GOAL := help

.PHONY: help test coverage mutation analyse cs-check cs-fix complexity psalm rector rector-fix build-phar install hooks

help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-16s\033[0m %s\n", $$1, $$2}'

install: ## Install Composer dependencies
	composer install

hooks: ## Install git hooks via captainhook
	composer run post-install-cmd

test: ## Run the test suite
	composer test

coverage: ## Run the test suite with code coverage
	composer coverage

mutation: ## Run mutation testing
	composer mutation

analyse: ## Run PHPStan static analysis
	composer analyse

cs-check: ## Check code style (dry-run)
	composer cs-check

cs-fix: ## Fix code style
	composer cs-fix

complexity: ## Check cyclomatic complexity
	composer complexity

psalm: ## Run Psalm static analysis
	composer psalm

rector: ## Run Rector in dry-run mode
	composer rector

rector-fix: ## Apply Rector refactors
	composer rector-fix

build-phar: ## Build a self-contained PHAR binary
	@command -v box >/dev/null 2>&1 || { echo "box-project/box is not installed. Run: composer global require humbug/box"; exit 1; }
	box compile
