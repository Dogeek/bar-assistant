<?php

declare(strict_types=1);

namespace Kami\Cocktail\Http\Controllers;

use Illuminate\Http\Request;
use Kami\Cocktail\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Kami\Cocktail\Search\SearchActionsAdapter;
use Kami\Cocktail\Http\Requests\RegisterRequest;
use Kami\Cocktail\Http\Resources\ProfileResource;

class AuthController extends Controller
{
    public function authenticate(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $token = $request->user()->createToken('web_app_login');

            return response()->json(['token' => $token->plainTextToken]);
        }

        abort(404, 'User not found. Check your username and password and try again.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['data' => ['success' => true]]);
    }

    public function register(SearchActionsAdapter $search, RegisterRequest $req): JsonResponse
    {
        if (config('bar-assistant.allow_registration') == false) {
            abort(404, 'Registrations are closed.');
        }

        $user = new User();
        $user->name = $req->post('name');
        $user->password = Hash::make($req->post('password'));
        $user->email = $req->post('email');
        $user->email_verified_at = now();
        $user->search_api_key = $search->getActions()->getPublicApiKey();
        $user->save();

        return (new ProfileResource(
            $user->load('favorites', 'shelfIngredients', 'shoppingLists'),
            app(SearchActionsAdapter::class),
        ))->response()->setStatusCode(200);
    }
}
