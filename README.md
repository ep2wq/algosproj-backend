# algosproj-backend

The API for AlgosLab, a platform for learning data structures and algorithms. It handles
auth, lessons, problems, submissions, points and the leaderboard. The React frontend
(`algosproj-frontend`) talks to this service, and this service is the only one that calls
the sandbox (`algosproj-sandbox`) to run and grade user code.

## Stack

Laravel on PHP 8.3+, MySQL or Postgres, Sanctum in SPA mode (cookie sessions, no API tokens; see "Sessions").

## Setup

No npm or Vite here: the backend serves only the JSON API under `/api` and the `/up` health
check (`GET /` is 404).

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed   # seeds 19 modules with 65 lessons, one problem each
php artisan serve
```

A database created before the UUID change (see "Database indexes", "Keys") needs
`php artisan migrate:fresh` and then `php artisan content:sync`: plain `migrate` does not convert
it, and `migrate:fresh` wipes users and progress.

### Content lives in the database

After launch the database is the source of truth for modules, lessons, problems and test
cases. Edit them in the admin area (frontend `/admin`, API below). `lessons.php` and
`lesson_bodies.php` are only the initial import: `ContentSeeder` imports them only when the
`modules` table is empty. If any module exists it changes nothing and prints
`Content not imported: modules already exist.`, so running the seeder again (on purpose or
by accident) never overwrites admin edits. Editing the seed files after launch does nothing
to a live database. For a clean dev copy, `php artisan migrate:fresh --seed` still works:
it empties every table, so the import runs again.

### content:sync

`php artisan content:sync` brings new seed content into an existing database without touching
edits. In one transaction it reads the English files (`lessons.php`, `lesson_bodies.php`) and the
Arabic ones (`data/ar/*.php`) and:

1. adds the modules, lessons and problems (with their test cases) whose slug the database does
   not have yet, exactly as the first import does;
2. fills an Arabic column (`*_ar`) only while it is empty (NULL);
3. never changes a non-empty value (English or Arabic) and never touches the test cases of a
   problem that already exists. A problem an admin deleted comes back if its lesson is still in
   the seed files.

It prints how many rows were added, how many Arabic fields were filled and how many were skipped
because a value already exists, and records one audit-log row (`content.sync`, actor
`console`) with those counts. `--dry-run` prints the same summary and writes nothing. A second
run changes nothing. `ContentSeeder` uses the same code (`database/seeders/ContentImport.php`),
so there is one import path; the seeder still runs only when no module exists.

- **Dev:** `docker start algosproj-mysql`, `php artisan migrate`, `php artisan content:sync
  --dry-run`, then `php artisan content:sync`. A database from before the UUID change needs
  `php artisan migrate:fresh` first (it wipes users and progress).
- **Production:** after deploying, `php artisan migrate --force`, then
  `php artisan content:sync --dry-run`; read the summary, then `php artisan content:sync`.

To add a lesson in both languages: add the module (if new) and the lesson with its problem and
test cases to `lessons.php`, its body to `lesson_bodies.php`, and the Arabic title, body and
problem text under the same slugs in `data/ar/lessons.php` and `data/ar/problems.php` (a new
module's Arabic title in `data/ar/modules.php`). Then run `content:sync`. The Arabic is
required: `ArabicSeedTextTest` fails while any seeded module, lesson or problem lacks its Arabic
title or text, so new content always ships with Arabic. (A field still empty in the database,
for example after an admin edit, falls back to the English.)

### Content languages

Modules, lessons and problems have an Arabic column next to each English prose column, same
type, NULL until translated (an empty value is stored as NULL): `modules.title_ar`,
`lessons.title_ar`, `lessons.body_ar`, `problems.title_ar`, `problems.description_ar`. Slugs,
tracks, difficulty, test inputs and expected outputs are never translated.

Every request reads `Accept-Language` (`SetContentLanguage`, global middleware in
`bootstrap/app.php`, which sets the app locale): Arabic only when the first language listed is
`ar` or `ar-XX` (`ar`, `ar-SA`, `ar;q=0.9,en;q=0.8`); anything else, missing or malformed, is
English. On the public content routes (`/api/modules`, `/api/lessons/{slug}`, `/api/challenges`,
`/api/challenges/{slug}`) it picks the course text. The field names stay `title`,
`body`, `description`; in Arabic each field uses its Arabic column and falls back to English
on its own when that is empty. `body_lang` (lessons) and `description_lang` (problems) say which
language the text came in (`ar` or `en`), so the frontend sets `lang` and `dir`. Responses carry
`Vary: Accept-Language`. English responses are what they were before, plus the `*_lang` fields;
the `*_ar` names never appear in public responses. The language only picks a column: no extra
queries. Admin responses hold both languages.

Translated: all 19 of 19 modules (65 lessons, 65 problems).

### API messages

Messages follow the same language (`MessageLanguageTest`). Every response carries
`Vary: Accept-Language`. Where they live:

- `lang/en/messages.php` and `lang/ar/messages.php`: the public messages (wrong login, banned,
  logged out, code too large, code runner unavailable, unauthenticated, not found, too many
  attempts, the email and password reset messages). `lang/*/mail.php`: the two emails ("Email"). Code calls `__('messages.key')`; whole sentences with `:placeholders`.
- `lang/ar/validation.php`: Arabic for the validation rules the public endpoints use
  (`required`, `string`, `email`, `max`, `min`, `unique`, `in`, `boolean`, `accepted`, `confirmed`) and the field names
  `name`, `email`, `password`, `password_confirmation`, `token`, `remember`, `accepted_terms`, `language`, `code`; `lang/ar.json`: the "(and :count more errors)"
  summary. English validation stays Laravel's built-in text; any rule missing in Arabic falls
  back to it.
- `lang/en/admin.php`: admin API messages, English only (the admin frontend always sends `en`,
  and `app.fallback_locale` is `en`).
- Laravel's own 401, 404 and 429 messages are replaced only in Arabic (`bootstrap/app.php`);
  in English the framework's text is kept exactly as before. The ban reason is returned as
  typed; only the sentence around it is translated.

Adding a message: a key in `lang/en/<file>.php` and, if users see it, `lang/ar/<file>.php`.
Adding a language (say `fr`): `lang/fr/messages.php`, `lang/fr/validation.php` (the rules above),
`lang/fr.json`, and accept `fr` in `SetContentLanguage::wantsArabic` (rename it then) and in the
Arabic-only error rendering in `bootstrap/app.php`; course text needs `*_fr` columns.

Translation rules (for every batch):

- Translated: explanations, instructions, the wording of examples, headings and problem
  statements, in plain neutral Modern Standard Arabic (short sentences, everyday words, no
  dialect, idioms or rare classical words), not word for word.
- Kept in English: every technical and computer-science term (array, string, index, loop,
  function, recursion, time complexity, input, output, hash map, ...), with no Arabic
  equivalent next to it. In prose it is a `~term~` marker (one term or phrase, never Arabic
  inside); code, identifiers, keywords, Big-O, values and expressions stay in `inline code`.
  No Arabic suffix on a marker: rephrase or use the English plural (`~arrays~`). The definite
  form is "الـ" right before the marker (`الـ~array~`), with و، ب، ف، ك before it
  (`بالـ~index~`); this is the only place for tatweel.
- Titles: a title that is only a technical term or a known problem name stays English (Two Sum,
  FizzBuzz); a descriptive one is translated with its terms in English and no marker
  ("مجموع عناصر الـ array").
- Same structure as the English (same headings, list items and code blocks in the same order;
  code blocks byte-identical), same numbers, examples and outputs; problem sections are
  `~Input~`, `~Output~`, `~Constraints~`. Arabic punctuation, Western digits, no diacritics
  unless a word is ambiguous, no gendered address where it can be avoided, body length within
  about 25% of the English.
- Checks: `tests/Feature/ArabicSeedTextTest.php` (every seeded module, lesson and problem has
  its Arabic; code blocks, headings, list items, no empty field, no English outside inline code
  and markers, balanced markers), and a back-translation by a reader who has not seen the
  English.

`database/seeders/data/ar/GLOSSARY.md` lists (1) the terms kept in English and (2) the Arabic
wording chosen for recurring words and phrases; ⚠ marks choices to review. Load it before a
batch and add every new entry.

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
row with actor `console`. It also marks the email as verified (an explicit server-side action by
the owner) and says so (`The email ... is marked as verified.`); an earlier verification date is kept. The role is in the user object of login, register and `/api/me`;
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
`user`. Each refusal is a 403 with a message. Middleware order: `auth:web`, `not-banned`,
`admin`, then `owner` on owner routes, all before route model binding, so a refused user
gets 403 even for an id that does not exist.

### Bans

`users.banned_at` (set while banned) and `users.ban_reason` (required, at most 255
characters). Banning ends all the user's sessions (session rows deleted, remember token changed) in the same
transaction. The `not-banned` middleware on every authenticated route also refuses a banned
user's session if one survived:
403 `{"message": "This account is banned.", "reason": "..."}`. Login answers the same 403
(after the password check, so the reason only reaches the account holder) and starts no
session. Register with that email still fails (the row exists). Banned users are left out of
`/leaderboard` and of the rank counts in `/leaderboard/me`. Unban clears both columns;
points, completions and submissions are kept through a ban and an unban. Neither column
appears in the user object of login, register or `/api/me`.

### Terms acceptance

Register needs `"accepted_terms": true` (the checkbox on the frontend's register form): `true`,
`1` or `"1"`; anything else, or no field, is a 422 on `accepted_terms` and no account is made.
The server then sets `users.terms_accepted_at` (the time) and `users.terms_version` (from
`config/legal.php`, the legal pages' "last updated" date, the same value as `LEGAL_UPDATED` in
the frontend's `src/site.ts`; change both when the Terms, Privacy or Cookies text changes). Only
register sets the two columns: they are not mass-assignable, no other endpoint changes them,
and they are hidden from the user object of login, register and `/api/me`. No IP address or
user agent is stored with them. Accounts made before the columns existed (and the seeded Test
User) keep NULL; nobody is asked to accept again yet. Login needs no field (its checkbox is
frontend only). `TermsConsentTest` covers it.

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
  `login`, `login_failed`, `login_blocked_banned`, `logout` (Google and GitHub sign-ins write the same
  `register`, `login` and `login_blocked_banned`), and `banned_by_admin` /
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
- `` `inline code` `` in headings, list items, paragraphs and problem descriptions is shown in a
  code font, left to right. Arabic text must put every expression and value in it.
- `~term~` (Arabic text only) is an English term shown as plain text, left to right, marked
  `lang="en"`. It starts and ends with a non-space character; a lone `~` stays as text, and a
  `~` inside code is never a marker. English text never uses it.

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

Keys: every application table's key is a UUIDv7 string (`CHAR(36)`, Laravel `HasUuids`), and
every foreign key is a `foreignUuid` with the same delete rule as before. Never order by id or
read it as a number: ordering uses explicit columns (`test_cases.position`, `problems.created_at`,
`created_at`, `sort_order`) and the id only breaks ties. `personal_access_tokens` (unused) keeps
its own bigint id. The decision is in `docs/uuid-decision.md` in the workspace.

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
  The id is only the last tiebreaker, for a stable order.
- `user_activity_logs.created_at`: `activity:prune` deletes by age.
- `users.created_at`: the admin users list is read newest first.
- `admin_audit_logs.created_at`: the audit log is read newest first.
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

## Sessions

The frontend logs in with a cookie session (Laravel Sanctum's SPA mode). There are no API
tokens: login and register return only `{"user": ...}`, and a bearer token never
authenticates, because the protected routes use the `web` session guard (`auth:web`).
`tests/Feature/SessionTest.php` covers the whole flow.

- Only the frontend gets a session. `$middleware->statefulApi()` (`bootstrap/app.php`) starts
  the session, encrypts cookies and checks CSRF for requests whose `Origin` (or `Referer`)
  host and port are in `SANCTUM_STATEFUL_DOMAINS` (default: those of `FRONTEND_URL`). Any
  other client gets no session: login and register answer 419 and protected routes 401.
- CSRF: the frontend first calls `GET /sanctum/csrf-cookie` (204), which sets the
  `XSRF-TOKEN` cookie (readable by JavaScript) and the session cookie. Every POST, PUT and
  DELETE sends that value back in the `X-XSRF-TOKEN` header; without it the answer is 419.
  Requests use `credentials: 'include'`; CORS allows credentials for `FRONTEND_URL` only.
- The session cookie (`algoslab-session`, `Str::slug(APP_NAME)-session` with `APP_NAME=AlgosLab`;
  the frontend's Cookies page names it) is HttpOnly and `SameSite=Lax`
  (Sanctum sets Lax on API responses whatever `SESSION_SAME_SITE` says), and `Secure` with
  `SESSION_SECURE_COOKIE=true`. Sessions live in the `sessions` table: keep
  `SESSION_DRIVER=database`, because ban and log out everywhere delete rows by `user_id`.
- Idle timeout: `SESSION_LIFETIME` minutes (120); each request renews it. Login and register
  start a new session id (against session fixation). Logout ends the session, makes a new
  CSRF token and gives the user a new remember token, so a "remember me" cookie on another
  device stops working too (a session open there runs until it times out).
- Remember me: `"remember": true` on login (off by default) sets Laravel's remember cookie
  (`remember_web_...`, HttpOnly) for `AUTH_REMEMBER_MINUTES` (43200 = 30 days; the
  `remember` option of the `web` guard in `config/auth.php`). It logs the user back in after
  the session timed out.
- Ban, log out everywhere and delete call `User::endSessions()` in the action's transaction:
  it deletes the user's session rows and changes the remember token. The `not-banned`
  middleware still refuses a banned user if a session survives.
- Rate limits: login 5 per minute per email and IP; register 5 per minute per IP (a named
  limiter: `throttle:5,1` would count per logged-in user, and register logs the new user in).
- No password reset: "forgot password" needs email, which the app does not send.

Local development (`.env`; the frontend's `VITE_API_URL` must use the same host name as the
page, `http://localhost:8000` next to `http://localhost:5173`, never `127.0.0.1`: cookies
belong to a host name, ports don't matter):

