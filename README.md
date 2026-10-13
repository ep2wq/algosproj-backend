# algosproj-backend

Laravel API for the algosproj platform. It owns auth, lessons, submissions and the
leaderboard, and it is the only service that calls the sandbox — the frontend never
talks to the sandbox directly.

## Running it

```bash
composer install
cp .env.example .env   # if .env is missing
php artisan key:generate
php artisan serve
```

The API is then available at http://localhost:8000.
