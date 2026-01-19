# CLAUDE.md - SureCart Shipping Project Guide

## Project Overview

**Repository:** surecart-shipping
**Purpose:** Shipping functionality extension/integration for SureCart (WordPress e-commerce platform)
**Status:** New project - actively under development

This document serves as a comprehensive guide for AI assistants working on this codebase. It outlines the project structure, development workflows, coding conventions, and best practices.

---

## Project Status

**Current State:** This is a new repository being initialized. The codebase structure and conventions documented here will guide development as the project grows.

**Expected Project Type:** Based on the repository name, this is likely:
- A WordPress plugin for SureCart shipping integrations
- A PHP-based extension with potential JavaScript components
- Integration with shipping carriers/services (USPS, FedEx, UPS, etc.)

---

## Technology Stack (Expected)

### Backend
- **Language:** PHP 7.4+ or PHP 8.0+
- **Framework:** WordPress plugin architecture
- **CMS:** WordPress 5.9+
- **E-commerce:** SureCart plugin integration

### Frontend (If Applicable)
- **Languages:** JavaScript/TypeScript, HTML, CSS
- **Build Tools:** Webpack, npm/yarn, or Composer
- **Frameworks:** Potentially React, Vue.js, or vanilla JavaScript

### Development Tools
- **Version Control:** Git
- **Package Management:** Composer (PHP), npm/yarn (JavaScript)
- **Testing:** PHPUnit, Jest (if JavaScript)
- **Code Quality:** PHP_CodeSniffer, ESLint, Prettier

---

## Repository Structure

```
surecart-shipping/
├── .git/                      # Git repository data
├── CLAUDE.md                  # This file - AI assistant guide
├── README.md                  # User-facing documentation (to be created)
├── LICENSE                    # Project license (to be added)
├── .gitignore                 # Git ignore rules
├── composer.json              # PHP dependencies (when created)
├── package.json               # JavaScript dependencies (when created)
├── phpunit.xml                # PHPUnit configuration
├── .phpcs.xml                 # PHP CodeSniffer rules
├── src/                       # Source code
│   ├── Plugin.php            # Main plugin class
│   ├── Admin/                # Admin interface components
│   ├── Api/                  # API integrations (shipping carriers)
│   ├── Models/               # Data models
│   ├── Services/             # Business logic services
│   └── Utils/                # Utility functions
├── assets/                    # Static assets
│   ├── css/                  # Stylesheets
│   ├── js/                   # JavaScript files
│   └── images/               # Image assets
├── templates/                 # Template files
├── languages/                 # Translation files
├── tests/                     # Test files
│   ├── Unit/                 # Unit tests
│   └── Integration/          # Integration tests
├── docs/                      # Additional documentation
└── vendor/                    # Composer dependencies (gitignored)
```

---

## Development Workflows

### Setting Up Development Environment

```bash
# Clone the repository
git clone <repository-url>
cd surecart-shipping

# Install PHP dependencies
composer install

# Install JavaScript dependencies (if applicable)
npm install

# Set up WordPress development environment
# (Instructions vary based on local setup: Local WP, MAMP, Docker, etc.)
```

### Branch Strategy

- **Main Branch:** `main` or `master` - production-ready code
- **Development Branch:** `develop` - integration branch for features
- **Feature Branches:** `feature/<feature-name>` - new features
- **Bug Fix Branches:** `fix/<bug-name>` - bug fixes
- **Claude Branches:** `claude/<session-id>` - AI assistant work branches

### Commit Conventions

Follow conventional commits format:

```
<type>(<scope>): <subject>

<body>

<footer>
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, no logic change)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Examples:**
```
feat(shipping): add USPS rate calculation integration
fix(api): handle timeout errors in carrier API calls
docs(readme): update installation instructions
```

### Testing Workflow

```bash
# Run PHP unit tests
composer test
# or
vendor/bin/phpunit

# Run JavaScript tests (if applicable)
npm test

# Run code quality checks
composer lint
# or
vendor/bin/phpcs
```

### Build and Deployment

```bash
# Build production assets (if applicable)
npm run build

# Create release package
composer archive

