# algosproj-backend

The API for AlgosProj, a platform for learning data structures and algorithms. It handles
auth, lessons, problems, submissions, points and the leaderboard. The React frontend
(`algosproj-frontend`) talks to this service, and this service is the only one that calls
the sandbox (`algosproj-sandbox`) to run and grade user code.

## Stack

Laravel on PHP 8.3+, MySQL or Postgres, Sanctum personal access tokens.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed   # seeds 19 modules with 65 lessons, one problem each
php artisan serve
```

### Content lives in the database

After launch the database is the source of truth for modules, lessons, problems and test
cases. Edit them in the admin area (frontend `/admin`, API below). `lessons.php` and
`lesson_bodies.php` are only the initial import: `ContentSeeder` imports them only when the
`modules` table is empty. If any module exists it changes nothing and prints
`Content not imported: modules already exist.`, so running the seeder again (on purpose or
by accident) never overwrites admin edits. Editing the seed files after launch does nothing
to a live database. For a clean dev copy, `php artisan migrate:fresh --seed` still works:
it empties every table, so the import runs again.

### Roles

`users.role` is `user` (the default), `admin` or `owner`; an owner is above an admin. It is
not mass-assignable, so register, login and any future profile update can never set it. An
owner can switch other accounts between `user` and `admin` through the API; nothing in the
API can make or change an owner. Only this command can:

```bash
php artisan user:make-admin you@example.com            # make an admin
php artisan user:make-admin you@example.com --owner    # make an owner
php artisan user:make-admin you@example.com --remove   # back to a normal user
```

The account must already exist (register first). An unknown email prints `No user has the
email ...` and exits with 1; `--owner` with `--remove` is refused. Each run writes an audit
row with actor `console`. The role is in the user object of login, register and `/api/me`;
no public response includes it.

| | user | admin | owner |
|---|---|---|---|
| Content (modules, lessons, problems, test cases) | | yes | yes |
| View users, a user's detail and activity | | yes | yes |
| Ban, unban, log out everywhere | | normal users only | normal users and admins |
| Change role (user/admin), adjust points, reset progress, delete a user | | | yes |
| Read the audit log and the global activity feed | | | yes |

Enforced on the server for every request: nobody can ban, change the role of, delete, reset
or adjust the points of their own account; nobody can act on an owner through the API at
all (owners change only through the command); an admin can only act on accounts with role
`user`. Each refusal is a 403 with a message. Middleware order: `auth:sanctum`, `not-banned`,
`admin`, then `owner` on owner routes, all before route model binding, so a refused user
gets 403 even for an id that does not exist.

### Bans

`users.banned_at` (set while banned) and `users.ban_reason` (required, at most 255
characters). Banning deletes all the user's tokens in the same transaction. The `not-banned`
middleware on every authenticated route also refuses a banned user's token if one survived:
403 `{"message": "This account is banned.", "reason": "..."}`. Login answers the same 403
(after the password check, so the reason only reaches the account holder) and issues no
token. Register with that email still fails (the row exists). Banned users are left out of
`/leaderboard` and of the rank counts in `/leaderboard/me`. Unban clears both columns;
points, completions and submissions are kept through a ban and an unban. Neither column
appears in the user object of login, register or `/api/me`.

### Logs

Both logs are append-only: no endpoint, page or code path edits or deletes rows, except
`activity:prune` for the activity log. Neither ever holds a password, a token, a request
body, a lesson body or a test.

- `admin_audit_logs` (what admins do): `actor_id` (null after the actor is deleted, or for
  the command), `actor_email` (snapshot, `console` for the command), `action` (such as
  `user.ban`, `lesson.update`), `target_type`, `target_id`, `target_label` (snapshot: the
  email or the title), `details` (JSON), `ip`, `created_at`. One row for every successful
  admin write (content create, update and delete, every user action) and every
  `user:make-admin` run, written in the same transaction as the change; a refused request
  (403, 409, 422) writes none. Updates store `details.changes`: each changed field with
  `old` and `new` cut to 200 characters, or just `"changed"` for a lesson body and a test's
  input and expected output.
- `user_activity_logs` (what users do): `user_id` (null after the user is deleted, or for a
  failed login with an unknown email), `email` (snapshot, or the attempted email cut to 255
  characters), `event`, `ip`, `user_agent` (cut to 255), `created_at`. Events: `register`,
  `login`, `login_failed`, `login_blocked_banned`, `logout`, and `banned_by_admin` /
  `unbanned`, which are written in the ban's transaction and have no IP or browser (the
  request was the admin's; the audit log has the admin's IP). Not logged: throttled 429s,
  validation errors, page views, submissions (the `submissions` table has those). Each of
  register, login and logout adds one INSERT; if it fails, the request still succeeds and
  the error goes to the Laravel log.
- Retention: `php artisan activity:prune` deletes activity rows older than 90 days
  (`--days=30` for another limit). It is not scheduled; see "Deploy checklist".
- `ip` is the request IP. Locally it is `127.0.0.1`. Behind Cloudflare or another proxy it is
  the proxy's IP until trusted proxies are configured (see "Deploy checklist"). Both logs
  store IP addresses and emails, which the privacy policy must cover.

Seed content lives in `database/seeders/data/lessons.php`: Getting started (slug `basics`;
titled Basics until the Programming basics module was added), Programming basics, Core programming, Object-oriented programming (slug `oop`), Algorithm basics, Arrays and strings,
Stacks and queues, Linked lists, Hash tables, Trees and BSTs, Heaps and priority queues (slug `heaps`), Recursion and backtracking, Graphs, Tries and union-find (slug `tries-union-find`), Sorting and searching, Greedy algorithms (slug `greedy`), Dynamic programming basics (slug `dp-basics`), Dynamic programming: tables (slug `dp-tables`), Bit manipulation and math (slug `bits-math`). Each problem has 2 visible and 3-6 hidden tests (Make change has 10, one per amount it must cover; Count set bits has 10, one per required value),
454 test cases in all (130 visible, 324 hidden),
and points follow difficulty (easy 10, medium 20, hard 30). The expected outputs were
generated by running a reference solution and checked through the sandbox. If you change
a test, regenerate its expected output the same way. The sandbox compares trimmed output
exactly, line breaks included. Most answers are a single line; some, such as FizzBuzz,
print several. The sandbox rejects a submission whose code plus all tests pass 120 KB once
base64-encoded; Friend groups and Minimum spanning tree have large required tests, so they
accept code up to about 38 KB and 48 KB, not the full 64 KB.

### Test quality rule

No trivial answer and no unchanged starter code may pass more than half of a problem's
tests. The trivial answers checked are: print the first input line, the first number, 0, 1,
-1, the number of input lines, the input unchanged, an empty line, YES, NO, and the sum,
largest and count of the numbers in the input; the starter runs in all four languages. Each
must also fail at least one visible test, so unchanged code shows a failure on an example.
Check it through the sandbox against every test, hidden ones included, whenever tests change.

### Lesson text format

Lesson text (`lessons.body`) was first imported from `database/seeders/data/lesson_bodies.php`
(keyed by lesson slug) and is now edited in the admin area. The frontend parses a small subset of markdown, nothing more (no bold, links
or numbered lists):

- A line starting with `## ` is a section heading.
- Consecutive lines starting with `- ` are a bulleted list.
- Consecutive lines indented with 4 spaces are a code block. Blank lines inside such a run
  stay in the block if the next non-blank line is also indented. The 4 leading spaces are
  removed; the rest of the indentation is kept.