```
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DRIVER=database
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
```

Production: the frontend and the API on the same registrable domain, `https://algoslab.sa` and
`https://api.algoslab.sa`, with the frontend built with `VITE_API_URL=https://api.algoslab.sa`
and `VITE_SITE_URL=https://algoslab.sa`:

```
APP_NAME=AlgosLab
APP_URL=https://api.algoslab.sa
FRONTEND_URL=https://algoslab.sa
SANCTUM_STATEFUL_DOMAINS=algoslab.sa
SESSION_DOMAIN=.algoslab.sa
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_DRIVER=database
```

`SESSION_DOMAIN=.algoslab.sa` shares the cookies with the site and the API, so the frontend can
read `XSRF-TOKEN`. One host for both (a reverse proxy sending `/api` and `/sanctum` to
Laravel) also works, with `SESSION_DOMAIN=null`. On different registrable domains (say
`algoslab.sa` and an API on another domain) cookie login does not work: the cookies are
third-party there, the browser does not send a Lax cookie on a cross-site request and the
frontend cannot read `XSRF-TOKEN`, so every request after login is 401 or 419. Move the API
under the frontend's domain (a subdomain or a proxied path); there is no token fallback.

Behind a proxy or load balancer, configure trusted proxies (see "Deploy checklist"), or the
logs and the IP rate limits see the proxy's address instead of the user's.

