# Pharmalab

Pharmalab is a mobile-first clinical learning platform for pharmacy rotations. Phase 1 connects rotation assignments, de-identified student case documentation, faculty review, correction, approval, and a student portfolio.

This repository currently contains the **FND-01 application foundation**: Laravel 13, Inertia 3, Vue 3, TypeScript, PostgreSQL, Redis, authentication, institution scoping, role dashboards, authorization tests, and CI.

## Requirements

- Docker Desktop
- Node.js 22.18+
- npm 10+

PHP, Composer, PostgreSQL, and Redis run in containers; no host installation is required.

## Clean-clone setup

```powershell
Copy-Item .env.example .env
docker compose build app
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose up -d postgres redis
docker compose run --rm app php artisan migrate:fresh --seed
npm install
npm run build
docker compose up -d app
```

Open `http://localhost:8000`.

For frontend development, run `npm run dev` in a second terminal. The application container continues serving Laravel at port 8000.

## Demo accounts

All seeded demo accounts use the password `password`.

| Role                      | Email                    |
| ------------------------- | ------------------------ |
| Student                   | `student@pharmalab.test` |
| Faculty / preceptor       | `faculty@pharmalab.test` |
| Institution administrator | `admin@pharmalab.test`   |

Demo credentials are local-development data only and must never be used in a deployed environment.

## Common commands

```powershell
# Backend tests, PHP formatting, and static analysis
docker compose run --rm app composer test

# Frontend checks
npm run check
npm run types:check

# Rebuild seeded local database
docker compose run --rm app php artisan migrate:fresh --seed

# Follow application logs
docker compose logs -f app
```

## Architecture and planning

The source-of-truth documents are in [`docs/`](docs/). Clinical fields remain provisional until institutional forms, rubrics, and privacy requirements are validated.

The next engineering deliverable is `SYNC-SPIKE-01`, followed by the walking skeleton.
