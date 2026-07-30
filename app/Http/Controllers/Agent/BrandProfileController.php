<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\UpdateBrandProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandProfileController extends Controller
{
    /**
     * Show the team's brand profile.
     */
    public function edit(Request $request): Response
    {
        $brand = $request->user()->currentTeam->brandProfile;

        return Inertia::render('brand/edit', [
            'brand' => [
                'voice' => $brand?->voice,
                'audience' => $brand?->audience,
                'topics' => $brand->topics ?? [],
                'bannedTerms' => $brand->banned_terms ?? [],
                'examples' => $brand->examples ?? [],
            ],
        ]);
    }

    /**
     * Create or update the team's brand profile.
     */
    public function update(UpdateBrandProfileRequest $request): RedirectResponse
    {
        $request->user()->currentTeam->brandProfile()->updateOrCreate([], [
            'voice' => $request->validated('voice'),
            'audience' => $request->validated('audience'),
            'topics' => $request->validated('topics', []),
            'banned_terms' => $request->validated('banned_terms', []),
            'examples' => $request->validated('examples', []),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand voice saved.')]);

        return back();
    }
}
