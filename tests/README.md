# Tests - Homelab Assistant Tool

## 📊 Quick Stats

- **Tests:** 165 (98 Unit + 67 Integration)
- **Coverage:** 83.20% (Real, Xdebug verified)
- **Success Rate:** 100% ✅
- **Status:** Production Ready

---

## 🚀 Running Tests

```bash
# All tests
vendor/bin/phpunit

# Unit tests only
vendor/bin/phpunit --testsuite Unit

# Integration tests only
vendor/bin/phpunit --testsuite Integration

# With coverage report (requires Xdebug)
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html tests/results/coverage
```

---

## 📁 Test Structure

```
tests/
├── Unit/ (98 tests)
│   ├── Helper/ConfigurationTest.php
│   ├── Model/
│   ├── Provider/
│   └── Service/
└── Integration/ (67 tests)
    └── Command/
```

---

## 📚 Full Documentation

Complete testing documentation is available in `./docs/`:

- 📖 **[Test Implementation Summary](../docs/test-implementation-summary.md)** - Overview, timeline, all components
- 📊 **[Coverage Analysis](../docs/test-coverage-analysis.md)** - Detailed coverage data
- 🚀 **[CI/CD Guide](../docs/test-ci-cd-guide.md)** - GitLab pipeline setup
- 📋 **[Test Plan](../docs/test-plan.md)** - Testing strategy
- 🔮 **[Future Refactoring](../docs/future-refactoring-plan.md)** - Optional improvements

---

## 📦 Historical Documentation

Phase summaries and detailed implementation logs are archived in:
- `./archive/` - All historical documentation

---

**Quick Links:** [Project README](../README.md) | [Coverage Report](./results/coverage/index.html)
