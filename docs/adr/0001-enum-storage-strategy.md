# ADR 0001: Enum storage strategy

- **Status:** Accepted
- **Date:** 2026-08-12

## Context

CLAUDE.md §6 requires enums as native PHP backed enums, not string literals, but leaves
open whether the database column enforces the value set via a Postgres `CHECK` constraint
or relies solely on the application layer, and asks for one consistent choice, documented.

## Decision

Enum-shaped columns (`role`, `status`, `verification_status`, `pricing_type`, etc.) are
plain `string` columns in the migration, with no `CHECK` constraint at the database level.
Validity is enforced entirely at the application boundary: a FormRequest rejects invalid
input with a `422`, and the Eloquent model casts the column to a native PHP backed enum
(`protected function casts(): array { return ['status' => UserStatus::class]; }`), so an
out-of-range value from any other write path throws immediately on hydration rather than
persisting silently.

## Consequences

- Adding or renaming an enum case is a single PHP change with no migration required,
  which matters here because several of these enums (verification/approval statuses,
  document types) are still expected to evolve during early development.
- The database itself does not reject a bad value written outside Eloquent (a raw SQL
  script, a future service in another language). This is an accepted gap for a modular
  monolith with one write path; revisit if a second writer to these tables appears.
- Every enum column must have a corresponding cast on its model — reviewers should treat
  a string enum column without a cast as a bug.

## Alternatives considered

- **Postgres `CHECK` constraint per enum column:** gives DB-level enforcement independent
  of the application, but every enum case addition becomes a migration
  (`ALTER TABLE ... DROP CONSTRAINT ... ADD CONSTRAINT ...`), which is friction the project
  doesn't need yet while the domain model (especially Freelance and Business verification
  flows) is still settling. Revisit once these enums stabilize.
- **Native Postgres `ENUM` type:** rejected — altering a Postgres enum type's values is
  more awkward than a `CHECK` constraint (`ALTER TYPE ... ADD VALUE` can't run inside a
  transaction in older Postgres, and values can't be removed at all), and there's no read
  path in this project that needs the marginal storage/index savings over `varchar`.
