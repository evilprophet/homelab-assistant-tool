# GitLab CI/CD Guide

**Pipeline Status:** ✅ Configured  
**Coverage Reports:** ✅ Enabled  
**Last Updated:** 2026-01-25

---

## 📋 Overview

This project uses GitLab CI/CD for automated testing and code quality verification. Every push and merge request automatically triggers the test suite and generates coverage reports.

### Pipeline Features:
- ✅ Automated test execution (165 tests)
- ✅ Code coverage reports (83%)
- ✅ JUnit XML artifacts for GitLab integration
- ✅ HTML coverage reports
- ✅ Composer dependency caching (~50% faster)

---

## 🚀 Pipeline Configuration

### File: `.gitlab-ci.yml`

The pipeline consists of 2 stages:

#### Stage 1: Test (Every Push/MR)
- Runs all 165 tests
- Generates JUnit XML report
- Displays coverage percentage
- Takes ~2-3 minutes

#### Stage 2: Coverage (Main Branches Only)
- Installs Xdebug for detailed coverage
- Generates HTML coverage report
- Creates Testdox HTML documentation
- Takes ~3-4 minutes
- Only runs on: main, master, develop branches

---

## ⚙️ Pipeline Stages

### test:phpunit
```yaml
stage: test
script:
  - composer install
  - vendor/bin/phpunit
artifacts:
  - tests/results/junit.xml
coverage: '/^\s*Lines:\s*\d+\.\d+\%/'
```

**Triggers:** Every push, every MR  
**Output:** JUnit XML + console coverage  
**Artifacts:** 1 week retention

### test:coverage
```yaml
stage: coverage
script:
  - pecl install xdebug
  - vendor/bin/phpunit --coverage-html tests/results/coverage
artifacts:
  - tests/results/coverage/
only:
  - main
  - develop
```

**Triggers:** Push to main/develop  
**Output:** HTML coverage report + Testdox  
**Artifacts:** 1 month retention

---

## 📊 Artifacts

### JUnit XML Report
- **Path:** `tests/results/junit.xml`
- **Usage:** GitLab displays test results in MR
- **Retention:** 1 week
- **Size:** ~10-20 KB

### HTML Coverage Report
- **Path:** `tests/results/coverage/index.html`
- **Usage:** Download and open in browser
- **Retention:** 1 month
- **Size:** ~5-10 MB

### Testdox HTML
- **Path:** `tests/results/testdox.html`
- **Usage:** Readable test documentation
- **Retention:** 1 month
- **Size:** ~50-100 KB

---

## 🔧 Local Testing

### Run All Tests:
```bash
vendor/bin/phpunit
```

### With Coverage (requires Xdebug):
```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html tests/results/coverage
```


### Single Test File:
```bash
vendor/bin/phpunit tests/Unit/Model/UpsTest.php
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
    - apt-get update
    - apt-get install -y git unzip
    - composer install
```

---

## 🎯 GitLab Integration

### Merge Request Features:
1. **Test Results** - Automatic pass/fail display
2. **Coverage Badge** - Shows current coverage %
3. **Pipeline Status** - Red/Green indicator
4. **Artifacts Browser** - Download reports

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

### Coverage shows 0%
**Solution:** Ensure Xdebug is installed in coverage stage

### Artifacts not found
**Solution:** Check if `tests/results/` directory exists

---

## ✅ Best Practices

1. ✅ **Run tests locally** before pushing
2. ✅ **Check coverage reports** after changes
3. ✅ **Don't merge red pipelines** 
4. ✅ **Monitor coverage trends** - don't let it drop
5. ✅ **Use feature branches** - feature/* → develop → main

---

## 📈 Coverage Badge

Add to README.md:
```markdown
[![coverage](https://gitlab.com/{namespace}/{project}/badges/{branch}/coverage.svg)](https://gitlab.com/{namespace}/{project}/-/commits/{branch})
```

---

## 🔗 Related Documentation

- **[Implementation Summary](./test-implementation-summary.md)** - Test overview
- **[Coverage Analysis](./test-coverage-analysis.md)** - Detailed coverage data
- **[Test Plan](./test-plan.md)** - Testing strategy

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-25  
**Pipeline Status:** ✅ Active
