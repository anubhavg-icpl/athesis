# CLAUDE.md

Project instructions for Claude Code / AI agents. See also [AGENTS.md](AGENTS.md) (same
content, tool-neutral).

## What this is

PHP + MySQL discussion forum. Server-rendered PHP, Bootstrap 5 frontend, PDO data layer.
Educational / small-community scale. Full feature list in [README.md](README.md).

## Stack

- PHP 7.4+ (8.0+ target), MySQL 8, PDO + prepared statements
- Bootstrap 5.3, vanilla ES6 (`public/js/script.js`)
- Apache in Docker (`Dockerfile`, `docker/apache-security.conf`)

## Run it

```bash
cp .env.example .env      # change every value before any real deploy
docker compose up --build # web on http://localhost:8088, MySQL 8 seeded from sql/
```

Schema: `sql/forum_setup.sql` (auto-seeded on first DB boot) + `sql/migration_add_signature.sql`.
Default admin `admin` / `admin123` — change immediately.

## Layout

- `config/` — `config.php` (site), `database.php` (PDO, reads `DB_*` env), `security.php` (helpers)
- `includes/` — `functions.php`, `header.php`, `footer.php`
- `public/auth/` — login, register, logout, profile
- `public/forum/` — topics, view_topic, create_topic, edit_topic, edit_reply
- `index.php` → redirects into `public/`

## Non-negotiable conventions (this is a security-first forum — don't regress it)

- **DB**: every query is a PDO prepared statement with bound params. No string interpolation into SQL, ever.
- **Output**: escape all user data on output with `htmlspecialchars(...)` (XSS).
- **Forms**: every state-changing POST carries a CSRF token; verify it server-side (`config/security.php`).
- **Passwords**: `password_hash()` / `password_verify()` (bcrypt). Never log or echo them.
- **Config**: DB creds come from `DB_*` env (see `docker-compose.yml`), not hardcoded. Secrets stay out of git.
- Match existing file style; keep changes minimal and scoped.

## Cloudflare MCP servers (`.mcp.json`)

All 14 official Cloudflare remote MCP servers are wired up for Claude Code. First use of a
non-public server opens a browser OAuth login to your Cloudflare account; approve the project
MCP servers when Claude Code prompts. Requires Node (`npx`).

| Server | Use for |
|---|---|
| `cloudflare-docs` | Cloudflare reference docs (public, no auth) |
| `cloudflare-blog` | Search the Cloudflare blog (public, no auth) |
| `cloudflare-bindings` | Workers + KV/R2/D1/AI bindings |
| `cloudflare-builds` | Workers Builds insight/management |
| `cloudflare-observability` | App logs & analytics debugging |
| `cloudflare-containers` | Sandbox dev environments |
| `cloudflare-browser` | Fetch pages → markdown, screenshots |
| `cloudflare-logpush` | Logpush job health |
| `cloudflare-ai-gateway` | AI Gateway logs, prompts/responses |
| `cloudflare-auditlogs` | Audit-log queries & reports |
| `cloudflare-dns-analytics` | DNS performance/debugging |
| `cloudflare-dex` | Digital Experience Monitoring |
| `cloudflare-casb` | Cloudflare One CASB misconfig checks |
| `cloudflare-graphql` | Cloudflare GraphQL analytics API |

Source: https://github.com/cloudflare/mcp-server-cloudflare
