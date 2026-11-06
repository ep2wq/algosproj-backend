# CLAUDE.md

## Project overview

AlgosLab teaches data structures and algorithms (English and Arabic): lessons, visualizations,
in-browser code (Python, Java, JavaScript, C++) run against test cases, points, AI hints.
Three repos, three deployments. Flow: frontend -> backend -> sandbox.
- **algosproj-frontend**: React + TypeScript + Vite + Tailwind v4. Talks only to the backend.
- **algosproj-backend** (this repo): Laravel API. The only service that calls the sandbox.
- **algosproj-sandbox**: FastAPI, runs user code in throwaway Docker containers. Never
  public; the backend calls it with a shared secret.
Points are only awarded after server-side grading. Never trust a score sent by the client.

## This repo

- Laravel (PHP 8.3+), MySQL 8 in Docker (`algosproj-mysql`, host port 3307), Sanctum
  cookie sessions (SPA mode, `auth:web`, no API tokens; README "Sessions"). Frontend and API must
  share a registrable domain.
- Tables: `users` (`points`, `role` user/admin/owner, `banned_at`/`ban_reason`, none fillable),
  `modules`, `lessons`, `problems` (+ `title_ar`, `body_ar`, `description_ar`; NULL =
  untranslated; problems also `created_at`), `test_cases` (`is_hidden`, `position`), `submissions`,
  `completions`, `admin_audit_logs`, `user_activity_logs` (both append-only), `security_events`,
  `banned_ips`, `bug_reports`. Keys are UUIDv7
  strings (`CHAR(36)`, `HasUuids`), FKs `foreignUuid`. Planned: `ai_hints`.
- Language: global `SetContentLanguage` (first `ar`/`ar-XX` = Arabic locale, `Vary` always). Text
  falls back per field (`HasArabicText`, `*_lang`, no `*_ar` publicly). Messages only from `lang/`
  (README "API messages"; `ar/validation.php`, `ar.json`; admin keys English only).
- `routes/api.php` (README "API", "Admin API", "Roles", "Bans", "Logs"); plain controllers, inline
  validation. `/api/admin/*`: `auth:web` + `not-banned` + `admin`, owner routes add `owner`,
  all before route binding. Owners only via `php artisan user:make-admin {email} [--owner|--remove]`.
- `.env`: `SANDBOX_URL`, `SANDBOX_SECRET`, `FRONTEND_URL` (only CORS origin),
  `SANCTUM_STATEFUL_DOMAINS`, `SESSION_*`, `AUTH_REMEMBER_MINUTES` (README "Sessions"). Commands: `composer
  install`, `php artisan serve`, `migrate`, `content:sync [--dry-run]`, `test`. Dockerfile: README.
- No web pages, npm or Vite: `routes/web.php` holds only the social login redirect/callback (`GET /` is 404), health is `/up`. The only
  views are the two email templates in `resources/views/mail/` (README "Email").

## Working rules

- Keep code simple. Don't add abstractions, patterns or dependencies I didn't ask for.
- Don't do extra work beyond the request. If you spot something worth fixing, mention it
  instead of changing it.
- Ask before adding a new dependency.
- Never add Co-Authored-By or Claude-Session lines, or any other attribution, to commit
  messages or PR descriptions. ep2wq is the only author.

## Session routine

