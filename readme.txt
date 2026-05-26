=== WriteFlow AI ===
Contributors:      Dhruvik Malaviya
Tags:              ai, openai, suggestions, block-editor, gutenberg, writing
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Requires PHP:      8.2
Requires at least: 6.9
Tested up to:      6.9.1
Stable tag:        1.0.0

Real-time AI writing suggestions in the Block Editor, powered by OpenAI.

== Description ==

WriteFlow AI brings intelligent writing assistance directly into the WordPress Block Editor. Get instant suggestions to improve your content clarity and quality.

**Features:**

- AI-powered suggestions for paragraph and heading blocks
- Streaming responses — see suggestions appear in real-time
- One-click accept/reject/regenerate workflow
- Intelligent caching to minimize API costs
- WordPress nonce-protected REST API
- Requires `edit_posts` capability

**Requirements:**

- OpenAI API key (set via `WRITEFLOW_AI_API_KEY` constant or Settings page)
- PHP 8.2+
- WordPress 6.9+

== Getting Started ==

1. Install and activate the plugin
2. Go to Settings → WriteFlow AI
3. Enter your OpenAI API key
4. In the Block Editor, click "AI Suggest" on any paragraph or heading block
5. Review the suggestion and accept, reject, or regenerate

== Frequently Asked Questions ==

= Do I need an OpenAI account? =
Yes. Sign up at https://openai.com and create an API key in the dashboard.

= Which blocks are supported? =
Currently, paragraph and heading (core/paragraph, core/heading) blocks.

= Is my content sent to OpenAI? =
Yes. The block content is sent to OpenAI's API for processing. Review OpenAI's privacy policy before using this plugin.

= How is the response streamed? =
The plugin uses native HTTP streaming (ReadableStream) on the frontend and cURL streaming on the backend. Text appears progressively without waiting for the full response.

= What about costs? =
OpenAI charges per token used. Suggestions are cached for 30 minutes to reduce API calls on identical content.

== Screenshots ==

1. AI Suggest toolbar button in the Block Editor
2. Modal showing real-time suggestion with Accept/Reject buttons
3. Settings page to configure OpenAI API key

== Changelog ==

For the full changelog, see https://github.com/rtCamp/writeflow-ai/blob/main/CHANGELOG.md

== Upgrade Notice ==

No breaking changes in 1.0.0.

== License ==

This plugin is licensed under the GPLv2 or later. See LICENSE file for details.
