# GitLab CI/CD Guide

**Pipeline Status:** ✅ Configured  
**Coverage Reports:** ❌ Not generated in CI (local only)  
**Last Updated:** 2026-01-26

---

## 📋 Overview

This project uses GitLab CI/CD for automated testing and code quality verification. Every push and merge request automatically triggers linting and the full test suite (unit + integration).

### Pipeline Features:
- ✅ Automated test execution (175 tests)
- ✅ Linting with PHP_CodeSniffer (PSR-12)
- ✅ Composer dependency caching (~50% faster)
- ❌ No coverage reports generated in CI (run locally)

---

## 🚀 Pipeline Configuration

### File: `.gitlab-ci.yml`

The pipeline consists of 1 stage with 2 jobs:

#### Stage: Test (Every Push/MR)
- **lint:phpcs** — PSR-12 lint for `src` and `tests`
- **test:phpunit** — Unit + Integration tests (no coverage)

---

## ⚙️ Pipeline Stages

### lint:phpcs
```yaml
stage: test
script:
  - composer install
  - vendor/bin/phpcs --standard=PSR12 --extensions=php src tests
```

**Triggers:** Every push, every MR  
**Output:** Lint violations in console

### test:phpunit
```yaml
stage: test
script:
  - vendor/bin/phpunit --configuration ./phpunit.xml.dist --testsuite Unit --no-coverage
  - vendor/bin/phpunit --configuration ./phpunit.xml.dist --testsuite Integration --no-coverage
```

**Triggers:** Every push, every MR  
**Output:** Console results (no coverage)

---

## 📊 Artifacts

No test artifacts are uploaded by default. Coverage and Testdox are generated locally only.

---

## 🔧 Local Testing

### Run All Tests (local):
```bash
bin/phpunit
```

### With Coverage (local, requires Xdebug):
```bash
bin/phpunit-coverage
```


### Single Test File:
```bash
bin/phpunit tests/Unit/Model/UpsTest.php
```

---

## ⚡ Optimization

### Composer Cache
The pipeline caches Composer dependencies:
```yaml
cache:
  paths:
    - .composer-cache/
    - vendor/
```

**Benefit:** ~50% faster after first run

### PHP Template
Using YAML anchor for DRY configuration:
```yaml
.php_template: &php_template
  image: php:8.3-cli
  before_script:
    - apt-get update -qq
    - apt-get install -y -qq git unzip libzip-dev zlib1g-dev libxml2-dev pkg-config build-essential autoconf libssl-dev
    - docker-php-ext-install zip sockets
    - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    - composer install --no-progress --no-interaction --prefer-dist
```

---

## 🎯 GitLab Integration

### Merge Request Features:
1. **Pipeline Status** - Red/Green indicator
2. **Lint Results** - PSR-12 violations in job log
3. **Test Results** - pass/fail in job log

### Recommended MR Settings:
```
Settings → Merge Requests:
☑ Pipelines must succeed
☑ All discussions must be resolved
```

---

## 🐛 Troubleshooting

### Pipeline Fails - "Composer not found"
**Solution:** Check `before_script` section

### "No tests executed"
**Solution:** Verify `phpunit.xml.dist` path

### Coverage shows 0% (local)
**Solution:** Run `bin/phpunit-coverage` and ensure Xdebug is enabled locally

### Artifacts not found
**Solution:** Check if `tests/results/` directory exists

---

## ✅ Best Practices

1. ✅ **Run tests locally** before pushing
2. ✅ **Check coverage reports** after changes
3. ✅ **Don't merge red pipelines**
4. ✅ **Monitor coverage trends** - run `bin/phpunit-coverage` after major changes
5. ✅ **Use feature branches** - feature/* → develop → main

---

## 📈 Coverage Badge (optional)

Coverage is not generated in CI. If you want a badge, add a coverage stage first.

---

## 🔗 Related Documentation

- **[Implementation Summary](./test-implementation-summary.md)** - Test overview
- **[Coverage Analysis](./test-coverage-analysis.md)** - Detailed coverage data
- **[Test Plan](./test-plan.md)** - Testing strategy

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-26  
**Pipeline Status:** ✅ Active
