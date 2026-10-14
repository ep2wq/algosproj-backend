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
- Planned tables: `users` (with a `points` column), `modules`, `lessons`, `problems`,
  `test_cases`, `submissions`, `completions` (unique on `user_id` + `lesson_id`),
  `ai_hints`.
- Planned endpoints: register, login, logout, me, modules, lesson detail, submit, hint,
  leaderboard.
- Use Laravel's `throttle` middleware on the submit and hint endpoints.
- Config: `SANDBOX_URL` and `SANDBOX_SECRET` in `.env`.
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
- Laravel 13 scaffold (local PHP 8.4); default migrations (users, cache, jobs) have run
  on MySQL. MySQL is the default connection in `.env`, `.env.example` and the
  `config/database.php` fallback. Committed and pushed.

### In progress
- Nothing.

### Next
- Install Sanctum and add the auth endpoints (there is no `routes/api.php` yet).
- Create the planned tables.
- Add `SANDBOX_URL` and `SANDBOX_SECRET` to `.env` and `.env.example` (not there yet).

### Decisions
- MySQL runs in Docker (`algosproj-mysql`) on host port 3307, because a local `mysqld`
  already listens on 3306.

### Gotchas
- The Laravel scaffold's `config/database.php` fell back to `sqlite`; now `mysql`. The
  unused `database/database.sqlite` still exists locally (gitignored).
- Start the container (`docker start algosproj-mysql`) before `migrate` or `serve`.
- `php artisan serve` and the sandbox both default to port 8000; run the sandbox on 8001.
