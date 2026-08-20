<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $customerIds = Customer::where('user_id', $request->user()->id)->pluck('id');

        return Inertia::render('Member/Courses/Index', [
            'enrollments' => Enrollment::whereIn('customer_id', $customerIds)
                ->with('course.product')
                ->latest()
                ->get()
                ->map(fn (Enrollment $e) => [
                    'id' => $e->id,
                    'title' => $e->course->product->name,
                    'thumbnail_url' => $e->course->product->thumbnailUrl(),
                    'progress' => $e->progress_percent,
                    'lesson_count' => $e->course->lessons()->count(),
                    'expires_at' => $e->expires_at?->toDateString(),
                    'has_access' => $e->hasAccess(),
                    'completed_at' => $e->completed_at?->toDateString(),
                    'certificate_code' => $e->certificate_code,
                ]),
        ]);
    }

    public function show(Request $request, Enrollment $enrollment): Response
    {
        $this->authorizeEnrollment($request, $enrollment);

        abort_unless($enrollment->hasAccess(), 403, 'Akses kelas kamu sudah berakhir.');

        $enrollment->load(['course.product', 'course.sections.lessons', 'progress']);
        $done = $enrollment->progress->where('completed', true)->pluck('lesson_id')->all();

        return Inertia::render('Member/Courses/Show', [
            'enrollment' => [
                'id' => $enrollment->id,
                'title' => $enrollment->course->product->name,
                'progress' => $enrollment->progress_percent,
                'completed_at' => $enrollment->completed_at?->toDateString(),
                'certificate_code' => $enrollment->certificate_code,
            ],
            'sections' => $enrollment->course->sections->map(fn ($section) => [
                'title' => $section->title,
                'description' => $section->description,
                'lessons' => $section->lessons->map(fn (Lesson $l) => [
                    'id' => $l->id,
                    'title' => $l->title,
                    'type' => $l->type,
                    'duration_minutes' => $l->duration_minutes,
                    // Drip-locked lessons return no body at all, so the content
                    // is not merely hidden in the UI.
                    'unlocked' => $l->isUnlockedFor($enrollment),
                    'unlocks_at' => $l->unlocksAt($enrollment)?->toDateString(),
                    'completed' => in_array($l->id, $done, true),
                    'body' => $l->isUnlockedFor($enrollment) ? $l->body : null,
                    'video_url' => $l->isUnlockedFor($enrollment) ? $l->video_url : null,
                ]),
            ]),
        ]);
    }

    public function complete(Request $request, Enrollment $enrollment, Lesson $lesson)
    {
        $this->authorizeEnrollment($request, $enrollment);

        abort_unless($lesson->isUnlockedFor($enrollment), 403);

        $enrollment->progress()->updateOrCreate(
            ['lesson_id' => $lesson->id],
            ['completed' => true, 'completed_at' => now()],
        );

        $enrollment->recalculateProgress();

        return back();
    }

    private function authorizeEnrollment(Request $request, Enrollment $enrollment): void
    {
        $owned = $enrollment->user_id === $request->user()->id
            || ($enrollment->customer && $enrollment->customer->user_id === $request->user()->id);

        abort_unless($owned, 403);
    }
}
