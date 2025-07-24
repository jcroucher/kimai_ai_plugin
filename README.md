# Kimai AI Plugin

An AI-powered time tracking assistant for Kimai that enables intelligent time log parsing and chat-based interactions.

## Features

- **Time Log Parser**: Parse free-form time logs into structured Kimai entries
- **AI Chat Assistant**: Interactive chat interface for time tracking questions
- **Smart Entry Creation**: Automatically detect projects, clients, and billable status
- **Preview & Confirm**: Review parsed entries before submission
- **Secure Configuration**: Encrypted API key storage

## Installation

1. Copy the `AIBundle` directory to your Kimai installation's `var/plugins/` directory:
   ```bash
   cp -r AIBundle /opt/kimai/var/plugins/
   ```

2. Copy the plugin assets to the public directory:
   ```bash
   cp -r /opt/kimai/var/plugins/AIBundle/Resources/public /opt/kimai/public/bundles/ai
   ```

3. Fix permissions:
   ```bash
   chown -R www-data:www-data /opt/kimai/var/plugins/AIBundle
   chown -R www-data:www-data /opt/kimai/public/bundles/ai
   chmod -R 755 /opt/kimai/var/plugins/AIBundle
   chmod -R 755 /opt/kimai/public/bundles/ai
   ```

4. Clear the Kimai cache:
   ```bash
   cd /opt/kimai
   bin/console cache:clear
   ```

5. The plugin should now be automatically loaded by Kimai.

## Configuration

1. Go to **Admin → AI Assistant Settings** in your Kimai interface
2. Enter your OpenAI API key (get one from [OpenAI Platform](https://platform.openai.com/api-keys))
3. Save the settings

## Usage

### AI Chat Widget

A floating chat widget appears in the bottom-right corner of all Kimai pages:

- Click the **AI Assistant** button to open the chat interface
- Switch between **Chat** and **Parse Time Log** tabs
- Ask questions about your time tracking or get general assistance

### Time Log Parsing

1. Click the AI Assistant widget
2. Switch to the **Parse Time Log** tab
3. Paste your free-form time log, for example:
   ```
   Hourly rate $100
   Monday 1/15
   9-10:30am - Client Apple. Meeting with team about new features
   2-4pm - Client Acme. Development work on user dashboard
   4:30-5pm - Code review
   
   Tuesday 1/16
   10am-12pm - Client call with Acme Corp
   1-3pm - Bug fixes for mobile app
   ```
4. Click **Parse Time Log**
5. Review the parsed entries in the preview table
6. Click **Create Entries** to add them to Kimai

### Parsing Rules

The AI parser follows these rules:
- **Default Date**: If no date specified, uses today's date
- **Time Formats**: Supports various formats (9am-5pm, 09:00-17:00, etc.)
- **Duration Only**: If only duration given, calculates from default start time
- **Billable Status**: Assumes billable unless explicitly stated otherwise
- **Default Rate**: Uses $90/hr unless specified
- **Project/Client Detection**: Attempts to identify from context

## File Structure

```
AIBundle/
├── AIBundle.php                           # Main bundle class
├── composer.json                          # Plugin metadata
├── Controller/
│   ├── AdminController.php               # Settings page
│   └── ChatController.php                # API endpoints
├── DependencyInjection/
│   └── AIExtension.php                   # Dependency injection
├── EventSubscriber/
│   └── AssetSubscriber.php               # Asset loading
├── Resources/
│   ├── config/
│   │   ├── routes.yaml                   # Route definitions
│   │   └── services.yaml                 # Service configuration
│   ├── public/
│   │   ├── css/
│   │   │   └── ai-chat.css              # Chat widget styles
│   │   └── js/
│   │       └── ai-chat.js               # Chat widget functionality
│   └── views/
│       └── admin/
│           └── settings.html.twig        # Settings page template
└── Service/
    ├── OpenAIService.php                 # OpenAI API integration
    └── TimeEntryService.php              # Kimai time entry handling
```

## API Endpoints

- `GET/POST /admin/ai` - Admin settings page
- `POST /ai/chat` - Chat with AI assistant
- `POST /ai/parse` - Parse time log text
- `POST /ai/preview` - Preview parsed entries
- `POST /ai/submit` - Submit entries to Kimai

## Requirements

- Kimai 2.0+
- PHP 8.1+
- OpenAI API key


## Troubleshooting

### Plugin Not Loading
1. Check that the bundle is in `/opt/kimai/var/plugins/AIBundle/`
2. Clear cache: `bin/console cache:clear`
3. Check Kimai logs for errors

### Chat Widget Not Appearing
1. Verify assets are copied correctly:
   ```bash
   ls -la /opt/kimai/public/bundles/ai/
   ```
   Should show `css/` and `js/` directories

2. Check browser console (F12) for JavaScript errors
3. Verify asset permissions are correct (755)
4. Try hard refresh (Ctrl+F5) to clear browser cache

### AI Not Responding
1. Verify API key is configured correctly in **Admin → AI Assistant Settings**
2. Check internet connectivity
3. Ensure OpenAI account has sufficient credits
4. Check browser console for API errors

### Parsing Issues
1. Try reformatting your time log text
2. Use clearer date/time formats
3. Check that project/client names are recognizable
4. Review the preview before submitting

### Cache Permission Issues
If you see cache permission errors:
```bash
chmod -R 777 /opt/kimai/var/cache
chmod -R 777 /opt/kimai/var/log
```

## Development

To extend or modify the plugin:

1. **Add New AI Functions**: Extend `OpenAIService` with new methods
2. **Modify UI**: Update `ai-chat.js` and `ai-chat.css`
3. **Add Routes**: Define new endpoints in `routes.yaml`
4. **Custom Parsing**: Modify the system prompt in `OpenAIService::getTimelogParsingPrompt()`

## License

MIT License - see LICENSE file for details

## Support

For issues and feature requests, please use the GitHub repository issue tracker.