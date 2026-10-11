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

## Demo environment

A separate, fully fictional **Pharmalab Demo College** can be created on Railway staging or local Docker for client demonstrations. It never touches the local-development accounts above.

Required variables:

```env
DEMO_MODE=true
SEED_DEMO=false
DEMO_PASSWORD=<at least 12 characters>
```

- `DEMO_MODE=true` allows the demo commands to run; without it they refuse and change nothing.
- `SEED_DEMO=false` stops `docker/start.sh` from re-creating the older local-development accounts on every boot alongside the demo institution. Existing accounts are not removed automatically.
- `DEMO_PASSWORD` is the password for every demo account. It is never printed or logged.

Commands (on Railway: `railway ssh`, then run them):

```sh
php artisan demo:seed          # create anything missing; rotate demo passwords
php artisan demo:reset         # show what will be removed, confirm, then rebuild
php artisan demo:reset --force # same without the prompt (safety checks still run)
```

`demo:seed` leaves existing demo cases unchanged; use `demo:reset` to restore them after a demonstration. `demo:reset` deletes only **Pharmalab Demo College** data (slug `pharmalab-demo-college`) and refuses — even with `--force` — if that institution contains any account outside the four demo emails below. Deployment never automatically seeds or resets demo data.

**Do not create people in Pharmalab Demo College** (for example through the administrator's People page). Reset refuses to delete any account outside the four demo emails, because such an account could belong to a real person; recovering from that refusal means removing those accounts and the records they created by hand in the database.

| Role                          | Email                          |
| ----------------------------- | ------------------------------ |
| Institution administrator     | `admin@pharmalab-demo.test`    |
| Faculty / preceptor           | `faculty@pharmalab-demo.test`  |
| Student (all case states)     | `student1@pharmalab-demo.test` |
| Student (second progress row) | `student2@pharmalab-demo.test` |

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
