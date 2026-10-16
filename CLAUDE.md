# CLAUDE.md

## Project overview

AlgosProj teaches data structures and algorithms: lessons, visualizations, in-browser code
(Python, Java, JavaScript, C++) run against test cases, points on a leaderboard, AI hints.
Three repos, three deployments. Flow: frontend -> backend -> sandbox.
- **algosproj-frontend**: React + TypeScript + Vite + Tailwind v4. Talks only to the backend.
- **algosproj-backend** (this repo): Laravel API (Sanctum auth, lessons, problems,
  submissions, points, leaderboard). The only service that calls the sandbox.
- **algosproj-sandbox**: FastAPI, runs user code in throwaway Docker containers. Never
  public; the backend calls it with a shared secret.
Points are only awarded after server-side grading. Never trust a score sent by the client.

## This repo

- Laravel (PHP 8.3+), MySQL 8 in Docker (`algosproj-mysql`, host port 3307), Sanctum
  personal access tokens (not cookie mode: the frontend is on another domain).
- Tables: `users` (`points`, `role` user/admin/owner and `banned_at`/`ban_reason`, none
  fillable), `modules`, `lessons`, `problems`, `test_cases` (`is_hidden`), `submissions`,
  `completions`, `admin_audit_logs`, `user_activity_logs` (both append-only). Planned: `ai_hints`.
- `routes/api.php` (README "API", "Admin API", "Roles", "Bans", "Logs"); plain controllers,
  inline validation. `/api/admin/*`: `auth:sanctum` + `not-banned` + `admin` (admin or owner),
  owner-only routes add `owner`; all run before route binding. `Controllers/Admin/*`. Owners
  only via `php artisan user:make-admin {email} [--owner|--remove]`.
- `.env`: `SANDBOX_URL`, `SANDBOX_SECRET` (`config('services.sandbox')`), `FRONTEND_URL`
  (the only CORS origin). Dockerfile: PHP 8.4 + Apache, config via env vars (README).
- Commands: `composer install`, `php artisan serve`, `php artisan migrate`, `php artisan test`.

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

- Done: auth, lessons, submit, leaderboard, rate limits, admin content CRUD, owner role, bans,
  user endpoints, audit + activity logs. 111 tests pass (SQLite, `Http::fake()`).
- Content lives in the DB. `ContentSeeder` imports `data/lessons.php` + `lesson_bodies.php`
  only when `modules` is empty; never make it overwrite. Next: AI hints (`ai_hints`).
- Decisions: sandbox failure = 503, no submission stored; logs never hold code, tests or
  the secret. Ties share a rank. Code max 65536 bytes. Points from difficulty, never from a
  request. Admin delete is 409 for a module with lessons, a lesson with problems, a solved
  problem. Tests: max 50, 20,000 bytes per field, 25,000 total, untrimmed. Every admin write
  calls `AdminAuditLog::record()` in its transaction (`AdminTest` checks `AUDIT_ACTIONS`).
- Gotchas: `docker start algosproj-mysql` before `migrate`/`serve`. Sandbox on 8001. Expected
  outputs must match trimmed stdout and come from a reference solution, never by hand.