## Environment variables

On top of the usual Laravel `APP_*` and `DB_*` settings:

- `SANDBOX_URL` — base URL of the sandbox service
- `SANDBOX_SECRET` — shared secret sent to the sandbox as the `X-Sandbox-Secret` header
- `FRONTEND_URL` — the only origin allowed by CORS (credentials allowed) and the default
  stateful domain (default `http://localhost:5173`)
- `SANCTUM_STATEFUL_DOMAINS` — hosts (with port) that get a session; see "Sessions"
- `SESSION_DRIVER` — keep `database`; see "Sessions"
- `SESSION_DOMAIN` — cookie domain; see "Sessions"
- `SESSION_SECURE_COOKIE` — `true` in production; see "Sessions"
- `SESSION_SAME_SITE` — `lax`; see "Sessions"
- `SESSION_LIFETIME` — idle timeout in minutes (120); see "Sessions"
- `AUTH_REMEMBER_MINUTES` — "remember me" length (43200 = 30 days); see "Sessions"
- `MAIL_*` — the mailer; `log` locally, Resend SMTP in production; see "Email"
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` — the OAuth apps; see "Social login"

## API

All routes are under `/api`. Send `Accept: application/json`. Authenticated routes need the
session cookie set by login or register (see "Sessions"); `Authorization: Bearer` is not used.

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/api/register` | | Create a user (`name`, `email`, `password`, `accepted_terms`: true; see "Terms acceptance"); returns `user` and sets the session; 5 per minute per IP |
| POST | `/api/login` | | `email`, `password`, optional `remember` (boolean); returns `user` and sets the session (401 on a wrong password, 403 with `reason` for a banned account); 5 per minute per IP and email |
| GET | `/api/auth/pending` | | The pending Google or GitHub sign-up of this session: `{name, email, provider}`, or 404 (see "Social login") |
| POST | `/api/auth/complete` | | `name`, `accepted_terms`: true; creates the account of the pending sign-up, returns `user` (201) and sets the session; 410 without one; register's limit |
| POST | `/api/logout` | yes | Ends the session |
| GET | `/api/me` | yes | The current user, with `points` and `email_verified` (boolean) |
| GET | `/api/email/verify/{id}/{hash}` | | The signed link from the verification email (see "Email"); redirects to the frontend |
| POST | `/api/email/verification-notification` | yes | Sends a new verification email (202); 200 "already verified" sends nothing; 3 per hour per user and 10 per hour per IP |
| POST | `/api/forgot-password` | | `email`; always 202 with the same message; 3 per hour per email and IP, 10 per hour per IP |
| POST | `/api/reset-password` | | `token`, `email`, `password`, `password_confirmation`; 200, or 422 with one generic message; 5 per hour per IP |
| GET | `/api/modules` | | Modules with their lessons (no lesson body), in `sort_order` |
| GET | `/api/lessons/{slug}` | | A lesson (`body`, `body_lang`) with its problems (`description_lang`) and their visible test cases |
| POST | `/api/problems/{id}/submit` | yes, verified email | `{id}` is the problem's UUID. Run code against all test cases, in `position` order; 10 requests per minute per user; 403 `reason: email_unverified` without a verified email |
| GET | `/api/leaderboard?limit=50` | | `[{rank, name, points, is_you}]`, users with points > 0 who are not banned (limit max 100); `is_you` is true only on the logged-in requester's row (a session is optional), false on every row when logged out |
| GET | `/api/leaderboard/me` | yes | `{rank, points}` for the current user |
| GET | `/api/challenges` | | Every problem: `[{slug, title, difficulty, points, topic, lesson_slug, solved}]`, in module order, then lesson order; standalone problems last |
| GET | `/api/challenges/{slug}` | | One problem with what the editor needs (`id`, `title`, `description`, `description_lang`, `difficulty`, `points`, visible `test_cases`) plus `slug`, `topic`, `lesson_slug`, `solved`; 404 for an unknown slug |

