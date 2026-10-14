# CLAUDE.md

## Project overview

AlgosProj is an interactive learning platform for data structures and algorithms.
Students read a lesson, watch a visualization, write code in the browser (Python or
Java), and the code is run against test cases. Passing earns points on a leaderboard.
An AI tutor gives hints.

Three separate repos, three separate deployments:

- **algosproj-frontend**: React + TypeScript + Vite + Tailwind v4. Talks only to the backend.
- **algosproj-backend**: Laravel API. Handles auth (Sanctum tokens), lessons, problems,
  submissions, points and the leaderboard. It is the only service that calls the sandbox.
- **algosproj-sandbox**: FastAPI service that runs user code in throwaway Docker containers
  and returns the results. Never exposed to the public internet; the backend calls it with
  a shared secret.

Flow: frontend -> backend -> sandbox. The frontend never calls the sandbox directly.

Points and completions are only awarded after the sandbox grades a submission
server-side. Never trust a score sent by the client.

## This repo

- Stack: Laravel (PHP 8.3+), MySQL 8, Sanctum personal access tokens (not cookie mode,
  because the frontend lives on a different domain).
- MySQL 8 runs locally in a Docker container named `algosproj-mysql` (data volume
  `algosproj-mysql-data`).
- Tables: `users` (with `points`), `modules`, `lessons`, `problems`, `test_cases`
  (`is_hidden`), `submissions`, `completions` (unique on `user_id` + `problem_id`).
  Planned: `ai_hints`.
- Endpoints are in `routes/api.php` (listed in README "API"). Planned: hint.
- Plain controllers with inline validation; no service layer.
- Use Laravel's `throttle` middleware on the submit and hint endpoints.
- Config: `SANDBOX_URL`, `SANDBOX_SECRET` (read via `config('services.sandbox')`) and
  `FRONTEND_URL` (the only CORS origin) in `.env`.
- `Dockerfile` builds a PHP 8.4 + Apache image (no `.env` inside; config via env vars); see README "Docker".
- Commands: `composer install`, `php artisan serve`, `php artisan migrate`,
  `php artisan test`.

## Working rules

- Keep code simple. Don't add abstractions, patterns or dependencies I didn't ask for.
- Don't do extra work beyond the request. If you spot something worth fixing, mention it
  instead of changing it.
- Ask before adding a new dependency.
- Never add Co-Authored-By or Claude-Session lines, or any other attribution, to commit
  messages or PR descriptions. ep2wq is the only author.

## Session routine

- At the start of a session, read this file and the "Current status" section before
  doing anything else.
- Before ending a session, or when I say we're done, update "Current status" to match
  reality. Rewrite the section; don't append to it. Remove items that are finished or no
  longer true, so the file stays short (under about 60 lines in total).
- Only record facts you verified in the repo or that I told you. Don't guess.
- Update the other sections of this file too if something in them became wrong.
- Don't commit the CLAUDE.md update unless I ask. Tell me it changed.

## Current status

### Done
- Sanctum token auth (register, login, logout, me), content schema with seeder
  (`migrate:fresh --seed` gives the "Warm-up" lesson and the "Double the number" problem),
  lesson endpoints, submit (calls the sandbox, awards points once), leaderboard, CORS,
  rate limits (register 5/min per IP, login 5/min per IP + email, submit 10/min per
  user), sandbox failures logged with `Log::error`. 36 tests pass (SQLite in memory,
  `Http::fake()` for the sandbox). Verified end to end against the real sandbox on 8001.
  Committed, not pushed.

### In progress
- Nothing.

### Next
- AI hint endpoint and the `ai_hints` table.

### Decisions
- MySQL runs in Docker (`algosproj-mysql`) on host port 3307, because a local `mysqld`
  already listens on 3306.
- Any sandbox failure (unreachable, timeout, non-2xx, unknown status) returns 503 and
  stores no submission.
- Leaderboard ranks ties equally (rank = 1 + users with more points), in the list and in
  `/leaderboard/me`.
- Code size is validated in bytes (max 65536), matching the sandbox's 64 KB limit.
  `submissions.code` stays `mediumText`.
- Sandbox failure logs never include the code, test cases or secret; a 2xx body with an
  unknown status is logged without the body, since it holds test results.

### Gotchas
- Start the container (`docker start algosproj-mysql`) before `migrate` or `serve`.
- `php artisan serve` and the sandbox both default to port 8000; run the sandbox on 8001.
- The unused `database/database.sqlite` still exists locally (gitignored).
