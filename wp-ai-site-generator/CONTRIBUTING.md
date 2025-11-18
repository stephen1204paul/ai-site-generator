# Contributing to WP AI Site Generator

Thank you for considering contributing to the WP AI Site Generator plugin! This document outlines the process and guidelines for contributing.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment for all contributors.

## How to Contribute

### Reporting Bugs

Before creating a bug report, please check existing issues to avoid duplicates.

**When reporting a bug, include:**
- A clear and descriptive title
- Steps to reproduce the issue
- Expected behavior vs. actual behavior
- WordPress version, PHP version, and plugin version
- Any error messages or screenshots
- Browser and operating system (if relevant)

### Suggesting Features

Feature suggestions are welcome! Please:
- Use a clear and descriptive title
- Provide a detailed explanation of the proposed feature
- Explain why this feature would be useful
- Include examples of how it would work

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Install dependencies**: `composer install && npm install`
3. **Make your changes** following the coding standards
4. **Test your changes** thoroughly
5. **Update documentation** if needed
6. **Commit your changes** with clear commit messages
7. **Push to your fork** and submit a pull request

#### Pull Request Guidelines

- Follow WordPress coding standards (WPCS)
- Include tests for new functionality
- Update CHANGELOG.md
- Ensure all tests pass
- Keep pull requests focused on a single feature/fix

## Development Setup

```bash
# Clone the repository
git clone https://github.com/yourusername/wp-ai-site-generator.git
cd wp-ai-site-generator/wp-ai-site-generator

# Install dependencies
composer install
npm install

# Start development environment
npm run env:start

# Build assets
npm run build

# Run tests
npm run test:php
npm run test:js
```

## Coding Standards

### PHP

- Follow [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use proper DocBlocks for all functions and classes
- Run PHPCS: `composer run lint`
- Fix issues: `composer run format`

### JavaScript

- Follow [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
- Use ESLint: `npm run lint:js`
- Fix issues: `npm run format`

### CSS

- Follow [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)
- Use Stylelint: `npm run lint:css`

## Testing

- Write unit tests for new PHP functionality
- Write JavaScript tests for React components
- Ensure all tests pass before submitting PR
- Aim for good code coverage

```bash
# PHP tests
npm run test:php

# JavaScript tests
npm run test:js

# E2E tests
npm run test:e2e
```

## Documentation

- Update inline code documentation
- Update README.md if adding new features
- Add entries to CHANGELOG.md
- Include JSDoc for JavaScript functions
- Include PHPDoc for PHP functions and classes

## Commit Messages

Follow the conventional commits specification:

```
type(scope): subject

body

footer
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Example:**
```
feat(providers): add support for Google Gemini

- Implement Google Gemini provider class
- Add configuration UI for Gemini
- Update provider manager to support Gemini
- Add tests for Gemini integration

Closes #123
```

## Questions?

If you have questions or need help, feel free to:
- Open an issue for discussion
- Reach out to the maintainers
- Join our community discussions

## License

By contributing, you agree that your contributions will be licensed under the GPL-2.0+ License.

Thank you for contributing!