- At the start of a session, read this file and "Current status" before anything else.
- Before ending a session, or when I say we're done, rewrite "Current status" to match
  reality (don't append). Remove finished or untrue items; keep the file concise.
- Only record facts you verified in the repo or that I told you. Don't guess.
- Update the other sections of this file too if something in them became wrong.
- Don't commit the CLAUDE.md update unless I ask. Tell me it changed.

## Current status

- Done: auth, lessons, submit, leaderboard, limits, admin, roles, bans, logs, Arabic text+messages.
- Done: register requires `accepted_terms` (`bail|required|accepted|boolean`; only `true`, `1`, `"1"`
  pass). `users.terms_accepted_at` and `users.terms_version` are set only by register, not
  mass-assignable, hidden from user JSON and `/api/me`; existing users stay NULL. Run the migration
  `2026_10_28_100000_add_terms_acceptance_to_users_table` (`php artisan migrate`) on every database.
- Done: email verification + password reset (README "Email"). Mail is sent after the response
  (`defer()`, no queue), failures logged without address or token; `log` mailer locally, Resend SMTP
  in production. Only submit needs a verified email (`verified-email`, 403 `reason: email_unverified`);
  responses carry `email_verified` (boolean), `email_verified_at` is hidden. `user:make-admin`
  verifies. No migration (the users table already had both).
- Done: Google + GitHub login (README "Social login", `laravel/socialite` ^5.31, `SocialLoginController`).
  `GET /auth/{google|github}/redirect|callback` in `routes/web.php` (the web group always has a session;
  the callback comes from the provider, so Sanctum's Origin check would give none), PKCE + state, 30/min
  per IP. Callback: denied/failed/no verified email -> `FRONTEND_URL/login?error=social_*`; linked or
  same verified email -> log in (banned -> `social_banned`); same unverified email -> `social_email_exists`,
  never linked; new email -> pending in the session (15 min) -> `/auth/complete`, then `GET /api/auth/pending`
  and `POST /api/auth/complete` (Terms like register, role user, verified, password NULL; 410 without a
  pending one). `social_accounts` (no tokens), `users.password` nullable. Env `GOOGLE_*`, `GITHUB_*`.
  Owner: run the two 2026_10_29 migrations, create both OAuth apps, set the env values, test a real login.
- Done: performance pass (`docs/performance-backend.md` in the workspace). Migration
  `2026_11_03_100000_add_admin_filter_indexes` (activity email/event, users role): run `php artisan migrate`.
  `/api/challenges` list cached 60 s per language (`solved` per request; `content:sync` forgets it,
  admin edits show after <= 60 s); ETag + 304 on `/api/modules` and `/api/lessons/{slug}`;
  `Model::preventLazyLoading` outside production.
- Done (security audit 2026-11-03, `docs/security-audit.md` in the workspace): login limiter no longer
  500s on a non-string email; production refuses `MAIL_MAILER=log|array` at boot; `DatabaseSeeder` makes the
  Test User only in local/testing; global `AddSecurityHeaders` (nosniff, Referrer-Policy); `activity:prune`
  scheduled daily (host needs the `schedule:run` cron). Audit fixes: submit to a problem with no tests is
  422 `reason: no_tests` (message `runner_unavailable`, nothing stored, sandbox not called); a sandbox
  `passed` without a passing result per test is a 503; the mail guard also checks failover/roundrobin lists
  by transport. Tests: 446, pass on SQLite and on MySQL
  (`assertSameJson` in `tests/TestCase.php` for JSON key order). symfony/http-foundation and symfony/mime
  advisories fixed (Symfony 8.1.10, Laravel 13.39.0; `composer audit` clean). Symfony 8.1 needs PHP >= 8.4.1:
  rebuild the image with `docker build --pull`.
- Done (2026-11-05): security events and IP bans (README "Security events and IP bans"). Tables
  `security_events`, `banned_ips` (migrations `2026_11_05_100000`/`100100`: run `php artisan migrate`);
  `SecurityEvent::record()` (aggregates per IP+type for 10 min, 200 new rows per IP a day, never breaks
  the request); global `BlockBannedIps` (403 `Forbidden.` before the session, cached list 60 s, fails open)
  and `RecordSecurityEvents` (401 admin, 419, 429, decoy 404s, suspicious query/auth input); honeypot
  `form_extra` on login/register/forgot (answers like a normal failure); owner API under
  `/api/admin/security/*`; `security:prune` daily. `TRUSTED_PROXIES` env (`App\Http\Middleware\TrustProxies`):
  set it behind Cloudflare. Tests: 478 on SQLite and MySQL.
- Done (2026-11-06): bug reports (README "Bug reports"). Table `bug_reports` (migration `2026_11_06_100000`:
  run `php artisan migrate`), public `POST /api/bug-reports` (multipart, 5/h per IP and per account, honeypot
  `form_extra` = fake 201, server-side sanitizing and redaction, optional parts only with their `included` flag,
  no IP stored), screenshot PNG/WebP/JPEG by content, max 1.5 MB and 4000 px, on the private `local` disk; owner
  API under `/api/admin/bug-reports*` (status/delete audited); `bugreports:prune` daily (`config/bug_reports.php`).
  Host: PHP upload_max_filesize/post_max_size >= 3M. Tests: 498 on SQLite and MySQL.
- Content lives in the DB. One import path, `ContentImport`: `ContentSeeder` (only if no module)
  and `content:sync` (adds missing slugs, fills empty `*_ar`, never overwrites). Arabic (in
  `data/ar/*.php`, a test requires it per slug): README "Content languages", `data/ar/GLOSSARY.md`.
- Decisions: sandbox failure = 503, no submission stored; logs never hold code, tests or the
  secret. Ties share a rank. Code max 65536 bytes. Points from difficulty. Admin delete 409 for
  a module with lessons, a lesson with problems, a solved problem. Tests: max 50, 20,000 bytes
  each, 25,000 total, untrimmed. Admin writes call `AdminAuditLog::record()` in the transaction.
  Ids are UUIDv7 strings: never order by id or parse it as a number (explicit columns: `position`,
  `created_at`, `sort_order`; id only breaks ties). Migrations were rewritten in place: an old DB
  needs `migrate:fresh` + `content:sync`.
- Gotchas: `docker start algosproj-mysql` first. Sandbox on 8001. Expected outputs come from a
  reference solution, matched on trimmed stdout. Next: AI hints.