The modules, lessons and challenges routes answer in Arabic for `Accept-Language: ar` (see
"Content languages"), and every route's messages do too ("API messages"); admin messages are
English.

Hidden test cases are never returned by the public routes. In a submit response they only
show `{"passed": bool}`. Only the admin routes below return them.

Challenges are the problems themselves, so a problem solved from `/challenges` or from its
lesson is the same completion and earns points once. `topic` is `{slug, title}` of the
problem's module. `solved` is true only for the requester (a session is optional, as on the
leaderboard) and always false for guests. `problems.lesson_id` is nullable: a problem with no
lesson is a standalone challenge, with `topic` and `lesson_slug` null; submit works the same
for it. `problems.slug` is unique and nullable; the import (`ContentImport`) sets a problem's slug to
its lesson slug. A standalone problem needs its own slug to appear at
`/challenges/{slug}`.

```bash
# Register (or log in with POST /api/login and the same email and password)
curl -X POST http://localhost:8000/api/register \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name":"Ada","email":"ada@example.com","password":"secret-password","accepted_terms":true}'
# -> {"user":{"id":"01a1792e-257b-7368-9b50-7f372b2a5c20","name":"Ada","points":0,...}}
# Only the frontend origin gets a session, so plain curl answers 419 here. The frontend
# sends the same request with its cookies and the CSRF header (see "Sessions").

curl http://localhost:8000/api/lessons/warm-up -H "Accept: application/json"

# Submit is sent by the logged-in frontend (session cookie and CSRF header), not by curl:
# POST /api/problems/01a1792e-257b-7368-9b50-7f372b2a5c21/submit
#   {"language":"python","code":"print(int(input()) * 2)"}
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

## Email

Two transactional emails, nothing else (no marketing, no images, no tracking pixels): the
verification email after register (and on request), and the password reset email. Both are
plain HTML plus a text part (`resources/views/mail/account*.blade.php`), in the language the
request was answered in (`lang/en/mail.php`, `lang/ar/mail.php`; Arabic is `dir="rtl"`).

- **Sent after the response.** There is no queue worker, so mail goes out in a deferred callback
  (`defer()`, `User::notifyAfterResponse`) once the response has been sent. A mail failure never
  fails or slows the request: it is caught and logged as `Mail could not be sent` with only the
  notification and exception class names (never the address, link or token); the user can ask
  again. Forgot password does the account lookup, the token and the mail all after the
  response, so neither the body nor the timing shows whether an account exists.
- **Verification.** New accounts start unverified and are logged in. The link is
  `GET /api/email/verify/{id}/{hash}`, signed, valid 60 minutes, `hash` = sha1 of the email. It
  works without a session and always redirects to `FRONTEND_URL/verify-email?status=success` or
  `?status=invalid` (bad or expired signature, wrong hash, unknown id; a non-UUID id is 404).
  Opening it again keeps the first verification. Responses show `email_verified` (boolean) only.
- **What needs a verified email.** Only `POST /api/problems/{id}/submit` (the only route that runs
  code and the only one that awards points): the `verified-email` middleware answers 403
  `{message, reason: "email_unverified"}`, before route binding. Everything else stays open;
  admin and owner routes keep their role rules. `user:make-admin` verifies the email; factory
  users are verified unless `->unverified()`.
- **Password reset.** Laravel's password broker: tokens hashed in `password_reset_tokens`, valid
  60 minutes, one use, one per account (a new one replaces the old). The link is
  `FRONTEND_URL/reset-password?token=...&email=...`. A successful reset sets the password, ends
  every session and remember cookie of the account (`User::endSessions()`, and the requesting
  browser's own session if it was logged in to that account) and does not log in. A banned user
  can reset and stays banned.

Local testing: keep `MAIL_MAILER=log`. Each email, link included, is written to
`storage/logs/laravel.log`; copy the link from there. Nothing is sent. Tests use the `array`
mailer (`phpunit.xml`) and `Notification::fake()`.

Production (Resend over SMTP, from `no-reply@algoslab.sa`), in the production `.env` only:

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=587
MAIL_USERNAME=resend
MAIL_PASSWORD=<the Resend API key>
MAIL_FROM_ADDRESS="no-reply@algoslab.sa"
MAIL_FROM_NAME="AlgosLab"
```

