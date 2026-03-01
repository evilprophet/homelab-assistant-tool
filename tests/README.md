# Tests - Homelab Assistant Tool

## 🚀 Running Tests

```bash
# Full suite
bin/phpunit

# Unit suite
bin/phpunit --testsuite Unit

# Integration suite
bin/phpunit --testsuite Integration

# Functional suite
bin/phpunit --testsuite Functional

# Coverage (Xdebug required)
bin/phpunit-coverage
```

## 📁 Test Structure

```text
tests/
├── Functional/
│   ├── Controller/
│   └── Support/
├── Integration/
│   ├── Command/
│   ├── Repository/
│   ├── Service/
│   └── Support/
├── Support/
└── Unit/
    ├── Command/
    ├── Controller/
    ├── EventSubscriber/
    ├── Factory/
    ├── Helper/
    ├── Runtime/
    └── Service/
```

## 📚 Documentation

- 📖 **[Test Implementation Summary](../docs/test-implementation-summary.md)** – Overview and current scope
- 🚀 **[CI/CD Guide](../docs/test-ci-cd-guide.md)** - GitLab pipeline setup
