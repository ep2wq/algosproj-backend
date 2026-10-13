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
- Commands: `composer install`, `php artisan serve`, `php artisan migrate`,
  `php artisan test`.

## Working rules

- Keep code simple. Don't add abstractions, patterns or dependencies I didn't ask for.
- Don't do extra work beyond the request. If you spot something worth fixing, mention it
  instead of changing it.
- Ask before adding a new dependency.