# Deploy to WordPress plugins directory or via CI/CD
```

---

## Coding Conventions

### PHP Conventions

1. **PSR Standards:** Follow PSR-12 coding standard
2. **Naming Conventions:**
   - Classes: `PascalCase`
   - Methods: `camelCase`
   - Functions: `snake_case` (WordPress convention)
   - Constants: `UPPER_SNAKE_CASE`
   - Files: Match class names or use `kebab-case` for includes

3. **Documentation:**
   - Use PHPDoc blocks for all classes, methods, and functions
   - Document parameter types, return types, and exceptions

4. **WordPress Integration:**
   - Prefix all functions with `surecart_shipping_`
   - Use WordPress coding standards where applicable
   - Leverage WordPress hooks and filters appropriately

5. **Security:**
   - Sanitize all inputs: `sanitize_text_field()`, `esc_attr()`, etc.
   - Escape all outputs: `esc_html()`, `esc_url()`, etc.
   - Use nonces for form submissions: `wp_nonce_field()`, `wp_verify_nonce()`
   - Prepare database queries properly using `$wpdb->prepare()`

**Example PHP Code:**

```php
<?php
/**
 * Calculate shipping rates for a given order.
 *
 * @param array $order_data Order information including weight, dimensions, destination
 * @return array Array of shipping rate options
 * @throws ShippingApiException If API call fails
 */
