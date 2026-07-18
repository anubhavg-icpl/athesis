# Athesis

**Athesis** is a community **forum + professional blog** with an Odyssey-inspired UI: pure black, JetBrains Mono, and chatak red (`#ff0033`). Sparse chrome. End-to-end features for discussion and publishing.

> Repo / product name: **Athesis** (not “PHP Forum”).

## Stack

| Layer | Tech |
|--------|------|
| Backend | PHP 8.2+ (PDO, sessions) |
| Database | MySQL 8 |
| Frontend | Bootstrap 5 grid (restyled), custom `style.css` |
| Type | JetBrains Mono |
| Runtime | Apache (Docker) or any PHP + MySQL host |
| Design | OLED black · `#f2eeea` text · red accent · 720px content wrap |

## What you get

### Forum
- Register / login / logout / profile
- Topics list with search & sort
- View topic, create topic, edit topic / reply
- Threaded replies
- Public **hacker-style signatures** under posts
- Role helpers: user, author, moderator, admin

### Blog (Phases 1–4)
- **Write / edit** posts (draft · published · scheduled)
- Categories, tags, series (multi-part)
- SEO meta, Open Graph, canonical, reading time, views
- Comments (logged-in auto-approve; guest → moderation)
- Related posts, search, RSS, XML sitemap
- **Admin** dashboard + bulk publish / unpublish / delete
- **Media library** (image upload)
- Live preview + **revisions** (restore)
- Schedule publish (lazy cron on page load)
- Newsletter capture + subscriber list
- Share buttons, archive by month, TOC + light code highlight
- Members-only (**paywall**) posts
- Comment **moderate** queue

### Site chrome
- Static pages: about, privacy, contact
- Custom **404**
- Brand art under `public/images/brand/`
- Optional analytics: `PLAUSIBLE_DOMAIN` / `GA_MEASUREMENT_ID`

## Quick start (Docker)

```bash
git clone https://github.com/anubhavg-icpl/athesis.git
cd athesis
docker compose up -d --build
```

App: **http://localhost:8088/public/index.php**  
(Port **8088** by default; change in `docker-compose.yml` if needed.)

On first DB boot, `docker/forum_setup_docker.sql` (or `sql/forum_setup.sql`) seeds schema. Apply blog migrations if you have an older volume:

```bash
docker exec -i athesis-db-1 mysql -uforum -pforumpass php_forum < sql/migration_add_signature.sql
docker exec -i athesis-db-1 mysql -uforum -pforumpass php_forum < sql/migration_blog_phase1.sql
docker exec -i athesis-db-1 mysql -uforum -pforumpass php_forum < sql/migration_blog_phase2.sql
docker exec -i athesis-db-1 mysql -uforum -pforumpass php_forum < sql/migration_blog_phase3_4.sql
```

(Some `ALTER`s are one-shot; ignore “duplicate column” if already applied.)

### Default admin

| | |
|--|--|
| Username | `admin` |
| Password | `admin123` |

**Change this immediately.**

## Local (without Docker)

1. PHP 8.2+, MySQL 8, Apache/Nginx with `mod_rewrite` optional (pretty blog URLs).
2. Create DB and import:

   ```bash
   mysql -u root -p < sql/forum_setup.sql
   # then run migration_*.sql in order if not already in forum_setup
   ```

3. Set env or edit `config/database.php`:

   ```bash
   export DB_HOST=localhost
   export DB_NAME=php_forum
   export DB_USER=forum
   export DB_PASS=forumpass
   ```

4. Point the web root at the **repo root** (so `/public/...` resolves), or serve `public/` and adjust `BASE_PATH` detection.
5. Ensure `public/uploads/blog/` is writable for media uploads.

## Useful URLs

| Path | What |
|------|------|
| `/public/index.php` | Home |
| `/public/forum/topics.php` | Forum topics |
| `/public/blog/index.php` | Blog |
| `/public/blog/write.php` | Write / edit post (login) |
| `/public/blog/admin.php` | Blog admin |
| `/public/blog/media.php` | Media library |
| `/public/blog/moderate.php` | Comment moderation |
| `/public/blog/rss.php` | RSS |
| `/public/blog/sitemap.php` | Sitemap |
| `/public/pages/about.php` | About |
| `/blog/post/{slug}` | Pretty post URL (Apache rewrite) |

## Project layout

```
athesis/
├── config/                 # config, database, security (CSP, sessions)
├── includes/
│   ├── blog.php            # Blog helpers
│   ├── functions.php       # Auth, CSRF, sanitization
│   ├── header.php / footer.php
│   └── partials/           # e.g. newsletter
├── public/
│   ├── index.php           # Home
│   ├── auth/               # login, register, profile, logout
│   ├── forum/              # topics, view, create, edit
│   ├── blog/               # full blog surface
│   ├── pages/              # about, privacy, contact
│   ├── images/brand/       # static Odyssey-matched art
│   ├── uploads/blog/       # user media (gitignored content)
│   ├── css/style.css       # design system
│   ├── js/script.js
│   └── 404.php
├── sql/                    # schema + migrations
├── docker/                 # Apache extras, Docker seed SQL
├── docker-compose.yml
├── Dockerfile
└── README.md
```

## Design tokens (CSS)

```css
--bg: #000000;
--text: #f2eeea;
--accent: #ff0033;   /* chatak laal */
--font: "JetBrains Mono", ui-monospace, monospace;
--wrap: 720px;
```

Edit `public/css/style.css` for theme changes. Brand images live in `public/images/brand/`.

## Configuration

| Setting | Where |
|---------|--------|
| Site name / description | `config/config.php` → `SITE_NAME` = `Athesis` |
| DB | Env `DB_*` or `config/database.php` |
| Pagination | `TOPICS_PER_PAGE`, `POSTS_PER_PAGE`, etc. |
| Upload max | `MAX_UPLOAD_SIZE` |
| Analytics | `PLAUSIBLE_DOMAIN`, `GA_MEASUREMENT_ID` env vars |
| Debug display | `APP_DEBUG=1` |

## Security notes

- Passwords: bcrypt  
- CSRF on forms  
- XSS: sanitize + limited HTML on posts  
- SQL: prepared statements  
- CSP + security headers in `config/security.php`  
- Uploads restricted to images; PHP execution blocked under `uploads/blog/`  

For production: HTTPS, strong secrets, disable display_errors, dedicated DB user, backups, change default admin.

## License / intent

Built for demos, learning, and small communities. Review security and scale needs before production use.

---

**Athesis** · sparse discussions · long-form when it matters · can’t stop · won’t stop