Port 587 with the `smtp` scheme upgrades to TLS (STARTTLS). Never commit the key. `MAIL_TIMEOUT`
(default 10 seconds) limits how long one SMTP connection may hang.

"After the response" depends on the server: with PHP-FPM (`fastcgi_finish_request`) the client
gets its answer before the mail is sent. The Docker image runs Apache with mod_php, where the
connection may stay open until the deferred mail is done (not verified here). After the deploy,
compare the time of `POST /api/forgot-password` for a known and an unknown email; if the known one
is clearly slower, switch the image to PHP-FPM.

Resend setup for `algoslab.sa` (owner steps):

1. Create a Resend account, then Domains > Add domain > `algoslab.sa` (pick the region closest
   to the users).
2. Add the DNS records Resend shows at the DNS host (Cloudflare: DNS only, not proxied):
   - SPF: the `MX` and `TXT` records on the `send` subdomain (`send.algoslab.sa`), exactly as
     shown. They do not change an SPF record on `algoslab.sa` itself.
   - DKIM: the `TXT` record `resend._domainkey` with the public key Resend shows.
   - DMARC: a `TXT` record `_dmarc` with `v=DMARC1; p=none; rua=mailto:contact@algoslab.sa` to
     start; move to `p=quarantine` once reports look clean.
