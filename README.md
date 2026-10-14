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
  `SANDBOX_SECRET`. Generate a key with
  `docker run --rm algosproj-backend php artisan key:generate --show`.
- Inside a container, `DB_HOST` and `SANDBOX_URL` must point at `host.docker.internal`
  (or a Docker network name), not `127.0.0.1`.
- Migrations are not run by the image. Run them with
  `docker exec <container> php artisan migrate --force`.
