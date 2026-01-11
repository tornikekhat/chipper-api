<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\CreateFavoriteRequest;
use App\Http\Resources\PostResource;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * @group Favorites
 *
 * API endpoints for managing favorites
 */
class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $favorites = $request->user()->favorites()->with(['favoritable' => function ($morphTo) {
            $morphTo->morphWith([
                Post::class => ['user'],
                User::class => [],
            ]);
        }])->get();
        
        $posts = $favorites
            ->filter(fn($favorite) => $favorite->favoritable instanceof Post)
            ->map(fn($favorite) => $favorite->favoritable)
            ->unique('id')
            ->values();
        
        $users = $favorites
            ->filter(fn($favorite) => $favorite->favoritable instanceof User)
            ->map(fn($favorite) => $favorite->favoritable)
            ->unique('id')
            ->values();
        
        return response()->json([
            'data' => [
                'posts' => PostResource::collection($posts),
                'users' => $users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                    ];
                }),
            ],
        ]);
    }

    public function store(CreateFavoriteRequest $request, Post $post = null, User $user = null)
    {
        $favoritable = $post ?? $user;
        
        if (!$favoritable) {
            abort(404);
        }

        if ($favoritable instanceof User && $favoritable->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'favoritable' => ['You cannot favorite yourself.'],
            ]);
        }

        $favoritableType = get_class($favoritable);
        
        $existingFavorite = $request->user()->favorites()
            ->where('favoritable_id', $favoritable->id)
            ->where('favoritable_type', $favoritableType)
            ->first();

        if ($existingFavorite) {
            return response()->noContent(Response::HTTP_CREATED);
        }

        $favoriteData = [
            'favoritable_id' => $favoritable->id,
            'favoritable_type' => $favoritableType,
        ];
        
        if ($favoritable instanceof Post) {
            $favoriteData['post_id'] = $favoritable->id;
        } else {
            $favoriteData['post_id'] = 0;
        }
        
        $request->user()->favorites()->create($favoriteData);

        return response()->noContent(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Post $post = null, User $user = null)
    {
        $favoritable = $post ?? $user;
        
        if (!$favoritable) {
            abort(404);
        }

        $favoritableType = get_class($favoritable);
        
        $favorite = $request->user()->favorites()
            ->where('favoritable_id', $favoritable->id)
            ->where('favoritable_type', $favoritableType)
            ->firstOrFail();

        $favorite->delete();

        return response()->noContent();
    }
}