public function calculateRates(array $order_data): array
{
    // Validate input
    $validated_data = $this->validateOrderData($order_data);

    // Call shipping API
    $rates = $this->api->fetchRates($validated_data);

    return $this->formatRates($rates);
}
```

### JavaScript Conventions

1. **Style Guide:** Follow Airbnb JavaScript Style Guide
2. **Naming Conventions:**
   - Variables and functions: `camelCase`
   - Classes: `PascalCase`
   - Constants: `UPPER_SNAKE_CASE`

3. **Modern JavaScript:**
   - Use ES6+ features (arrow functions, destructuring, etc.)
   - Prefer `const` and `let` over `var`
   - Use async/await for asynchronous code

4. **Documentation:**
   - Use JSDoc comments for functions and classes

### Database Conventions

1. **Table Naming:** Prefix with `{$wpdb->prefix}surecart_shipping_`
2. **Column Naming:** Use `snake_case`
3. **Indexes:** Add indexes for frequently queried columns
4. **Migrations:** Version control all database schema changes

---

## API Integration Guidelines

### Shipping Carrier APIs

When integrating with shipping carriers (USPS, FedEx, UPS, DHL, etc.):

1. **API Keys:** Store securely using WordPress options API or environment variables
2. **Rate Limiting:** Implement rate limiting and caching
3. **Error Handling:** Gracefully handle API failures with fallbacks
4. **Timeouts:** Set appropriate timeout values (10-30 seconds)
5. **Logging:** Log API requests and responses for debugging
6. **Testing:** Use sandbox/test environments during development

### SureCart Integration

1. **Hooks:** Use SureCart hooks and filters for integration points
2. **Data Models:** Follow SureCart's data structure conventions
3. **Checkout Flow:** Integrate seamlessly with SureCart checkout process
4. **Admin UI:** Match SureCart's admin interface design patterns

---

## Security Best Practices

1. **Input Validation:** Always validate and sanitize user inputs
2. **Output Escaping:** Escape all output to prevent XSS attacks
3. **SQL Injection:** Use prepared statements for all database queries
4. **CSRF Protection:** Use nonces for all form submissions
5. **Capability Checks:** Verify user permissions before sensitive operations
6. **API Security:** Secure API endpoints with authentication and authorization
7. **Data Encryption:** Encrypt sensitive data (API keys, credentials)
8. **HTTPS:** Enforce HTTPS for all API communications

---

## AI Assistant Guidelines

### When Working on This Project

1. **Read Before Editing:**
   - Always read files before making changes
   - Understand existing patterns and conventions
   - Check for similar implementations before creating new code

2. **Code Quality:**
   - Follow the coding conventions documented above
   - Write clean, maintainable, well-documented code
   - Avoid over-engineering; keep solutions simple and focused
   - Only make changes that are requested or clearly necessary

3. **Security First:**
   - Never introduce security vulnerabilities
   - Sanitize inputs and escape outputs
   - Use WordPress security functions appropriately
   - Validate and verify all user data

4. **Testing:**
   - Write tests for new functionality
   - Run existing tests before committing
   - Test edge cases and error conditions

5. **Documentation:**
   - Update documentation when making changes
   - Add inline comments for complex logic
   - Keep CLAUDE.md updated as project evolves

6. **Git Practices:**
   - Work on feature branches, not main/master
   - Write clear, descriptive commit messages
   - Push to Claude-specific branches: `claude/<session-id>`
   - Create pull requests for review

7. **WordPress Compatibility:**
   - Test with multiple WordPress versions
   - Follow WordPress coding standards
   - Use WordPress APIs and functions when available
   - Ensure compatibility with common plugins

8. **Performance:**
   - Cache API responses appropriately
   - Optimize database queries
   - Minimize HTTP requests
   - Use WordPress transients for temporary data

### Common Tasks and Patterns

#### Adding a New Shipping Carrier Integration

1. Create carrier class in `src/Api/Carriers/`
2. Implement required interface methods
3. Add configuration options in admin
4. Write unit tests for rate calculations
5. Document API requirements and setup

#### Adding Admin Settings

1. Create settings class in `src/Admin/Settings/`
2. Register settings with WordPress Settings API
3. Add validation and sanitization callbacks
4. Create admin template in `templates/admin/`
5. Enqueue necessary styles and scripts

#### Creating API Endpoints

1. Define endpoint in `src/Api/Endpoints/`
2. Register with WordPress REST API
3. Add authentication and permission checks
4. Implement request validation
5. Document endpoint in API documentation

---

## Testing Guidelines

### Unit Testing

- Test individual methods and functions in isolation
- Mock external dependencies (APIs, database, WordPress functions)
- Aim for high code coverage (80%+)
- Use descriptive test names that explain what is being tested

### Integration Testing

- Test interactions between components
- Test WordPress integration points
- Test actual API calls (in sandbox/test mode)
- Test database operations

### Manual Testing Checklist

- [ ] Test in WordPress admin interface
- [ ] Test checkout flow with various shipping options
- [ ] Test with different carrier APIs
- [ ] Test error handling and edge cases
- [ ] Test with different WordPress themes
- [ ] Test plugin activation/deactivation
- [ ] Test multisite compatibility (if applicable)

---

## Troubleshooting

### Common Issues

1. **API Connection Failures:**
   - Check API credentials
   - Verify network connectivity
   - Check for rate limiting
   - Review API logs

2. **WordPress Compatibility:**
   - Check WordPress version requirements
   - Verify plugin dependencies
   - Check for conflicting plugins
   - Review error logs

3. **Database Issues:**
   - Verify table structure
   - Check for missing indexes
   - Review query performance
   - Check for data inconsistencies

---

## Resources

### SureCart Documentation
- Official SureCart documentation (when available)
- SureCart developer resources
- SureCart API reference

### WordPress Development
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress REST API](https://developer.wordpress.org/rest-api/)

### Shipping Carrier APIs
- USPS Web Tools API
- FedEx Developer Resource Center
- UPS Developer Kit
- DHL Developer Portal

### Testing and Quality
- [PHPUnit Documentation](https://phpunit.de/)
- [PHP CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)
- [WordPress Plugin Testing](https://make.wordpress.org/cli/handbook/plugin-unit-tests/)

---

## Changelog

This section will track major changes to the project and this documentation.

### [Unreleased]
- Initial project setup
- CLAUDE.md created with comprehensive guidelines

---

## Contributing

When contributing to this project:

1. Fork the repository
2. Create a feature branch
3. Make your changes following the conventions above
4. Write/update tests
5. Submit a pull request with clear description

---

## License

[License information to be added]

---

## Contact and Support

[Contact information to be added]

---

**Last Updated:** 2026-01-19
**Document Version:** 1.0.0
**Maintainer:** AI Assistant (Claude)

---

## Notes for Future Updates

As the project develops, this document should be updated to reflect:
- Actual technology stack once confirmed
- Real directory structure as it's created
- Specific API integrations implemented
- Established patterns and conventions
- Team preferences and decisions
- Performance benchmarks and optimization notes
- Common gotchas and solutions discovered during development