- Everything else is paragraphs. Blank lines separate them, and single line breaks inside
  a paragraph are kept (worked examples rely on that). Paragraphs use a proportional font,
  so worked tables whose columns must line up are written as 4-space code blocks.

Each lesson uses the headings `## Example`, `## How to think about it`, `## Complexity`
and `## Watch out for`, and is 300-350 words. The Core programming, Object-oriented
programming, Algorithm basics, Heaps and priority queues, Greedy algorithms, both Dynamic
programming, the Tries and union-find and the Bit manipulation and math lessons also show code under `## Python`, `## Java`, `## JavaScript` and `## C++`
headings; the first two are 350-450 words, the other seven keep 300-350, code included. Run any
code snippet through the sandbox before adding it; the warm-up, Core programming,
Object-oriented programming, Algorithm basics, Heaps, Greedy, both Dynamic programming,
Tries and union-find and Bit manipulation and math modules' snippets were checked in all four languages. The JavaScript `Heap` class in Last stone weight is reused
by the other two heap lessons and by Connect ropes. Minimum spanning tree reuses `find` and
`join` from Friend groups.

Run the tests with `php artisan test`.

## Database indexes

Indexes on the tables the API reads, besides each primary key:

- `users_email_unique`: login and the register uniqueness check look a user up by email.
- `users_rank_index` (`banned_at, points`): `/leaderboard/me` counts the users who are not
  banned and have more points, from the index alone. It replaced `users_points_index`: with
  the banned filter that index stopped covering the count and MySQL scanned all 20,000 rows
  (3.1 ms); with this one it reads only the matching rows (1.2 ms for 7,992 rows).
