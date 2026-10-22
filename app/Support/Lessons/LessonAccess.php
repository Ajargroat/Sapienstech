<?php

namespace App\Support\Lessons;

use App\Models\BookLesson;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * The ONE authorization class for the staff lesson surface.
 *
 * The same controllers are mounted twice — /consultant/lessons/* and
 * /teacher/lessons/* — and the user's rule is "one controller set, one access
 * class". So the gate lives here, never in the route group alone:
 *
 *  - the actor must be a tenant user whose tenant matches the lesson's;
 *  - the `lesson_library` flag must be on for that tenant;
 *  - the actor's role must own the *panel they are standing in*: a teacher
 *    belongs to /teacher, a consultancy's staff/owner to /consultant. A user
 *    who is both keeps whichever panel they entered through, and neither
 *    prefix can be used to bypass the other's shell.
 *
 * Anything else answers 404, like every other gated section of this app.
 */
class LessonAccess
{
    /** The panel prefixes the surface is mounted under. */
    public const PANELS = ['consultant', 'teacher'];

    public static function enabled(): bool
    {
        return (bool) site('features.lesson_library', false);
    }

    /** The prefix the current request is standing in ("consultant"|"teacher"|null). */
    public static function currentPanel(?string $routeName = null): ?string
    {
        $routeName ??= Route::currentRouteName();

        if (! is_string($routeName)) {
            return null;
        }

        foreach (self::PANELS as $panel) {
            if (str_starts_with($routeName, $panel.'.lessons.')) {
                return $panel;
            }
        }

        return null;
    }

    /**
     * The panels the given actor may author lessons in.
     *
     * Takes the authenticated ACTOR, not a `User`: the staff surface lives
     * behind the `web` guard, an authenticated STUDENT also reaches `web`
     * middleware, and a `?User` type hint turned that case into a TypeError
     * (a 500) instead of the 404 the guest rule promises. Anything that is not
     * a staff user therefore returns [] and every caller answers 404.
     */
    public static function panelsFor(mixed $actor): array
    {
        if (! $actor instanceof User || ! self::enabled()) {
            return [];
        }

        $user = $actor;
        $tenantId = tenant()?->id;

        if ($tenantId === null || (int) $user->tenant_id !== (int) $tenantId) {
            return [];
        }

        $panels = [];

        if ($user->isTeacher()) {
            $panels[] = 'teacher';
        }

        if ($user->isTenantAdmin() || $user->isConsultantStaff()) {
            $panels[] = 'consultant';
        }

        return $panels;
    }

    /** May the current actor author lessons at all? (used by nav + helper) */
    public static function staffCanAuthor(): bool
    {
        // Auth::user() is the `web` guard: a student session can answer here,
        // which is exactly why panelsFor() checks the model, not the truthiness.
        return self::panelsFor(Auth::user()) !== [];
    }

    /** May the current actor open this lesson in the panel they are in? */
    public static function allowsStaff(BookLesson $lesson): bool
    {
        $panel = self::currentPanel();

        if ($panel === null || ! in_array($panel, self::panelsFor(Auth::user()), true)) {
            return false;
        }

        return (int) $lesson->tenant_id === (int) (tenant()?->id);
    }

    /** May the current actor preview this lesson through the student reader? */
    public static function allowsPreview(BookLesson $lesson): bool
    {
        return self::allowsStaff($lesson) || (
            self::staffCanAuthor() && (int) $lesson->tenant_id === (int) (tenant()?->id)
        );
    }

    /** Abort 404 unless the actor may open $lesson in this panel. */
    public static function authorizeStaff(BookLesson $lesson): void
    {
        abort_unless(self::allowsStaff($lesson), 404);
    }
}
