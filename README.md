# Ask Gemini (PHP + Docker)
 
A small question-and-answer web page built with PHP 8.3, Docker and the [Gemini API](https://ai.google.dev/). Type a question, get a formatted answer.
 
## Features
 
- Simple chat-style page with header, footer, light and dark mode
- Answers rendered from Markdown (bold, lists, code, tables)
- Tries several Gemini models in order, so one busy or rate-limited model does not break the page
- API key and model list kept in `.env`, never in code
- Copy button on every answer
## Requirements
 
- Docker Desktop (Windows, macOS or Linux)
- A Gemini API key from [Google AI Studio](https://aistudio.google.com/)
## Quick start
 
1. Clone the repository and open the folder.
2. Create a `.env` file next to `compose.yaml`:
```
GEMINI_API_KEY=your_api_key_here
GEMINI_MODELS=gemini-3.5-flash-lite,gemini-3.1-flash-lite,gemini-3.6-flash
```
 
   `GEMINI_MODELS` is optional. Models are tried in the order listed.
 
3. Build and start the container:
```bash
docker compose up -d --build
```
 
4. Install the PHP dependencies (first time only):
```bash
docker compose exec php composer require gemini-api-php/client guzzlehttp/guzzle erusev/parsedown
```
 
5. Open <http://localhost:8088/chat.php>.
## Project structure
 
```
.
├── chat.php        # Question and answer page
├── index.php       # Minimal "Hello" test script
├── compose.yaml    # Docker Compose service (port 8088)
├── Dockerfile      # PHP 8.3 + Apache + Composer
├── .env            # Your API key (not committed)
└── vendor/         # Composer packages (not committed)
```
 
## Configuration
 
| Variable         | Required | Description                                          |
| ---------------- | -------- | ---------------------------------------------------- |
| `GEMINI_API_KEY` | Yes      | Your Gemini API key                                  |
| `GEMINI_MODELS`  | No       | Comma-separated model names, tried in order          |
 
After changing `.env`, run `docker compose up -d` again.
 
## Troubleshooting
 
| Problem | Cause and fix |
| ------- | ------------- |
| `vendor/autoload.php` not found | Run the Composer command in step 4 |
| `404` with "no longer available" | The model is retired. Use a current name in `GEMINI_MODELS` |
| `429` quota exceeded | Free-tier daily limit reached for that model. Use another model, wait for the reset, or enable billing |
| `503` high demand | Temporary Google overload. Try again shortly |
| Timeout | Slow or blocked network from Docker. Try again or use another model |
 
Model availability changes often. Check which models your key can use in [Google AI Studio](https://aistudio.google.com/).
 
## Security
 
- Never commit your API key. Add this to `.gitignore`:
```
.env
vendor/
```
 
- If a key was ever pasted into code, chat or a public repo, delete it in Google AI Studio and create a new one.
- Answers are escaped through Parsedown safe mode, and questions are HTML-escaped before display.
- This page has no login. Do not expose it publicly without adding authentication and rate limiting, since anyone could use your API quota.
## License
 
MIT. Add a `LICENSE` file if you want to publish under this license.
 