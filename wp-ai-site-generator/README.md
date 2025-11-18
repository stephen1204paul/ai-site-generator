# WP AI Site Generator

An AI-powered WordPress plugin that generates complete websites through natural language conversations. Build professional websites with AI-generated content, images, and layouts - all through an intuitive chat interface.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Development Setup](#development-setup)
- [Build Commands](#build-commands)
- [Testing](#testing)
- [Project Structure](#project-structure)
- [Configuration](#configuration)
- [Contributing](#contributing)
- [License](#license)

## Features

- 🤖 **AI-Powered Generation**: Generate complete websites using OpenAI, Anthropic Claude, or Google Gemini
- 💬 **Conversational Interface**: Build websites through natural chat conversations
- 🎨 **Smart Theme Integration**: Works seamlessly with Astra, Kadence, and Twenty Twenty Five themes
- 📝 **Content Generation**: AI-generated pages, posts, and custom content types
- 🖼️ **Image Generation**: Integrated with DALL-E, Stability AI, and stock photo APIs
- 🎯 **SEO Optimization**: Built-in SEO best practices and schema markup
- 📱 **Responsive Design**: Mobile-first, fully responsive layouts
- 🔧 **Gutenberg Blocks**: Custom blocks for AI-generated content sections
- 🌐 **Multi-language Support**: Generate content in multiple languages
- 📊 **Analytics Ready**: Pre-configured for Google Analytics and other trackers

## Requirements

### System Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher (PHP 8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Node.js 18.0.0 or higher
- npm 9.0.0 or higher
- Composer 2.0 or higher

### Required WordPress Plugins

- None (standalone plugin)

### Recommended Themes

- Astra Theme
- Kadence Theme
- Twenty Twenty Five
- Any FSE (Full Site Editing) compatible theme

## Installation

### From WordPress Admin

1. Download the latest release `.zip` file
2. Go to **Plugins → Add New** in your WordPress admin
3. Click **Upload Plugin** and select the downloaded file
4. Click **Install Now** and then **Activate**
5. Go to **AI Site Generator** in the admin menu to configure

### Manual Installation

```bash
# Navigate to your WordPress plugins directory
cd wp-content/plugins/

# Clone the repository
git clone https://github.com/wpaisg/wp-ai-site-generator.git

# Navigate to the plugin directory
cd wp-ai-site-generator

# Install dependencies
npm install
composer install

# Build assets
npm run build

# Activate the plugin via WordPress admin or WP-CLI
wp plugin activate wp-ai-site-generator
```

## Development Setup

### Prerequisites

1. Install [Node.js](https://nodejs.org/) (v18.0.0+)
2. Install [Composer](https://getcomposer.org/)
3. Install [WP-CLI](https://wp-cli.org/) (optional but recommended)
4. Install [Docker](https://www.docker.com/) (for local WordPress environment)

### Quick Start

```bash
# Clone the repository
git clone https://github.com/wpaisg/wp-ai-site-generator.git
cd wp-ai-site-generator

# Install dependencies
npm install
composer install

# Copy environment variables
cp .env.example .env
# Edit .env and add your API keys

# Start WordPress development environment
npm run env:start

# Install test themes and plugins
npm run env:install-plugins

# Start development build with watch
npm run dev

# Access your local site at http://localhost:8888
```

### Environment Setup

The plugin uses `@wordpress/env` for local development. The environment is configured in `.wp-env.json`.

```bash
# Start the environment
npm run env:start

# Stop the environment
npm run env:stop

# Reset the environment (destroys all data)
npm run env:reset

# Access WordPress CLI
npm run wp <command>
# Example: npm run wp plugin list
```

### Development Workflow

1. **Start the environment**: `npm run env:start`
2. **Watch for changes**: `npm run watch`
3. **Make your changes** in the `src/` directory
4. **Test your changes** at `http://localhost:8888`
5. **Run tests**: `npm test`
6. **Build for production**: `npm run build:production`

## Build Commands

### Development

```bash
# Start development server with hot reload
npm run dev

# Watch files for changes
npm run watch

# Start webpack dev server only
npm start

# Analyze bundle size
npm run analyze
```

### Production

```bash
# Build for production
npm run build

# Build with internationalization
npm run build:production

# Create plugin ZIP file
npm run plugin-zip
```

### Code Quality

```bash
# Run all linters
npm run lint

# Lint JavaScript files
npm run lint:js

# Lint CSS/SCSS files
npm run lint:css

# Format code with Prettier
npm run format

# Check PHP coding standards
composer run-script phpcs

# Fix PHP coding standards
composer run-script phpcbf
```

### Testing

```bash
# Run all tests
npm test

# JavaScript unit tests
npm run test:unit
npm run test:unit:watch
npm run test:unit:coverage

# E2E tests
npm run test:e2e
npm run test:e2e:watch
npm run test:e2e:debug

# PHP unit tests
composer run-script test
composer run-script test:unit
composer run-script test:integration

# Cypress tests
npm run test:cypress
npm run test:cypress:open
```

### Storybook

```bash
# Start Storybook development server
npm run storybook

# Build Storybook static site
npm run build-storybook
```

## Project Structure

```
wp-ai-site-generator/
├── admin/               # Admin interface files
│   ├── css/            # Admin styles
│   ├── js/             # Admin scripts
│   ├── partials/       # Admin view templates
│   └── settings/       # Settings pages
├── api/                # REST API endpoints
│   ├── routes/         # API route definitions
│   └── controllers/    # API controllers
├── blocks/             # Gutenberg blocks
│   ├── hero/          # Hero section block
│   ├── features/      # Features grid block
│   ├── testimonials/  # Testimonials block
│   └── ...            # Other blocks
├── build/             # Compiled assets (git ignored)
├── database/          # Database operations
│   ├── migrations/    # Database migrations
│   ├── models/        # Data models
│   └── schema/        # Database schema
├── generators/        # Content generators
│   ├── content/       # Content generation
│   ├── images/        # Image generation
│   └── themes/        # Theme customization
├── includes/          # Core plugin files
│   ├── class-*.php    # PHP classes
│   └── helpers.php    # Helper functions
├── languages/         # Translation files
├── providers/         # AI provider integrations
│   ├── openai/        # OpenAI integration
│   ├── anthropic/     # Claude integration
│   └── google/        # Gemini integration
├── public/            # Frontend files
│   ├── css/          # Frontend styles
│   └── js/           # Frontend scripts
├── src/              # Source files for build
│   ├── admin/        # React admin components
│   ├── blocks/       # Block source files
│   ├── components/   # Shared React components
│   ├── hooks/        # Custom React hooks
│   ├── utils/        # Utility functions
│   └── styles/       # Global styles
├── tests/            # Test files
│   ├── unit/         # Unit tests
│   ├── integration/  # Integration tests
│   ├── e2e/          # End-to-end tests
│   └── fixtures/     # Test fixtures
├── .env.example      # Environment variables template
├── .eslintrc.json    # ESLint configuration
├── .gitignore        # Git ignore rules
├── .phpcs.xml.dist   # PHP CodeSniffer rules
├── .wp-env.json      # WordPress environment config
├── composer.json     # PHP dependencies
├── package.json      # Node dependencies
├── phpunit.xml.dist  # PHPUnit configuration
├── README.md         # Documentation
├── webpack.config.js # Webpack configuration
└── wp-ai-site-generator.php # Main plugin file
```

## Configuration

### Environment Variables

Copy `.env.example` to `.env` and configure:

```bash
# Required API Keys
OPENAI_API_KEY=your-openai-api-key
ANTHROPIC_API_KEY=your-anthropic-api-key
GOOGLE_AI_API_KEY=your-google-ai-api-key

# Optional Services
DALLE_API_KEY=your-dalle-api-key
STABILITY_API_KEY=your-stability-api-key
UNSPLASH_ACCESS_KEY=your-unsplash-key

# Development Settings
WP_ENVIRONMENT_TYPE=development
AI_SITE_GENERATOR_DEBUG=true
```

### WordPress Configuration

Add to your `wp-config.php` for development:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );
define( 'AI_SITE_GENERATOR_DEBUG', true );
```

### Plugin Settings

After activation, configure the plugin at:
**WordPress Admin → AI Site Generator → Settings**

Key settings include:
- AI Provider selection
- API key configuration
- Content generation limits
- Theme preferences
- Image generation settings

## API Documentation

### REST API Endpoints

The plugin provides REST API endpoints for integration:

```
GET    /wp-json/wpaisg/v1/status
POST   /wp-json/wpaisg/v1/generate
GET    /wp-json/wpaisg/v1/templates
POST   /wp-json/wpaisg/v1/chat
DELETE /wp-json/wpaisg/v1/session/{id}
```

### JavaScript API

```javascript
// Access the plugin's JavaScript API
const wpAiSiteGenerator = window.wpAiSiteGenerator;

// Generate content
wpAiSiteGenerator.generateContent({
    prompt: 'Create a homepage for a bakery',
    type: 'page',
    model: 'gpt-4'
}).then(response => {
    console.log(response);
});
```

### PHP Hooks

```php
// Filter generated content
add_filter('wpaisg_generated_content', function($content, $context) {
    // Modify content
    return $content;
}, 10, 2);

// Action after site generation
add_action('wpaisg_site_generated', function($site_data) {
    // Custom logic
});
```

## Troubleshooting

### Common Issues

1. **Build fails with "Cannot find module"**
   ```bash
   rm -rf node_modules package-lock.json
   npm install
   ```

2. **WordPress environment won't start**
   ```bash
   npm run env:stop
   npm run env:clean
   npm run env:start
   ```

3. **PHP coding standards errors**
   ```bash
   composer run-script phpcbf
   ```

4. **Tests failing locally**
   ```bash
   npm run env:reset
   npm test
   ```

### Debug Mode

Enable debug mode in `.env`:
```env
AI_SITE_GENERATOR_DEBUG=true
AI_SITE_GENERATOR_LOG_LEVEL=debug
LOG_API_REQUESTS=true
```

Check logs at: `wp-content/debug.log`

## Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

### Development Process

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Make your changes
4. Run tests: `npm test && composer test`
5. Commit with conventional commits: `git commit -m "feat: add new feature"`
6. Push to your fork: `git push origin feature/my-feature`
7. Submit a Pull Request

### Coding Standards

- JavaScript: [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
- PHP: [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- CSS: [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)
- React: Use functional components with hooks
- Documentation: JSDoc for JavaScript, PHPDoc for PHP

## Support

- 📚 [Documentation](https://docs.wpaisg.com)
- 💬 [Discord Community](https://discord.gg/wpaisg)
- 🐛 [Issue Tracker](https://github.com/wpaisg/wp-ai-site-generator/issues)
- 📧 [Email Support](mailto:support@wpaisg.com)

## License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Credits

Developed by the WP AI Site Generator Team.

Special thanks to all [contributors](https://github.com/wpaisg/wp-ai-site-generator/graphs/contributors) who have helped make this project better.

---

Made with ❤️ for the WordPress community