3. Click Verify in Resend and wait until every record is green.
4. API Keys > Create, permission "Sending access", limited to `algoslab.sa`; put it in
   `MAIL_PASSWORD` in the production `.env`.
5. After the deploy, register with a real address and use forgot password once; check both
   emails arrive (not in spam), the links work and the Arabic one reads right to left.

Free plan limits: 3,000 emails a month and 100 a day. Every registration and every reset or
resend is one email.

## Social login

"Continue with Google" and "Continue with GitHub" on the frontend's login and register pages,
with Laravel Socialite (`laravel/socialite`). Code: `app/Http/Controllers/SocialLoginController.php`,
`app/Models/SocialAccount.php`; tests: `tests/Feature/SocialLoginTest.php`.

**Flow.** The buttons are plain links (a full page visit, not a fetch) to
`GET /auth/{provider}/redirect` on the API host (`google` or `github`; anything else is 404). It
redirects to the provider with a random `state` and a PKCE challenge (S256), both kept in the
session. The provider sends the browser back to `GET /auth/{provider}/callback`, which checks
the state, swaps the code for a token (with the PKCE verifier), reads the profile and then
redirects to the frontend: `FRONTEND_URL/` (logged in), `FRONTEND_URL/auth/complete` (new
account, see below) or `FRONTEND_URL/login?error=<code>`. The app only redirects to these paths;
nothing in the request chooses where to go.

