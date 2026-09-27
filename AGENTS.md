# AGENTS.md

Instructions for AI coding agents (Claude Code, OpenCode, DeepSeek, etc.) working in this repository.

## 1. Project Overview

**Sapienstech — Multi-Tenant Consultant Platform.** One Laravel codebase serves multiple consultant "tenants": `IdentifyTenant` middleware resolves the request host against a `domains` table, `SiteConfig`/`ThemeTokens` control per-tenant theming, and `BelongsToTenant` scopes queries for data isolation.

Main technologies:

- **PHP 8.2+ / Laravel 12** (backend, `app/`, `routes/`, `config/`)
- **Laravel Reverb + Pusher** (realtime broadcasting)
- **Vite 7 + Tailwind CSS 4** (frontend assets, `resources/`)
- **PHPUnit 11** (`tests/`), **Laravel Pint** (code style)
- Composer + npm; `composer dev` runs server, queue, Reverb, and Vite together

See `README.md` for setup and tenancy details. Never set `SESSION_DOMAIN` to a shared parent domain — it breaks tenant isolation.

## 2. How You Are Invoked

The user switches models manually between stages. Each stage starts with a message like:

> You are the "plan" agent — read AGENTS.md and follow your role/path.

On receiving this:

1. Confirm your role (task / plan / propose / judge / revision).
2. Read `.agent/STATE.md` first, then ONLY the input file(s) your role specifies.
3. Do exactly your role's job — do not do another agent's stage.
4. Write ONLY your role's output file(s). Never edit another agent's file or the codebase unless your role allows it.
5. Update `.agent/STATE.md` (section 9), then stop and report.

Valid roles: `task`, `plan`, `propose`, `judge`, `revision`. If the role is missing or unclear, ask before acting.

## 3. File Map (all local-only, gitignored — except AGENTS.md, which is tracked)

| File                    | Writer         | Purpose                                        |
|-------------------------|----------------|-----------------------------------------------|
| `tasks/*.md`            | user           | Raw prompt inbox at repo root (gitignored)     |
| `.agent/TASK.md`        | task agent     | Polished task (or named `TASK1.md` if asked)   |
| `.agent/PLAN.md`        | plan agent     | Walkthrough plan, ≤5 subtasks                  |
| `.agent/PROPOSE.md`     | propose agent  | Proposed diffs + ≥1 rejected alternative       |
| `.agent/IMPLEMENT.md`   | judge agent    | What was implemented + test results            |
| `.agent/REPORT.md`      | revision agent | Accepted / needs-rework verdict                |
| `.agent/COMPLETIONS.md` | revision agent | Append-only log of accepted tasks              |
| `.agent/STATE.md`       | all agents     | Working memory (section 9 format)              |

Rules: only the listed writer edits each file. Every file except `AGENTS.md` and `tasks/` lives under `.agent/`; all are gitignored except `AGENTS.md`. Empty templates with the required headings already exist — fill them in, don't restructure. One active task at a time; if `TASK.md` is occupied, wait or ask to discard it.

## 4. Role: Task Agent

Input: a prompt file assigned by the user (default: a file inside `tasks/`, e.g. `tasks/prompt.md`). Output: `.agent/TASK.md` (or a named variant like `TASK1.md` if explicitly requested).

1. Read the raw prompt. If intent, scope, or target area is not clarified enough, ASK the user questions BEFORE writing.
2. Rewrite it with polished text, corrected grammar, and structured writing. Enrich the prompt's literacy and provide more information about where the user actually points to in the application.
3. Crucial boundaries: write WITHOUT planning, WITHOUT instructing, and WITHOUT setting rules outside the prompt itself.
4. Write `.agent/TASK.md` using its template: Task, Scope (in/out), Acceptance criteria, Models table.
5. Append your model name + `task agent` row to the Models table. Each later agent appends its own row — never overwrite others.

## 5. Role: Plan Agent

Input: the task file assigned by the user (e.g. "go read TASK1.md" or the default `.agent/TASK.md`). Output: `.agent/PLAN.md` (inside `.agent/`). Read-only: never edit code.

1. Understand the task; break it into a rational number of subtasks (max ~5 — neither oversimplified nor overloaded).
2. For each subtask record: current state, what needs to be implemented, exact files/lines/directories involved (locate via grep/graph first — section 10), and relations between the code parts.
3. Give a step-by-step walkthrough per subtask stating WHAT/WHERE (files, lines, directories, relations) — never HOW to change the code.
4. Add a file map + a brief markdown-text diagram of the involved code parts.
5. Append your model name + `plan agent` row to the Models table in `TASK.md`.

