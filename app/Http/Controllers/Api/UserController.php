<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        
        $validated = $request->validate([
            'role' => ['nullable', Rule::in(['Admin', 'Agency', 'Brand'])],
            'is_active' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);
        
        $query = User::with(['campaigns']);
        $currentUser = Auth::user();
        
        // Role-based filtering
        if ($currentUser->isAdmin()) {
            // Admins can see all users
        } elseif ($currentUser->isAgency()) {
            // Agencies can only see Brand users and themselves
            $query->where(function ($q) use ($currentUser) {
                $q->where('role', 'Brand')
                  ->orWhere('id', $currentUser->id);
            });
        } else {
            // Brands can only see themselves
            $query->where('id', $currentUser->id);
        }
        
        // Filter by role
        if (isset($validated['role'])) {
            $query->where('role', $validated['role']);
        }
        
        // Filter by active status
        if (isset($validated['is_active'])) {
            $query->where('is_active', $validated['is_active']);
        }
        
        // Search by name, email, or username
        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }
        
        $users = $query->orderBy('created_at', 'desc')
                      ->paginate($validated['per_page'] ?? 15);
        
        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $this->authorize('create', User::class);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'message' => 'You are not authorized to create users. Only Admins and Agencies can create new users.',
                'error' => 'insufficient_privileges',
                'required_role' => ['Admin', 'Agency']
            ], 403);
        }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::in(['admin', 'agency', 'brand', 'Admin', 'Agency', 'Brand'])],
            'is_active' => 'boolean'
        ]);
        
        // Normalize role to proper case
        $validated['role'] = ucfirst(strtolower($validated['role']));
        
        // Check if current user can create this role
        $currentUser = Auth::user();
        if (!$currentUser->isAdmin() && !app(UserPolicy::class)->createRole($currentUser, $validated['role'])) {
            return response()->json([
                'message' => 'You are not authorized to create users with this role.'
            ], 403);
        }
        
        $validated['password'] = Hash::make($validated['password']);
        $validated['email_verified_at'] = now(); // Auto-verify admin-created users
        
        $user = User::create($validated);
        $user->load(['campaigns']);
        
        return response()->json([
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);
        
        $user->load(['campaigns.videos', 'campaigns.analytics']);
        
        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => ['sometimes', 'required', Rule::in(['admin', 'agency', 'brand', 'Admin', 'Agency', 'Brand'])],
            'is_active' => 'boolean'
        ]);
        
        // Normalize role to proper case if provided
        if (isset($validated['role'])) {
            $validated['role'] = ucfirst(strtolower($validated['role']));
        }
        
        // Only admins can change roles
        if (isset($validated['role']) && !Auth::user()->isAdmin()) {
            unset($validated['role']);
        }
        
        // Hash password if provided
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }
        
        $user->update($validated);
        $user->load(['campaigns']);
        
        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);
        
        // Prevent self-deletion
        if ($user->id === Auth::id()) {
            return response()->json([
                'message' => 'You cannot delete your own account'
            ], 422);
        }
        
        // Soft delete to preserve data integrity
        $user->update(['is_active' => false]);
        
        return response()->json([
            'message' => 'User deactivated successfully'
        ]);
    }

    /**
     * Get current authenticated user profile.
     */
    public function profile(): JsonResponse
    {
        $user = Auth::user();
        $user->load(['campaigns.videos', 'campaigns.analytics']);
        
        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * Update current authenticated user profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'current_password' => 'required_with:password|string'
        ]);
        
        // Verify current password if changing password
        if (isset($validated['password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'message' => 'Current password is incorrect'
                ], 422);
            }
            $validated['password'] = Hash::make($validated['password']);
        }
        
        // Remove current_password from update data
        unset($validated['current_password']);
        
        $user->update($validated);
        $user->load(['campaigns']);
        
        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $user
        ]);
    }
}
