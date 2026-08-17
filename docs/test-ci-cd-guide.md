# 🚀 CI/CD Guide

**Pipeline Status:** Configured

**Coverage Reports:** Generated locally (Xdebug), not published by CI

## 🧭 Overview

This project uses GitLab CI/CD for automated testing and code quality verification.
Every push and merge request triggers one quality job that runs linting followed by all test suites.

### ✨ Pipeline Features

- Automated test execution (`Unit` + `Integration` + `Functional` suites)
- Linting with PHP_CodeSniffer (PSR-12)
- Composer dependency caching
- Dedicated test environment loaded from `.env.test` (`APP_ENV=test` is forced in `phpunit.xml.dist`)
- Integration DB tests use isolated SQLite test DB (`var/data/hat_test.sqlite`)

## ⚙️ Pipeline Configuration

### 📄 File

- `.gitlab-ci.yml`, which includes `php-quality@v1.8.0` from the shared CI/CD component catalog

### 🧩 Job

- `PHP Quality` - PSR-12 lint followed by Unit, Integration, and Functional tests without coverage

## 🔍 Job Details

### `PHP Quality`

```yaml
stage: validate
script:
  - vendor/bin/phpcs --standard=PSR12 --extensions=php src tests
  - vendor/bin/phpunit --configuration ./phpunit.xml.dist --testsuite Unit --no-coverage
  - vendor/bin/phpunit --configuration ./phpunit.xml.dist --testsuite Integration --no-coverage
  - vendor/bin/phpunit --configuration ./phpunit.xml.dist --testsuite Functional --no-coverage
```

Note:

- Integration DB tests recreate schema automatically inside test bootstrap/base classes.
- No additional migration step is required in CI for current integration coverage.

## 🧪 Local Testing

```bash
bin/phpunit
bin/phpunit --testsuite Unit
bin/phpunit --testsuite Integration
bin/phpunit --testsuite Functional
bin/phpunit-coverage
```

## 🧱 Runtime and Caching

- CI image: `php:8.4-cli`
- PHP extensions and Composer dependencies are installed once before the combined quality job
- Cache paths:
    - `.composer-cache/`
    - `vendor/`

## 🐳 GitHub Actions: image publication

A second pipeline lives in `.github/workflows/build-docker-image.yml` and is separate from the GitLab quality pipeline.

- Trigger: `workflow_dispatch` only, with a required `image_tag` input. Nothing publishes automatically on push.
- Registry: GHCR, authenticated with the workflow's own `GITHUB_TOKEN`.
- Tags pushed: the supplied `image_tag` **and** `latest`, both from whichever ref the run was started on.
- Build context: repository root with `./Dockerfile`.

Two consequences worth knowing before dispatching a run:

- The workflow runs no tests, so a dispatch from a broken ref publishes a broken image.
- `latest` is overwritten by every run regardless of the ref, and `docker-compose.yml` pins `latest`.

## 📝 Notes

- CI currently does not publish coverage artifacts.
- Coverage should be generated locally with `bin/phpunit-coverage`.
