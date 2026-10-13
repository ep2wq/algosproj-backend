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
php artisan migrate
php artisan serve
```

Run the tests with `php artisan test`.

## Environment variables

On top of the usual Laravel `APP_*` and `DB_*` settings:

- `SANDBOX_URL` — base URL of the sandbox service
- `SANDBOX_SECRET` — shared secret sent to the sandbox as the `X-Sandbox-Secret` header
