# CLAUDE.md

## Project overview

AlgosProj teaches data structures and algorithms (English and Arabic): lessons, visualizations,
in-browser code (Python, Java, JavaScript, C++) run against test cases, points, AI hints.
Three repos, three deployments. Flow: frontend -> backend -> sandbox.
- **algosproj-frontend**: React + TypeScript + Vite + Tailwind v4. Talks only to the backend.
- **algosproj-backend** (this repo): Laravel API. The only service that calls the sandbox.
- **algosproj-sandbox**: FastAPI, runs user code in throwaway Docker containers. Never
  public; the backend calls it with a shared secret.
Points are only awarded after server-side grading. Never trust a score sent by the client.

## This repo

- Laravel (PHP 8.3+), MySQL 8 in Docker (`algosproj-mysql`, host port 3307), Sanctum
  personal access tokens (not cookie mode: the frontend is on another domain).
- Tables: `users` (`points`, `role` user/admin/owner, `banned_at`/`ban_reason`, none fillable),
  `modules`, `lessons`, `problems` (+ `title_ar`, `body_ar`, `description_ar`; NULL =
  untranslated), `test_cases` (`is_hidden`), `submissions`, `completions`, `admin_audit_logs`,
  `user_activity_logs` (both append-only). Planned: `ai_hints`.
- Public content routes: `content-lang` middleware (`Accept-Language`, first `ar`/`ar-XX` =
  Arabic), per-field fallback (`HasArabicText`), `*_lang`, `Vary`; never `*_ar` names publicly.
- `routes/api.php` (README "API", "Admin API", "Roles", "Bans", "Logs"); plain controllers, inline
  validation. `/api/admin/*`: `auth:sanctum` + `not-banned` + `admin`, owner routes add `owner`,
  all before route binding. Owners only via `php artisan user:make-admin {email} [--owner|--remove]`.
- `.env`: `SANDBOX_URL`, `SANDBOX_SECRET`, `FRONTEND_URL` (only CORS origin). Dockerfile: README.
- Commands: `composer install`, `php artisan serve`, `migrate`, `content:sync [--dry-run]`, `test`.

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
  reality (don't append). Remove finished or untrue items; keep the file under 60 lines.
- Only record facts you verified in the repo or that I told you. Don't guess.
- Update the other sections of this file too if something in them became wrong.
- Don't commit the CLAUDE.md update unless I ask. Tell me it changed.

## Current status

- Done: auth, lessons, submit, leaderboard, rate limits, admin CRUD, roles, bans, logs, Arabic
  content (phase 2; pilot = module `basics`). 151 tests pass (SQLite, `Http::fake()`).
- Content lives in the DB. One import path, `ContentImport`: `ContentSeeder` (only if no module)
  and `content:sync` (adds missing slugs, fills empty `*_ar`, never overwrites). Arabic in
  `data/ar/*.php`; rules + terms in `data/ar/GLOSSARY.md` (I review it before more modules).
- Decisions: sandbox failure = 503, no submission stored; logs never hold code, tests or the
  secret. Ties share a rank. Code max 65536 bytes. Points from difficulty. Admin delete 409 for
  a module with lessons, a lesson with problems, a solved problem. Tests: max 50, 20,000 bytes
  each, 25,000 total, untrimmed. Admin writes call `AdminAuditLog::record()` in the transaction.
- Next: translate the other modules, AI hints. Gotchas: `docker start algosproj-mysql` first.
  Sandbox on 8001. Expected outputs come from a reference solution, matched on trimmed stdout.
