<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Register a user and issue a personal access token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $user = User::query()->create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => Hash::make($datos['password']),
        ]);

        return response()->json(
            $this->respuestaConToken($user, $datos['device_name'] ?? 'react-web'),
            Response::HTTP_CREATED,
        );
    }

    /**
     * Authenticate credentials and issue a personal access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $user = User::query()->where('email', $datos['email'])->first();

        if ($user === null || ! Hash::check($datos['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas no son correctas.'],
            ]);
        }

        return response()->json(
            $this->respuestaConToken($user, $datos['device_name'] ?? 'react-web'),
        );
    }

    /**
     * Revoke only the personal access token used for this request.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    /**
     * Build the token response returned by register and login.
     *
     * @return array<string, mixed>
     */
    private function respuestaConToken(User $user, string $nombreDispositivo): array
    {
        return [
            'token_type' => 'Bearer',
            'token' => $user->createToken($nombreDispositivo)->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];
    }
}
