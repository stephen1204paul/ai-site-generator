# WP AI Site Generator

A powerful WordPress plugin that generates complete multi-page websites using AI, featuring a conversational chat interface and support for multiple AI providers.

## Features

- 🎨 **Complete Website Generation**: Generate entire multi-page websites (Home, About, Contact, etc.) with AI
- 💬 **Conversational Interface**: Chat with AI to create and refine your website design
- 🤖 **Multiple AI Providers**: Support for OpenAI, Anthropic, Cohere, and more
- 🎯 **Block Editor Native**: Full integration with WordPress Block Editor
- 🎭 **Theme Compatibility**: Works with popular themes (Astra, Kadence, Twenty Twenty-Five, Ollie)
- 🔄 **Section Regeneration**: Regenerate specific sections without affecting the rest
- 🎪 **Design Refinement**: Continue chatting with AI to refine your design after generation
- 📱 **Responsive Layouts**: All generated designs are mobile-friendly
- 🔒 **Secure**: Encrypted API key storage and secure communication

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Block Editor enabled
- At least one supported theme installed

## Supported Themes

- Astra
- Kadence
- Twenty Twenty-Five
- Ollie
- Any Block-based theme

## Installation

1. Download the plugin zip file
2. Navigate to WordPress Admin > Plugins > Add New
3. Click "Upload Plugin" and select the zip file
4. Activate the plugin
5. Configure your AI provider settings

## Quick Start

1. **Configure AI Provider**:
   - Go to `AI Site Generator > Settings`
   - Select your preferred AI provider
   - Enter your API key
   - Save settings

2. **Start Generating**:
   - Navigate to `AI Site Generator > Chat`
   - Describe the website you want to create
   - Click Send and watch the AI work

3. **Refine Your Design**:
   - Continue chatting to make adjustments
   - Regenerate specific sections as needed
   - Preview your pages as you go

## Documentation

- [Technical Plan](docs/TECHNICAL_PLAN.md) - Complete technical architecture
- [Implementation Examples](docs/IMPLEMENTATION_EXAMPLES.md) - Code examples and patterns
- [Development Roadmap](docs/DEVELOPMENT_ROADMAP.md) - Development timeline and setup

## Project Structure

```
wp-ai-site-generator/
├── includes/           # Core plugin functionality
├── admin/             # Admin interface and assets
├── api/               # REST API endpoints
├── providers/         # AI provider implementations
├── generators/        # Block and page generators
├── database/          # Database operations
├── assets/            # Public assets
├── languages/         # Translations
└── tests/             # Test suites
```

## Development

### Setup Development Environment

```bash
# Clone repository
git clone https://github.com/yourusername/wp-ai-site-generator.git
cd wp-ai-site-generator

# Install dependencies
composer install
npm install

# Start development environment
npm run env:start
npm start
```

### Run Tests

```bash
# PHP Unit tests
composer test

# JavaScript tests
npm test

# E2E tests
npm run test:e2e
```

## API Providers

### Currently Supported
- ✅ OpenAI (GPT-4, GPT-4 Turbo)
- ✅ Anthropic (Claude 3.5 Sonnet, Claude 3.5 Haiku)
- 🔧 Cohere (Command R+)
- 🔧 Google (Gemini Pro)

### Coming Soon
- Hugging Face
- Local LLMs via Ollama
- Custom endpoints

## Architecture Highlights

### Provider Abstraction
All AI providers implement a common interface, making it easy to switch between providers or add new ones.

### Block-First Approach
The plugin generates native WordPress blocks, ensuring compatibility and editability.

### Conversational Memory
The chat system maintains context across conversations, understanding your design preferences and requirements.

### Theme Adaptation
Generated designs automatically adapt to your active theme's styling and structure.

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### Development Workflow

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Write/update tests
5. Submit a pull request

## Roadmap

### Version 1.0 (Current)
- ✅ Core plugin architecture
- ✅ Multiple AI provider support
- ✅ Block Editor integration
- ✅ Chat interface
- ✅ Section regeneration

### Version 1.1 (Planned)
- [ ] Elementor support
- [ ] Divi builder support
- [ ] Custom CSS generation
- [ ] SEO optimization
- [ ] Template library

### Version 2.0 (Future)
- [ ] Visual editing mode
- [ ] A/B testing
- [ ] Analytics integration
- [ ] Marketplace for templates
- [ ] White-label options

## Support

- 📖 [Documentation](https://docs.example.com)
- 💬 [Community Forum](https://forum.example.com)
- 🐛 [Issue Tracker](https://github.com/yourusername/wp-ai-site-generator/issues)
- 📧 [Email Support](mailto:support@example.com)

## License

GPL v2 or later - see [LICENSE](LICENSE) file for details.

## Credits

Created by [Your Name](https://yourwebsite.com)

### Special Thanks
- WordPress Core Contributors
- Block Editor Team
- AI Provider Partners
- Beta Testers Community

## Changelog

### Version 1.0.0 (2024-01-01)
- Initial release
- Core functionality implemented
- Support for OpenAI and Anthropic
- Block Editor integration
- Chat interface
- Section regeneration

---

**Note**: This plugin requires an API key from at least one supported AI provider. API usage may incur costs based on your provider's pricing.
