# WriteFlow AI

Real-time AI writing suggestions in the WordPress Block Editor using OpenAI's GPT models.

## Features

- **AI Suggestions** — One-click suggestions for paragraph and heading content powered by OpenAI
- **Streaming responses** — AI-generated text appears progressively in the editor (no loading spinner after first character)
- **Block Editor integration** — Toolbar button on supported blocks
- **Efficient caching** — 30-minute transient cache reduces API calls for identical content
- **Secure** — Uses WordPress nonce middleware, requires `edit_posts` capability

## Requirements

- PHP 8.2+
- WordPress 6.9+
- OpenAI API key

## Setup

### 1. Install

```bash
wp plugin activate writeflow-ai
```

### 2. Configure API Key

Set your OpenAI API key in `wp-config.php`:

```php
define( 'WRITEFLOW_AI_API_KEY', 'sk-...' );
```

Or via WordPress admin settings (Settings → WriteFlow AI → API Key).

### 3. Use

1. Create or edit a post/page
2. Add or select a **Paragraph** or **Heading** block
3. Click **AI Suggest** in the toolbar
4. Modal opens and text streams live from OpenAI
5. Accept, Reject, or Regenerate

## API Endpoints

### Non-streaming (legacy)
```
POST /wp-json/ai/v1/suggest
Content-Type: application/json
X-WP-Nonce: <nonce>

{ "content": "Your text here" }
```

Response:
```json
{
  "success": true,
  "data": { "suggestion": "Improved text..." }
}
```

### Streaming (recommended)
```
POST /wp-json/ai/v1/suggest-stream
Content-Type: application/json
X-WP-Nonce: <nonce>

{ "content": "Your text here" }
```

Streams plain-text chunks as they arrive from OpenAI. If an error occurs before streaming starts, the response begins with `[WRITEFLOW_STREAM_ERROR]:message`.

## Development

### Setup local environment

```bash
npm install
composer install
npm run build
```

### Run dev server

```bash
npm run dev
```

### PHP Code Standards

```bash
composer run lint
```

### Tests

```bash
composer run test:php
npm run test:js
```

## Architecture

- **Backend** — WordPress REST API with OpenAI integration via cURL (for streaming support)
- **Frontend** — React HOC wrapping core/paragraph and core/heading block edit components
- **Caching** — WordPress transients with content hash + model version keys

## License

GPLv2 or later. See [LICENSE](LICENSE) for details.
