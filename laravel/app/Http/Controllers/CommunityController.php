<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function index(Request $request): View
    {
        $topic = in_array($request->string('topic')->value(), ['general', 'markets', 'stocks', 'strategies'], true)
            ? $request->string('topic')->value()
            : null;

        $posts = CommunityPost::query()
            ->with('user:id,community_alias')
            ->where('is_published', true)
            ->when($topic, fn ($query) => $query->where('topic', $topic))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('community.index', [
            'posts' => $posts,
            'activeTopic' => $topic,
            'postCount' => CommunityPost::query()->where('is_published', true)->count(),
            'memberCount' => CommunityPost::query()->where('is_published', true)->distinct()->count('user_id'),
        ]);
    }

    public function updateAlias(Request $request): RedirectResponse
    {
        $request->merge(['community_alias' => Str::lower(trim((string) $request->input('community_alias')))]);
        $validated = $request->validate([
            'community_alias' => [
                'required', 'string', 'min:3', 'max:24', 'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('users', 'community_alias')->ignore($request->user()->id),
            ],
        ], [
            'community_alias.regex' => __('Der Alias darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.'),
        ]);

        $request->user()->update($validated);

        return back()->with('community_status', __('Dein Community-Alias wurde gespeichert.'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()->community_alias) {
            return back()->withErrors(['community_alias' => __('Lege zuerst einen Community-Alias fest.')]);
        }

        $validated = $request->validate([
            'topic' => ['required', Rule::in(['general', 'markets', 'stocks', 'strategies'])],
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $request->user()->communityPosts()->create($validated);

        return redirect()->route('community.index')->with('community_status', __('Beitrag veröffentlicht.'));
    }

    public function destroy(Request $request, CommunityPost $post): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id || $request->user()->is_admin, 403);
        $post->delete();

        return back()->with('community_status', __('Beitrag gelöscht.'));
    }
}
