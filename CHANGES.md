# Consultant Student Schedule — Change Set

Environment note up front: I only had the specific files you uploaded, no
working copy of the app (no vendor/, no artisan, no PHP interpreter
available to me). Everything below was written by hand against the schema
and code you provided and reasoned through carefully, but **I did not run
`artisan test` or `php -l`** — see "Tests" and "What I could not verify"
at the bottom before merging.

## 1. Files changed

**New**
- `app/Models/ScheduleItem.php`
- `app/Models/ItemComment.php`
- `app/Http/Requests/Consultant/StoreScheduleItemRequest.php`
- `app/Http/Requests/Consultant/UpdateScheduleItemRequest.php`
- `app/Http/Controllers/Consultant/StudentScheduleController.php`
- `resources/views/consultant/students/schedule.blade.php`
- `resources/js/features/consultant-schedule.js`
- `database/factories/UserFactory.php` (didn't exist despite `User` using `HasFactory`)
- `database/factories/ScheduleItemFactory.php`
- `tests/Feature/Consultant/StudentScheduleTest.php`

**Modified**
- `routes/web.php` — the `/schedule` route now points at `StudentScheduleController::edit` instead of the generic placeholder, plus new JSON routes nested under it. Route **name unchanged** (`consultant.student.schedule`), so any existing links (e.g. the dashboard's Actions menu) keep working.
- `app/Http/Controllers/Consultant/StudentFeatureController.php` — removed `'schedule'` from the placeholder `LABELS` map, since it's now handled by its own controller.

**Not touched:** `Student.php`, `Tenant.php`, `User.php`, `BelongsToTenant.php`, `IdentifyTenant.php`, `EnsureConsultantFeature.php`, `ConsultantDashboardController.php`, `ConsultantFeatureController.php`, `config/consultant.php` (the `student_schedule` feature key already existed and gates the new route exactly as it gated the old placeholder).

## 2. Database changes

**None.** `schedule_items` and `item_comments` already had every column the feature needs. I did not add a migration because your project tree has no `database/migrations` directory at all — the schema is clearly provisioned from the SQL dump rather than Laravel migrations, so adding one would be inconsistent with how the rest of the app manages its schema.

I used `item_comments` (not `event_comments`) for comments: `event_comments` hangs off the unrelated `schedule_events` table (a `user_id`-only calendar, no student/book/test fields), while `item_comments.item_id` matches `schedule_items.id` and the reference editor's own `get_comments_for_item&item_id=...` call.

## 3. Routes

All under the existing `consultant.student.` group (prefix `consultant/students/{student}`), gated by the pre-existing `consultant.feature:student_schedule` middleware:

| Method | URI | Name | Purpose |
|---|---|---|---|
| GET | `/schedule` | `consultant.student.schedule` | Calendar page (unchanged name) |
| GET | `/schedule/items` | `consultant.student.schedule.items.index` | Week's events (JSON) |
| POST | `/schedule/items` | `consultant.student.schedule.items.store` | Create item |
| PUT | `/schedule/items/{item}` | `consultant.student.schedule.items.update` | Update item |
| DELETE | `/schedule/items/{item}` | `consultant.student.schedule.items.destroy` | Delete item |
| GET | `/schedule/items/{item}/comments` | `consultant.student.schedule.items.comments` | List comments (read-only) |

## 4. Security / tenant isolation

- **Tenant scoping is at the data layer, not the controller.** `ScheduleItem` uses the same `BelongsToTenant` trait as `Student`/`User`, so every query (including route-model-bound `{item}`) is automatically filtered to `tenant()->id`. An item id from another tenant simply doesn't resolve — Laravel throws 404 before the controller method body runs.
- **Explicit tenant re-check on `{student}`**, identical to `StudentFeatureController::profile()`'s existing pattern (`abort_unless($tenant && $student->tenant_id === $tenant->id, 404)`), so a missing tenant context fails closed.
- **Same-tenant IDOR guard:** `assertItemBelongsToStudent()` checks `$item->student_id === $student->id`. Without this, a consultant could pair a student id they legitimately manage with a *different* same-tenant student's item id (both would pass the tenant scope) and reach/modify data they shouldn't.
- **Student-authored items are read-only to consultants.** `update`/`destroy` reject `item_type !== 'consultant_event'` with 403, so a consultant can't edit or delete a student's personal block through this endpoint.
- **Server never trusts client-computed datetimes.** Create/update accept `week_start_date` + `day_index` + `start_time`/`end_time`; the controller derives `start_datetime`/`end_datetime` itself (`resolveDatetimes()`), and rejects `end <= start` with a 422. `tenant_id` and `student_id` are never accepted from request input — they come from the resolved tenant and the route-bound `{student}`.
- **CSRF** stays on by default for `POST`/`PUT`/`DELETE` (standard `web` middleware group); the JS sends the token via `X-CSRF-TOKEN` from a `data-csrf` attribute rendered with `csrf_token()`.
- **One thing I deliberately did *not* add:** per-consultant assignment enforcement via `consultant_student_assignments`. That table has zero rows in the dump and nothing in the codebase you gave me enforces it yet — `ConsultantDashboardController` lists *all* tenant students unfiltered. Enforcing it only inside the new schedule feature would 403 every consultant for every student inconsistently with the rest of the app. I scoped this change to match the isolation level actually enforced elsewhere (tenant-only). If you want assignment-based restriction, it should probably be added consistently across the dashboard + all student-feature routes, which is a bigger, separate change.

## 5. Tests

`tests/Feature/Consultant/StudentScheduleTest.php` — 9 tests:
- page loads for an authorized consultant
- week items endpoint returns events
- create succeeds and persists
- create rejects end-before-start (422)
- update + delete both work and persist
- cross-tenant page access → 404
- cross-tenant update-by-guessed-id → 404, row untouched
- cross-tenant delete-by-guessed-id → 404, row untouched
- same-tenant, different-student item access → 404, row untouched

**I did not run these.** I have no PHP interpreter, no `vendor/`, and no live database in this environment — I could only reason through the code by hand. Please run `php artisan test --filter=StudentScheduleTest` yourself before trusting this as "passing." I also added `DatabaseTransactions` rather than `RefreshDatabase` in the test, since there's no migrations folder to refresh from — confirm your test DB already has these tables (e.g. seeded from the SQL dump) or swap in whatever your existing tests use.

## 6. Manual verification steps

1. **Open a schedule:** log in as a consultant, go to the dashboard, click "برنامه" for any student → weekly grid loads, current week highlighted.
2. **Create:** drag on an empty cell → modal opens pre-filled with the dragged time range → fill title, save → event appears in the right cell.
3. **Edit:** click an existing (solid, non-dashed) event → modal opens populated with its data, including completion status and any comments → change title/time → save → grid updates.
4. **Delete:** open an event → "حذف برنامه" → confirm → event disappears.
5. **Week navigation:** use the prev/next buttons → date range label updates, events reload for that week.
6. **Mobile:** narrow the viewport (<1024px) → day tabs appear, grid shows one day at a time, drag-to-create still works via touch.
7. **Unauthorized access:** as a consultant, manually edit the URL to a student id from a different tenant's domain → expect 404, not the schedule.
8. **Cross-tenant item id:** with dev tools, note a schedule item id from tenant B, then attempt `PUT .../items/{that-id}` as a tenant-A consultant on one of your own students → expect 404 and no DB change.

## What I could not verify

- I could not confirm the exact contents of `resources/views/layouts/consultant.blade.php`, `resources/views/components/consultant/topnav.blade.php`, or `vite.config.js` — they weren't uploaded. The Blade view assumes the layout defines a `content` `@section` and already loads Tailwind/Vazirmatn/the top nav (consistent with how the other consultant pages must work). You'll need to confirm `resources/js/features/consultant-schedule.js` is added to `vite.config.js`'s `input` array so `@vite([...])` in the Blade view actually resolves.
- I could not confirm how consultants authenticate (`auth()->user()` vs. something session-based like `ConsultantDashboardController`'s `session('username')`). I used the standard Laravel `auth()` helper; if your app uses a custom guard/session scheme instead, `created_by_user_id` and the `auth()->check()` calls in the form requests will need to point at that instead.
- I fixed what looks like an inverted label in the reference file: `next-week-btn`/`prev-week-btn` ids and their `title="..."` text didn't match the direction they actually navigated in the original `consultant_schedule_editor.php`. I wired the JS so the button labeled "previous" goes back and the one labeled "next" goes forward — worth a quick visual check on your end.
