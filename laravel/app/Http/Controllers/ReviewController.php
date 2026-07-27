<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(): View
    {
        $reviews = Review::query()->where('is_published', true)->latest()->paginate(6);

        return view('reviews.index', [
            'reviews' => $reviews,
            'averageRating' => round((float) Review::where('is_published', true)->avg('rating'), 1),
            'reviewCount' => Review::where('is_published', true)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:100'],
            'comment' => ['required', 'string', 'min:10', 'max:1500'],
            'website' => ['nullable', 'max:0'],
        ]);

        Review::create([
            'user_id' => $request->user()?->id,
            'name' => $request->user()->name,
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'comment' => $validated['comment'],
            'is_published' => true,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('review_success', __('Vielen Dank. Deine Bewertung wurde veröffentlicht.'));
    }
}