- `users_leaderboard_index` (`points desc, created_at`): `/leaderboard` reads the top rows
  already in its order (points down, then oldest account, then id) instead of sorting every
  user; with 20,000 users it read 51 rows instead of about 20,000. The banned filter is
  checked on those rows, so it still reads about the limit plus the banned ones among them.
- `user_activity_logs.created_at`: `activity:prune` deletes by age.
- `modules_slug_unique`, `lessons_slug_unique`: lesson pages are fetched by slug.
- Foreign key indexes (`lessons.module_id`, `problems.lesson_id`, `test_cases.problem_id`,
  `submissions.user_id`, `submissions.problem_id`, `completions.problem_id`): MySQL creates
  them for each foreign key; they serve the lesson, modules and submit lookups.
- `completions_user_id_problem_id_unique`: one completion per user and problem; it also
  answers the "already completed?" check on submit.

To add an index later, measure first: fill a temporary database with realistic volume, run
`EXPLAIN` / `EXPLAIN ANALYZE` on the query the app really sends, add the index there and
keep it only if rows read or time drop. Don't copy an index that already exists (foreign keys
and unique constraints have one). In MySQL, an index starting with a foreign key column
replaces that foreign key's own index, which matters if you later drop it. Add it in a new
migration with a name and a `down()` that drops it.

## Environment variables

On top of the usual Laravel `APP_*` and `DB_*` settings:

- `SANDBOX_URL` — base URL of the sandbox service
- `SANDBOX_SECRET` — shared secret sent to the sandbox as the `X-Sandbox-Secret` header
- `FRONTEND_URL` — the only origin allowed by CORS (default `http://localhost:5173`)

## API

All routes are under `/api`. Send `Accept: application/json`. Authenticated routes need
`Authorization: Bearer <token>`, using the token from register or login.

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/api/register` | | Create a user (`name`, `email`, `password`); returns `token` and `user`; 5 per minute per IP |
| POST | `/api/login` | | `email`, `password`; returns `token` and `user` (401 on a wrong password, 403 with `reason` for a banned account); 5 per minute per IP and email |
| POST | `/api/logout` | yes | Deletes the current token |
| GET | `/api/me` | yes | The current user, with `points` |
| GET | `/api/modules` | | Modules with their lessons (no lesson body), in `sort_order` |
| GET | `/api/lessons/{slug}` | | A lesson with its problems and their visible test cases |
| POST | `/api/problems/{id}/submit` | yes | Run code against all test cases; 10 requests per minute per user |
| GET | `/api/leaderboard?limit=50` | | `[{rank, name, points, is_you}]`, users with points > 0 who are not banned (limit max 100); `is_you` is true only on the logged-in requester's row (a token is optional), false on every row when logged out |
| GET | `/api/leaderboard/me` | yes | `{rank, points}` for the current user |
| GET | `/api/challenges` | | Every problem: `[{slug, title, difficulty, points, topic, lesson_slug, solved}]`, in module order, then lesson order; standalone problems last |
| GET | `/api/challenges/{slug}` | | One problem with what the editor needs (`id`, `title`, `description`, `difficulty`, `points`, visible `test_cases`) plus `slug`, `topic`, `lesson_slug`, `solved`; 404 for an unknown slug |

Hidden test cases are never returned by the public routes. In a submit response they only
show `{"passed": bool}`. Only the admin routes below return them.

Challenges are the problems themselves, so a problem solved from `/challenges` or from its
lesson is the same completion and earns points once. `topic` is `{slug, title}` of the
problem's module. `solved` is true only for the requester (a token is optional, as on the
leaderboard) and always false for guests. `problems.lesson_id` is nullable: a problem with no
lesson is a standalone challenge, with `topic` and `lesson_slug` null; submit works the same
for it. `problems.slug` is unique and nullable; the migration that added it filled it with the
lesson slug (a second problem in the same lesson would get `-<id>` appended), and the seeder
sets it the same way. A standalone problem needs its own slug to appear at
`/challenges/{slug}`.

```bash
# Register (or log in with POST /api/login and the same email and password)
curl -X POST http://localhost:8000/api/register \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name":"Ada","email":"ada@example.com","password":"secret-password"}'
# -> {"token":"1|...","user":{"id":1,"name":"Ada","points":0,...}}

curl http://localhost:8000/api/lessons/warm-up -H "Accept: application/json"

