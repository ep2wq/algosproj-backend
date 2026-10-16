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
- Tables: `users` (`points`), `modules`, `lessons`, `problems`, `test_cases` (`is_hidden`),
  `submissions`, `completions` (unique `user_id` + `problem_id`). Planned: `ai_hints`.
- Endpoints in `routes/api.php` (README "API"). Plain controllers, inline validation, no
  service layer. `throttle` on submit and (planned) hint.
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

- Done: auth, lessons, submit (sandbox, points once), leaderboard, CORS, rate limits.
  Seed: 15 modules, 49 lessons, one problem each (`data/lessons.php`; 2 visible + 3-10
  hidden tests; easy 10 / medium 20 / hard 30), all passing through the real sandbox.
  Module `basics` is titled "Getting started" (was "Basics"; slug unchanged). Lesson text
  (316-450 words, section headings) in `data/lesson_bodies.php`. 40 tests pass (SQLite in
  memory, `Http::fake()` for the sandbox).
- Next: AI hint endpoint and the `ai_hints` table.
- Decisions: any sandbox failure returns 503 and stores no submission; logs never include
  code, tests or the secret. Leaderboard ties share a rank. Code size max 65536 bytes.
  Lesson text uses a tiny format (`## ` headings, `- ` lists, 4-space code, paragraphs);
  see README "Lesson text format". The frontend parses it; no markdown library.
- Gotchas: `docker start algosproj-mysql` before `migrate` or `serve`. Run the sandbox on
  8001 (both default to 8000). Seed expected outputs must match trimmed stdout exactly and
  come from a reference solution, never by hand (some answers span several lines).
