<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Lessons\LessonAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the lesson reader, the ONE screen shared by two audiences.
 *
 * A student reaches it through `auth:student` + the `student_lessons` flag; a
 * consultant or teacher reaches it as a preview through their own staff
 * session and the `lesson_library` flag. Spelling either flag alone would
 * 404 the other audience, so the rule is "the flag that belongs to whichever
 * audience you are":
 *
 *   - a student session  => student_lessons
 *   - a staff session    => lesson_library
 *   - neither            => 404 (the controller then applies the row rule:
 *                           published+grade for a student, own-tenant for
 *                           staff, which is what allows a DRAFT preview)
 */
class EnsureLessonReader
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->allowed($request), 404);

        return $next($request);
    }

    private function allowed(Request $request): bool
    {
        if ($request->user('student') !== null) {
            return (bool) site('features.student_lessons', false);
        }

        // The operations console links here to check what OUR extraction
        // produced. A platform admin has no tenant role (`panelsFor()` returns
        // [] for them by design: they own no panel), so without this branch the
        // console could import a book but never look at the result.
        $user = $request->user();

        if ($user instanceof User && $user->canRunOps()) {
            return true;
        }

        return LessonAccess::staffCanAuthor();
    }
}