curl -X POST http://localhost:8000/api/problems/1/submit \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"language":"python","code":"print(int(input()) * 2)"}'
# -> {"status":"passed","passed_count":4,"total_count":4,"runtime_ms":31,"compile_error":null,
#     "points_awarded":10,"results":[{"passed":true,"input":"2","expected":"4","actual":"4","error":null},
#     {"passed":true,"input":"21","expected":"42","actual":"42","error":null},{"passed":true},{"passed":true}]}

curl "http://localhost:8000/api/leaderboard?limit=10" -H "Accept: application/json"
# -> [{"rank":1,"name":"Ada","points":10}]
```

Submit details:

- `language` is one of `python`, `java`, `javascript`, `cpp`; `code` is required, at most
  64 KB (65536 bytes, not characters). Invalid input returns 422.
- `status` is `passed`, `failed`, `error` or `timeout`. Points are awarded only the first
  time a problem is passed; later passing submissions return `points_awarded: 0`.
- If the sandbox is unreachable, times out (60 s) or returns an error, the response is
  503 `{"message": "Code runner unavailable, try again shortly"}` and nothing is stored.
  The failure is logged with `Log::error` (status code, at most 300 characters of the body,
  the exception message), never with the code, test cases or secret.
- Going over a rate limit returns 429.

## Admin API

All under `/api/admin`, behind `auth:sanctum`, `not-banned` and the `admin` middleware
(`EnsureUserIsAdmin`): no token is 401, a user who is neither admin nor owner is 403
(checked before route model binding, so they get 403 even for an id that does not exist),
admins and owners pass. Owner-only routes add the `owner` middleware (`EnsureUserIsOwner`,
403 for admins). The frontend hides these pages from others, but that is only convenience;
these checks are the security. Ids, not slugs, in the paths. Edits show on the public routes
at once. Every successful write is in the audit log (see "Logs").

| Method | Path | Body | Result |
|---|---|---|---|
| GET | `/modules` | | Modules in order, each with its `lessons` (no body) |
| POST | `/modules` | `title`, `slug`, `sort_order` | 201; new modules get track `algorithms` |
| PUT | `/modules/{module}` | `title`, `slug`, `sort_order` | The module (track unchanged) |
| DELETE | `/modules/{module}` | | 204, or 409 if it has lessons |
| GET | `/modules/{module}/lessons` | | Its lessons (no body) |
| POST | `/lessons` | `module_id`, `title`, `slug`, `body`, `sort_order` | 201 |
| GET | `/lessons/{lesson}` | | The lesson with its full `body` |
| PUT | `/lessons/{lesson}` | as POST | The lesson |
| DELETE | `/lessons/{lesson}` | | 204, or 409 if it has problems |
| GET | `/problems` | | All problems (no description) |
| POST | `/problems` | `lesson_id` (optional), `title`, `slug`, `description`, `difficulty` | 201 |
| GET | `/problems/{problem}` | | The problem |
| PUT | `/problems/{problem}` | as POST | The problem |
| DELETE | `/problems/{problem}` | | 204 (its tests and submissions go with it), or 409 if anyone has a completion |
| GET | `/problems/{problem}/test-cases` | | All its tests, hidden ones and expected outputs included |
| POST | `/problems/{problem}/test-cases` | `input`, `expected_output`, `is_hidden` | 201 |
| PUT | `/problems/{problem}/test-cases/{testCase}` | as POST | The test case |
| DELETE | `/problems/{problem}/test-cases/{testCase}` | | 204, always allowed |
| GET | `/users?search=&role=&banned=&page=` | | 25 per page, newest first: `id, name, email, role, points, banned_at, created_at, solved_count`; `search` matches name or email (`%` and `_` are literal), `role` is user/admin/owner, `banned` 1 or 0 |
| GET | `/users/{user}` | | The fields above plus `ban_reason`, `solved` (`title, slug, solved_at`) and the last 20 `submissions` (`problem, language, status, passed_count, total_count, runtime_ms, created_at`; never the code) |
| GET | `/users/{user}/activity?page=` | | The user's activity, 25 per page, newest first |
| POST | `/users/{user}/ban` | `reason` (required, max 255) | The user row; 409 if already banned |
| POST | `/users/{user}/unban` | | The user row; 409 if not banned |
| POST | `/users/{user}/logout-everywhere` | | The user row; all their tokens are deleted |
| PUT | `/users/{user}/role` | `role`: `user` or `admin` | Owner only. The user row |
| POST | `/users/{user}/points` | `amount` (non-zero integer, -1,000,000 to 1,000,000), `reason` (max 255) | Owner only. The user row; 422 if points would go below 0 |
| POST | `/users/{user}/reset-progress` | | Owner only. Deletes their completions and sets points to 0 (submissions stay) |
| DELETE | `/users/{user}` | `confirm_email` (the exact email) | Owner only. 204, or 422 if the email does not match. Deletes their tokens; foreign keys delete their submissions and completions and set them to null in both logs |
| GET | `/audit-log?action=&actor=&from=&to=&page=` | | Owner only. 50 per page, newest first; `actor` is an id, an email or `console`; `from`/`to` are `YYYY-MM-DD` UTC days, both included |
| GET | `/activity?event=&user=&from=&to=&page=` | | Owner only. 50 per page, newest first; `user` is an id or an email |

User actions (everything above that changes a user) follow the rules in "Roles"; ban, unban,
log out everywhere, role, points, reset and delete never accept a password, a token or the
role `owner`, and unknown fields are ignored.

Rules (422 with a message per field unless noted):

- Slugs: required, unique in their table, only `a-z`, `0-9` and `-`, at most 100 characters.
- Titles at most 255 characters; `sort_order` an integer 0-100000; lesson `body` optional,
  at most 20,000 characters (and 65,535 bytes, the TEXT column); problem `description`
  required, at most 5,000 characters; `difficulty` is `easy`, `medium` or `hard`.
- `points` is never accepted: the server sets it from difficulty (easy 10, medium 20, hard
  30) on create and update. Changing difficulty does not change points already awarded.
- Test cases: `input` may be empty, `expected_output` is required, each at most 20,000
  bytes. Both are stored exactly as sent (excluded from Laravel's TrimStrings; every seed
  input ends with a newline and some start with spaces). A problem has at most 50 tests,
  and all its inputs plus expected outputs total at most 25,000 bytes, checked on create and
  update; both return 422 under `test_cases`. This keeps the sandbox payload (base64 JSON,
  about 120 KB) safe next to 64 KB of code. Six seed problems are already over 25,000
  bytes (friend-groups 49,482, count-connected-components 46,060, sort-an-array 41,569,
  minimum-spanning-tree 39,551, delete-from-a-bst 29,693, diagonal-sums 26,186): their tests
  can be deleted but not added or edited until the total is under the limit. Seven seed
  problems (those above except delete-from-a-bst, plus linear-search and binary-search) have
  a test over 20,000 bytes, which cannot be saved again as it is. Problem fields can always
  be edited.
- Deleting: a module with lessons, a lesson with problems, or a problem that anyone has
  solved returns 409 with a message. Edit things users have solved instead of deleting them.
- Nested test-case routes use scoped binding: a test case of another problem is a 404.

## Docker

The image runs PHP 8.4 with Apache (PHP 8.4 because `composer.lock` pins Symfony 8). No
`.env` file is copied in; all configuration comes from environment variables at run time.

```bash
docker build -t algosproj-backend .
docker run -d --name algosproj-backend -p 8080:80 \
  -e APP_KEY=base64:... -e APP_ENV=production -e APP_DEBUG=false \
  -e DB_CONNECTION=mysql -e DB_HOST=host.docker.internal -e DB_PORT=3307 \
  -e DB_DATABASE=... -e DB_USERNAME=... -e DB_PASSWORD=... \
  -e SANDBOX_URL=http://host.docker.internal:8001 -e SANDBOX_SECRET=... \
  algosproj-backend
```

- Environment variables: `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `DB_*`, `SANDBOX_URL`,
  `SANDBOX_SECRET`, `FRONTEND_URL`. Generate a key with
  `docker run --rm algosproj-backend php artisan key:generate --show`.
- Inside a container, `DB_HOST` and `SANDBOX_URL` must point at `host.docker.internal`
  (or a Docker network name), not `127.0.0.1`.
- Migrations are not run by the image. Run them with
  `docker exec <container> php artisan migrate --force`.

## Deploy checklist

- Run `php artisan migrate --force`, then make the first owner with
  `php artisan user:make-admin <email> --owner` (the account must be registered first).
- Run `php artisan activity:prune` once a day (cron or the Laravel scheduler); it is not
  scheduled in the code yet.
- Configure trusted proxies when running behind Cloudflare or any other proxy
  (`$middleware->trustProxies(...)` in `bootstrap/app.php`). Until then the request IP is the
  proxy's, so both logs record the wrong IP and the login and register rate limits, which key
  on the IP, count every user behind the same proxy together.
- The logs store IP addresses and emails; cover that in the privacy policy before launch.
