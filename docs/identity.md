# Login identities (شناسهٔ ورود)

How a person signs in, and how their identity is stored. One home for the
formats is `app/Support/IranianIdentity.php`; the shape→column mapping is
`app/Support/LoginIdentifier.php`.

## The three identifiers

| Column | Meaning | Unique | Nullable |
|--------|---------|--------|----------|
| `email` | legacy + optional identifier | per tenant (`tenant_id`, `email`) | yes |
| `phone` | mobile, canonical `09xxxxxxxxx` | per tenant (`tenant_id`, `phone`) | yes |
| `national_code` | کد ملی, 10 digits with checksum | per table (index) + platform-wide check | yes |

`users` and `students` both carry all three columns
(`database/migrations/2026_10_22_000001_add_identity_columns_to_users_and_students.php`).
The login form field is `identifier`; both guards validate it as
`required|string|max:255` and report failures under that one key.

## Normalization

`IranianIdentity` is the only writer of the stored form:

- Persian (`۰-۹`) and Arabic-Indic (`٠-٩`) digits → ASCII.
- Phone: spaces, dashes and `+98` / `0098` / `98` prefixes are accepted;
  anything that does not reduce to `^09\d{9}$` is invalid.
- کد ملی: non-digits are stripped; validity is 10 digits, not all the same
  digit, and a correct checksum.

Model setters (`App\Models\Concerns\NormalizesIdentity`) canonicalize on
write; form requests / controllers canonicalize **before** validation
(`prepareForValidation`) so `Rule::unique` sees the same value the DB index
will. Unparseable input is kept as typed, so the format rules report it
instead of a silent drop.

## Login lookup

`LoginIdentifier::candidates($raw)` decides by shape: an `@` tries only
`email`, a valid mobile only `phone`, a valid کد ملی only `national_code`.
An unshaped value falls back to all three columns so a partially typed or
legacy value never 500s and never misses silently. The lookup is always
scoped to the request's tenant (`where('tenant_id', tenant()->id)`), so the
same phone may belong to accounts on different tenants.

Removed students (`removed_at`) fail with the same generic `identifier`
error as a wrong password — the login page never confirms a removal.

## Uniqueness

- **Phone** — a `(tenant_id, phone)` unique index on each table. A number may
  exist on two tenants, and a user + a student on the *same* tenant may share
  it (guards are separate); within one table and tenant it is one row.
- **کد ملی** — unique per table (index) plus
  `IranianIdentity::assertAvailable()`: one code = one person across `users`,
  `students` and tenants. Because one DB index cannot span two tables, that
  check is **best-effort under concurrency**; the two indexes are the last
  line of defense (a raced duplicate fails the write, never silently both).

## Enrolment paths

`StudentEnrollment` (public join + consultant hand-entry):

- The active-roster guard and row reuse match on the **union** of
  phone / کد ملی / email — a legacy email-only row must not be duplicated
  (unique keys) nor overwritten (account takeover).
- An **active** match is refused (error key `phone`); a **removed** row is
  reused, restoring the person's history.
- Re-applying reuses the open request row (`open_key`, primary identity
  first: `nc:` → `ph:` → `em:`).

Profile editors (`consultant.settings.profile`, `student.settings.profile`)
run the same availability check with the edited account ignored, so an
account may keep its own values but never adopt another's. Email is optional
on every path that phones/national codes cover; the consultant hand-entry
form requires name + phone.

## Legacy accounts

Rows that predate the identity columns keep working: they sign in with their
email, have `NULL` phone/کد ملی until they add them in the profile editor
(NULLs are exempt from the unique rules and coexist freely), and no path
requires the new columns to be present.

## Tests

- `tests/Unit/IranianIdentityTest.php` — normalization + candidate shapes.
- `tests/Feature/LoginIdentifierTest.php` — both guards, all shapes, tenant
  scoping, generic failure.
- `tests/Feature/IdentityUniquenessTest.php` — tenant/global uniqueness and
  NULL coexistence.