**Why `routes/web.php`, not `routes/api.php`.** The API gets a session only for requests whose
`Origin` or `Referer` is the frontend (Sanctum's stateful check, "Sessions"). The callback is a
page visit coming from `accounts.google.com` or `github.com`, so in the API group it would have no
session, and Socialite keeps the state and the PKCE verifier in the session. The `web` group always
starts the session. Both groups use the same `algoslab-session` cookie, so the login made by the
callback is the one the frontend then uses. The cookie is `SameSite=Lax`, which a top-level GET
from the provider still carries. `SocialLoginTest` checks the callback with a provider Referer
and no Origin.

**What counts as a verified email.** Google: `email_verified` must be `true` in its userinfo
(scopes `openid email profile`). GitHub: scope `user:email`; Socialite then reads `/user/emails`
and gives only the address that is both primary and verified (else none; the public profile
email is never used, and an unreadable list counts as none). This was checked by running the
real drivers against mocked answers. Emails are lower-cased.

**Decision table** (in this order):

| Case | Result |
|---|---|
| Denied on the provider's page | `login?error=social_cancelled` |
| Bad or missing state, provider error, failed token or profile call | `login?error=social_failed` |
| No verified email from the provider | `login?error=social_email_unverified` |
| (provider, provider user id) already linked | that user: banned -> `social_banned`, else log in |
| Not linked, a user with this email, email verified | banned -> `social_banned`, else link and log in |
| Not linked, a user with this email, email NOT verified | `social_email_exists`; no link, no login (stops pre-account takeover) |
| Not linked, the user already has another account of this provider | `social_failed`; no link |
| New email | nothing created; pending sign-in in the session (15 minutes) -> `/auth/complete` |

Logging in writes the same `login` activity row as the password login (the log has no provider
column); a banned user gets `login_blocked_banned` and no session. Each login starts a new
session id. Failures log only `Social login failed` with the provider and an exception class or
code, never the email, the token or the provider's answer.

**Completing a new account.** `GET /api/auth/pending` returns `{name, email, provider}` (404 when
there is none or it expired). `POST /api/auth/complete` `{name, accepted_terms}` validates like
register (`accepted_terms`: `bail|required|accepted|boolean`) and makes the user: role `user`,
email verified, no password, `terms_accepted_at` and `terms_version` like register, plus the
`social_accounts` row; then it logs in (new session id), writes the `register` activity row and
returns `{user}` (201). Without a pending sign-in, or when the email or the provider account was
taken meanwhile (a second tab), it answers 410 and creates nothing; the pending sign-in works
once. Both are API routes, so only the frontend gets the session they need.

**Data.** `social_accounts`: `id` (UUIDv7), `user_id` (cascade delete), `provider`,
`provider_user_id`, timestamps; unique (provider, provider_user_id) and (user_id, provider). No
access or refresh token, avatar or profile is stored, and no endpoint returns the table.
`users.password` is nullable: a social-only account cannot log in with a password (the same 401
as a wrong password) until it sets one with "Forgot password"; the reset keeps the email verified.

**Limits.** Redirect and callback: 30 per minute per IP (`throttle:social`). Complete shares
register's limit (5 per minute per IP).

**Environment** (`.env.example` has empty placeholders; real values only in your own `.env`):

```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
```

The callback URL is `APP_URL/auth/{provider}/callback` (`config/services.php`), so `APP_URL` must be
the API's public address.

**Owner steps.**

1. Run `php artisan migrate` on every database (two migrations: `password` nullable and the
   `social_accounts` table).
2. Google (Google Cloud Console, console.cloud.google.com):
   1. Create or pick a project.
   2. Google Auth Platform (the "OAuth consent screen"): app name AlgosLab, support email,
      audience External, developer contact. Add the home page `https://algoslab.sa`, the privacy
      policy `https://algoslab.sa/privacy` and the terms `https://algoslab.sa/terms`; `algoslab.sa`
      goes under authorized domains (Google may ask you to verify it in Search Console).
   3. Data access (scopes): `openid`, `.../auth/userinfo.email`, `.../auth/userinfo.profile`. These
      are non-sensitive scopes.
   4. Clients > Create client > Web application. Authorized redirect URIs, exactly:
      `http://localhost:8000/auth/google/callback` and `https://api.algoslab.sa/auth/google/callback`.
      No JavaScript origins are needed.
   5. Copy the client ID and secret into `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`.
   6. While the app is in "Testing", only the test users you add can sign in. Publish it
      ("In production") before launch; Google may still check the branding (name, logo, domain,
      privacy policy link). Google's rules change: check the current Google Auth Platform help and
      the "Sign in with Google" branding guidelines when you do this.
3. GitHub (Settings > Developer settings > OAuth Apps > New OAuth App, or the organization's):
   1. Application name AlgosLab, homepage `https://algoslab.sa`.
   2. Authorization callback URL: `https://api.algoslab.sa/auth/github/callback`. An OAuth app has
      one callback URL, so make a second app for local work with
      `http://localhost:8000/auth/github/callback`.
   3. Generate a client secret; put the ID and secret into `GITHUB_CLIENT_ID` and
      `GITHUB_CLIENT_SECRET` (the local app's in your local `.env`, the production app's in the
      production `.env`). The app asks for the `user:email` scope itself.
4. Never commit the IDs or secrets; they live only in the `.env` files.
5. After the deploy, sign in once with each provider for real: a new account (the complete page),
   logging out and in again, and an existing verified account with the same email.

## Admin API

All under `/api/admin`, behind `auth:web`, `not-banned` and the `admin` middleware
(`EnsureUserIsAdmin`): no session is 401, a user who is neither admin nor owner is 403
(checked before route model binding, so they get 403 even for an id that does not exist),
admins and owners pass. Owner-only routes add the `owner` middleware (`EnsureUserIsOwner`,
403 for admins). The frontend hides these pages from others, but that is only convenience;
these checks are the security. Ids, not slugs, in the paths; ids are UUIDs, and an id that is
not a UUID or is unknown is 404 with the usual `{message}`. Edits show on the public routes
at once. Every successful write is in the audit log (see "Logs").

| Method | Path | Body | Result |
|---|---|---|---|
| GET | `/modules` | | Modules in order, each with its `lessons` (no body) |
| POST | `/modules` | `title`, `title_ar`, `slug`, `sort_order` | 201; new modules get track `algorithms` |
| PUT | `/modules/{module}` | `title`, `slug`, `sort_order` | The module (track unchanged) |
| DELETE | `/modules/{module}` | | 204, or 409 if it has lessons |
| GET | `/modules/{module}/lessons` | | Its lessons (no body) |
| POST | `/lessons` | `module_id` (lower-case UUID of an existing module, else 422), `title`, `title_ar`, `slug`, `body`, `body_ar`, `sort_order` | 201 |
| GET | `/lessons/{lesson}` | | The lesson with its full `body` and `body_ar` |
| PUT | `/lessons/{lesson}` | as POST | The lesson |
| DELETE | `/lessons/{lesson}` | | 204, or 409 if it has problems |
| GET | `/problems` | | All problems (no description) |
| POST | `/problems` | `lesson_id` (optional; lower-case UUID of an existing lesson, else 422), `title`, `title_ar`, `slug`, `description`, `description_ar`, `difficulty` | 201 |
| GET | `/problems/{problem}` | | The problem |
| PUT | `/problems/{problem}` | as POST | The problem |
| DELETE | `/problems/{problem}` | | 204 (its tests and submissions go with it), or 409 if anyone has a completion |
| GET | `/problems/{problem}/test-cases` | | All its tests, in `position` order, hidden ones and expected outputs included |
| POST | `/problems/{problem}/test-cases` | `input`, `expected_output`, `is_hidden` | 201 |
| PUT | `/problems/{problem}/test-cases/{testCase}` | as POST | The test case |
| DELETE | `/problems/{problem}/test-cases/{testCase}` | | 204, always allowed |
| GET | `/users?search=&role=&banned=&page=` | | 25 per page, newest first (by `created_at`): `id, name, email, role, points, banned_at, created_at, solved_count`; `search` matches name or email (`%` and `_` are literal), `role` is user/admin/owner, `banned` 1 or 0 |
| GET | `/users/{user}` | | The fields above plus `ban_reason`, `solved` (`title, slug, solved_at`) and the last 20 `submissions` (`problem, language, status, passed_count, total_count, runtime_ms, created_at`; never the code) |
| GET | `/users/{user}/activity?page=` | | The user's activity, 25 per page, newest first |
| POST | `/users/{user}/ban` | `reason` (required, max 255) | The user row; 409 if already banned |
| POST | `/users/{user}/unban` | | The user row; 409 if not banned |
| POST | `/users/{user}/logout-everywhere` | | The user row; all their sessions end (session rows deleted, remember token changed) |
| PUT | `/users/{user}/role` | `role`: `user` or `admin` | Owner only. The user row |
| POST | `/users/{user}/points` | `amount` (non-zero integer, -1,000,000 to 1,000,000), `reason` (max 255) | Owner only. The user row; 422 if points would go below 0 |
| POST | `/users/{user}/reset-progress` | | Owner only. Deletes their completions and sets points to 0 (submissions stay) |
| DELETE | `/users/{user}` | `confirm_email` (the exact email) | Owner only. 204, or 422 if the email does not match. Deletes their sessions; foreign keys delete their submissions and completions and set them to null in both logs |
| GET | `/audit-log?action=&actor=&from=&to=&page=` | | Owner only. 50 per page, newest first; `actor` is a user UUID, an email or `console`; `from`/`to` are `YYYY-MM-DD` UTC days, both included |
| GET | `/activity?event=&user=&from=&to=&page=` | | Owner only. 50 per page, newest first; `user` is a user UUID or an email |

User actions (everything above that changes a user) follow the rules in "Roles"; ban, unban,
log out everywhere, role, points, reset and delete never accept a password, a token or the
role `owner`, and unknown fields are ignored.

Rules (422 with a message per field unless noted):

- Slugs: required, unique in their table, only `a-z`, `0-9` and `-`, at most 100 characters.
- Titles at most 255 characters; `sort_order` an integer 0-100000; lesson `body` optional,
  at most 20,000 characters (and 65,535 bytes, the TEXT column); problem `description`
  required, at most 5,000 characters; `difficulty` is `easy`, `medium` or `hard`.
- Arabic fields (`title_ar`, `body_ar`, `description_ar`) are optional, with the same limits as
  their English field (`body_ar` is checked in bytes too); an empty one is stored as NULL. The
  audit log records them like the English ones (`body_ar` only as "changed"). 255 Arabic
  letters fit a title (VARCHAR counts characters); 5,000 characters of a description are at
  most 20,000 bytes, so they always fit its TEXT column.
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
  -e APP_NAME=AlgosLab -e APP_KEY=base64:... -e APP_ENV=production -e APP_DEBUG=false \
  -e DB_CONNECTION=mysql -e DB_HOST=host.docker.internal -e DB_PORT=3307 \
  -e DB_DATABASE=... -e DB_USERNAME=... -e DB_PASSWORD=... \
  -e SANDBOX_URL=http://host.docker.internal:8001 -e SANDBOX_SECRET=... \
  algosproj-backend
```

- Environment variables: `APP_NAME` (`AlgosLab`; the session cookie name), `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `DB_*`, `SANDBOX_URL`,
  `SANDBOX_SECRET`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DRIVER`,
  `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `SESSION_LIFETIME`,
  `AUTH_REMEMBER_MINUTES` (see "Sessions"). Generate a key with
  `docker run --rm algosproj-backend php artisan key:generate --show`.
- Inside a container, `DB_HOST` and `SANDBOX_URL` must point at `host.docker.internal`
  (or a Docker network name), not `127.0.0.1`.
- Migrations are not run by the image. Run them with
  `docker exec <container> php artisan migrate --force`.

## Deploy checklist

- Run `php artisan migrate --force` and `php artisan content:sync` (see "content:sync"), then
  make the first owner with
  `php artisan user:make-admin <email> --owner` (the account must be registered first).
- Run `php artisan activity:prune` once a day (cron or the Laravel scheduler); it is not
  scheduled in the code yet.
- Set the production values from "Sessions": `APP_NAME=AlgosLab` (session cookie
  `algoslab-session`, as the Cookies page says), frontend and API on the same registrable domain,
  `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS`, `FRONTEND_URL`.
- Configure trusted proxies when running behind Cloudflare or any other proxy
  (`$middleware->trustProxies(...)` in `bootstrap/app.php`). Until then the request IP is the
  proxy's, so both logs record the wrong IP and the login and register rate limits, which key
  on the IP, count every user behind the same proxy together. The hourly email limits too: without
  it, 10 forgot-password or resend requests an hour would be shared by every visitor.
- The logs store IP addresses and emails; cover that in the privacy policy before launch.
- Social login: run the migrations, create the Google and GitHub OAuth apps with the production
  callback URLs, set the four `GOOGLE_*`/`GITHUB_*` values and test a real sign-in ("Social login").
- Email: the production `MAIL_*` values, the Resend DNS steps and the response-time check in "Email". Trusted proxies also
  matter here: without them the verification link is built and checked with the proxy's `http`
  scheme instead of `https`.
