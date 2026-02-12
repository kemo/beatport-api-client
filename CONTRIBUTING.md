# Contributing

Contributions are welcome! Please follow these guidelines.

## Setup

```bash
git clone https://github.com/kemo/beatport-api-client.git
cd beatport-api-client
composer install
```

## Development Workflow

### Running Tests

```bash
composer test
```

### Code Coverage

```bash
composer test:coverage
```

Coverage reports are generated in `coverage/` (HTML) and `coverage.xml` (Clover).

### Static Analysis

```bash
composer phpstan
```

### Code Style

Check for violations:

```bash
composer cs:check
```

Auto-fix violations:

```bash
composer cs:fix
```

### Full QA Pipeline

```bash
composer qa
```

## Pull Request Process

1. Fork the repository and create a feature branch from `develop`
2. Write tests for any new functionality
3. Ensure `composer qa` passes with no errors
4. Submit a pull request with a clear description of the changes

## Coding Standards

- Follow PSR-12 coding style (enforced by PHP CS Fixer)
- Use strict types in all PHP files
- Write unit tests for new code
- Keep methods focused and classes small
