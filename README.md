# KIC System — Multi-Service Marketplace Platform

A production multi-service marketplace (comparable to Thumbtack / Angi / TaskRabbit /
Upwork) with two transaction models under one roof:

- **Services & bookings** — customers book verified provider businesses; providers respond
  with quotations; payment happens on quotation acceptance.
- **Freelance marketplace** — clients publish projects; freelancers submit proposals;
  hiring creates a contract broken into milestones paid from escrow.

Four account types: **Customer, Provider (Business), Freelancer, Administrator.**

## Phase 1 scope (current)

Phase 1 is **web only**: a Laravel 12 REST API (`/api`) and a responsive React +
TypeScript SPA (`/web`), including the admin surface. There is no native mobile app yet —
that's Phase 2 (React Native, customer/provider/freelancer only; admin stays web-only
forever). Phase 1 code is written to not block that later phase — see CLAUDE.md §9.

## Repository layout

```
/api                # Laravel 12 API (not yet scaffolded)
/web                # React + TypeScript SPA (not yet scaffolded)
/docs
├── SRS.md          # source of truth for architecture, entities, workflows
└── PROJECT_SETUP.md
/.github/workflows  # CI
CLAUDE.md           # operating manual for AI-assisted development in this repo
```

## Local stack

The local environment — PostgreSQL + PostGIS, Redis, MinIO (S3-compatible storage), and
Mailpit (SMTP catcher) — runs via Docker Compose and doesn't depend on `api`/`web` being
scaffolded yet:

```bash
cp .env.example .env
make up          # or: docker compose up -d
```

| Service | URL |
|---|---|
| MinIO console | http://localhost:9001 |
| Mailpit UI | http://localhost:8025 |
| Postgres | `localhost:5433` (`make psql`) |
| Redis | `localhost:6379` (`make redis-cli`) |

See [`docs/PROJECT_SETUP.md`](docs/PROJECT_SETUP.md) for first-run steps, the full
environment variable list, and the third-party account checklist. The Stripe CLI (for
webhook forwarding) is needed once `api/` exists — see that doc.

## Where to start

- [`CLAUDE.md`](CLAUDE.md) — stack choices, hard constraints, domain module layout, and
  coding conventions for this repo.
- [`docs/SRS.md`](docs/SRS.md) — the source of truth for architecture, entities, and
  workflows. If anything is ambiguous, this document wins.