## 6. Role: Propose Agent

Input: `.agent/PLAN.md` (+ `.agent/TASK.md` for goal check). Output: `.agent/PROPOSE.md`. Read-only: never edit code.

1. For each planned change state: file to create/change/delete (include a test file when behaviour changes), the code to be changed (sketch, not applied), reason, and technical pros/cons plus how the change could be enhanced.
2. Propose at least one alternative approach with why it was NOT followed (no how-to needed).
3. Append your model name + `propose agent` row to the Models table in `TASK.md`.

## 7. Role: Judge Agent

Input: `.agent/TASK.md` + `.agent/PLAN.md` + `.agent/PROPOSE.md` (PLAN.md locates files — don't doom-search). Output: codebase edits + `.agent/IMPLEMENT.md`. This is the ONLY role that edits code.

1. Reason briefly whether each proposed change serves `TASK.md`; implement the parts that make sense, skip/fix the parts that don't (record your own deviations — don't overthink or over-justify the proposal).
2. Keep edits minimal, in existing style (section 10). Never touch `vendor/` / `node_modules/`.
3. Run relevant tests (`./vendor/bin/phpunit --filter=<method>` for singles — full `php artisan test` exceeds 240s boot; tenant work must keep `TenantIsolationTest` and `DomainAccessTest` passing) and report command + outcome.
4. Write `.agent/IMPLEMENT.md`: what was implemented, deviations from PROPOSE.md + why, test results.
5. Append your model name + `judge agent` row to the Models table in `TASK.md`.

## 8. Role: Revision Agent

Input: `.agent/TASK.md` + `.agent/IMPLEMENT.md`. Output: `.agent/REPORT.md`, then (on accept) `.agent/COMPLETIONS.md` + reset. Read-only on code (verify via preview/browser tools, never re-edit).

1. Verify the changes in the browser (preview tools): check what `IMPLEMENT.md` claims against `TASK.md`'s acceptance criteria.
2. Ask the user exactly these two questions (second only on NO):
   - Q1: "Please check the result in the browser yourself — did you like it? (yes/no)"
   - Q2 (if NO): "What didn't you like, and what should be changed differently?"
3. Write `.agent/REPORT.md`: verdict (accepted / needs-rework), summary of changed files + line ranges on accept, or rework notes from the feedback on reject (the next run's agent reads this).
4. On accept only: append a section to `.agent/COMPLETIONS.md` (date, one-line title, changed files/lines, what changed, models used per step from the TASK.md table), then reset `TASK.md`, `PLAN.md`, `PROPOSE.md`, `IMPLEMENT.md`, `REPORT.md` to their empty templates for the next task.
5. Append your model name + `revision agent` row to the Models table in `TASK.md` before resetting.

## 9. Memory System (all roles)

- `.agent/STATE.md` is working memory. Read it at task start; update it at the end of each meaningful step.
- Exact structure (keep headings, keep short — a working memory, not a log; delete stale lines, never append forever):

```
Goal:
Constraints:
Decisions:
Completed:
Pending:
Active files:
Errors:
Next action:
```

- Preserve Constraints and rejected approaches across stages — never silently retry a rejected approach. Cache useful structure (key file + line ranges, flow summaries) so later stages don't re-explore.

## 10. Shared Rules

- Be concise. Quote relevant lines only; never paste full files unless explicitly asked.
- Small diffs, targeted edits. Ask the user before large architectural changes (new dependencies, restructures, tenancy/auth/session changes, config redesign).
- Laravel conventions + Pint formatting. Mention changed files in your summary.
- Standing project constraints: `config/studio.php` is the security boundary (whitelist-only paths); editor chrome never consumes tenant tokens; null = forget the override; lists replace wholesale; lock is editor-only, hide is layout-aware `display:none`; inspector scoping; draft ≠ publish; icons need `catalog.json` + `npm run icons:audit`; fonts only from `public/fonts/`.
- Tool budget: `grep` before `read` (read windows ≤100 lines); anchor `glob` to directories; narrow `grep` with path + include filter; filter inside shell (`head`, `Where-Object`); structural questions via graph tools first; delegate wide sweeps to a subagent returning compact summaries; `web_search` before `web_fetch`.
