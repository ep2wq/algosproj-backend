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
  `completions`, `admin_audit_logs`, `user_activity_logs` (both append-only). Keys are UUIDv7
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
- No web pages, npm or Vite: `routes/web.php` is empty (`GET /` is 404), health is `/up`. The only
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
  Tests: 381.
- Done: email verification + password reset (README "Email"). Mail is sent after the response
  (`defer()`, no queue), failures logged without address or token; `log` mailer locally, Resend SMTP
  in production. Only submit needs a verified email (`verified-email`, 403 `reason: email_unverified`);
  responses carry `email_verified` (boolean), `email_verified_at` is hidden. `user:make-admin`
  verifies. No migration (the users table already had both).
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
