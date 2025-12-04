<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateUserRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseController
{
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request)
    {
        try {
            $data = $request->validated();

            // Hash the password
            $data['password'] = Hash::make($data['password']);

            // Set default role if not provided
            if (!isset($data['role_id'])) {
                $defaultRole = Role::where('is_default', true)->first();
                $data['role_id'] = $defaultRole ? $defaultRole->id : null;
            }

            // Create user
            $user = User::create($data);

            // Assign permissions based on role
            if ($user->role) {
                $user->permissions()->sync($user->role->permissions->pluck('id'));
            }

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->sendResponse([
                'user' => $user->load('role', 'permissions'),
                'token' => $token,
                'token_type' => 'Bearer'
            ], 'User registered successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Registration failed', $e->getMessage(), 500);
        }
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request)
    {
        try {
            $credentials = $request->validated();



            if (!Auth::attempt($credentials)) {
                return $this->sendError('Invalid credentials', [], 401);
            }

            $user = Auth::user();

            // Check if user is active
            if (!$user->is_active) {
                Auth::logout();
                return $this->sendError('Account is deactivated', [], 403);
            }

            // Update last login
            $user->update(['last_login' => now()]);

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->sendResponse([
                'user' => $user->load('role', 'permissions'),
                'token' => $token,
                'token_type' => 'Bearer'
            ], 'Login successful');
        } catch (\Exception $e) {
            return $this->sendError('Login failed', $e->getMessage(), 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return $this->sendResponse([], 'Logged out successfully');
        } catch (\Exception $e) {
            return $this->sendError('Logout failed', $e->getMessage(), 500);
        }
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        try {
            $user = $request->user()->load('role', 'permissions');

            return $this->sendResponse($user, 'User retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve user', $e->getMessage(), 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(UpdateUserRequest $request)
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            // Remove password from data if present
            unset($data['password']);

            $user->update($data);

            return $this->sendResponse(
                $user->fresh()->load('role', 'permissions'),
                'Profile updated successfully'
            );
        } catch (\Exception $e) {
            return $this->sendError('Profile update failed', $e->getMessage(), 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            // Verify current password
            if (!Hash::check($data['current_password'], $user->password)) {
                return $this->sendError('Current password is incorrect', [], 422);
            }

            // Update password
            $user->update(['password' => Hash::make($data['new_password'])]);

            // Revoke all tokens
            $user->tokens()->delete();

            return $this->sendResponse([], 'Password changed successfully');
        } catch (\Exception $e) {
            return $this->sendError('Password change failed', $e->getMessage(), 500);
        }
    }

    /**
     * Send password reset link
     */
    public function sendResetLink(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);

            $status = Password::sendResetLink($request->only('email'));

            if ($status === Password::RESET_LINK_SENT) {
                return $this->sendResponse([], 'Password reset link sent successfully');
            } else {
                return $this->sendError('Failed to send reset link', [], 500);
            }
        } catch (\Exception $e) {
            return $this->sendError('Failed to send reset link', $e->getMessage(), 500);
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    // Revoke all tokens
                    $user->tokens()->delete();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return $this->sendResponse([], 'Password reset successfully');
            } else {
                return $this->sendError('Failed to reset password', [], 500);
            }
        } catch (\Exception $e) {
            return $this->sendError('Password reset failed', $e->getMessage(), 500);
        }
    }

    /**
     * Create new operator (Admin/Manager only)
     */
    public function createOperator(Request $request)
    {
        try {
            // Check if user has permission
            if (!$request->user()->hasPermission('users.create')) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $request->validate([
                'username' => 'required|string|max:255|unique:users,username',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|max:20',
                'password' => 'required|string|min:8|confirmed',
                'address' => 'required|string|max:500',
                'gender' => 'required|in:male,female,other',
                'date_of_birth' => 'required|date|before:today',
                'role_id' => 'required|exists:roles,id'
            ]);

            $data = $request->all();
            $data['password'] = Hash::make($data['password']);
            $data['is_active'] = true;
            $data['email_verified_at'] = now();

            $user = User::create($data);

            // Assign permissions based on role
            if ($user->role) {
                $user->permissions()->sync($user->role->permissions->pluck('id'));
            }

            return $this->sendResponse(
                $user->load('role', 'permissions'),
                'Operator created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to create operator', $e->getMessage(), 500);
        }
    }

    /**
     * Update operator
     */
    public function updateOperator(Request $request, $id)
    {
        try {
            // Check if user has permission
            if (!$request->user()->hasPermission('users.edit')) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $user = User::findOrFail($id);

            $request->validate([
                'username' => 'sometimes|required|string|max:255|unique:users,username,' . $id,
                'email' => 'sometimes|required|email|unique:users,email,' . $id,
                'phone' => 'sometimes|required|string|max:20',
                'address' => 'sometimes|required|string|max:500',
                'gender' => 'sometimes|required|in:male,female,other',
                'date_of_birth' => 'sometimes|required|date|before:today',
                'role_id' => 'sometimes|required|exists:roles,id'
            ]);

            $data = $request->all();
            unset($data['password']); // Password should be changed separately

            $user->update($data);

            // Update permissions if role changed
            if (isset($data['role_id']) && $user->role) {
                $user->permissions()->sync($user->role->permissions->pluck('id'));
            }

            return $this->sendResponse(
                $user->fresh()->load('role', 'permissions'),
                'Operator updated successfully'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to update operator', $e->getMessage(), 500);
        }
    }

    /**
     * Activate operator
     */
    public function activateOperator(Request $request, $id)
    {
        try {
            // Check if user has permission
            if (!$request->user()->hasPermission('users.edit')) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $user = User::findOrFail($id);
            $user->update(['is_active' => true]);

            return $this->sendResponse(
                $user->fresh()->load('role', 'permissions'),
                'Operator activated successfully'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to activate operator', $e->getMessage(), 500);
        }
    }

    /**
     * Deactivate operator
     */
    public function deactivateOperator(Request $request, $id)
    {
        try {
            // Check if user has permission
            if (!$request->user()->hasPermission('users.edit')) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $user = User::findOrFail($id);
            $user->update(['is_active' => false]);

            // Revoke all tokens
            $user->tokens()->delete();

            return $this->sendResponse(
                $user->fresh()->load('role', 'permissions'),
                'Operator deactivated successfully'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to deactivate operator', $e->getMessage(), 500);
        }
    }

    /**
     * Reset operator password
     */
    public function resetOperatorPassword(Request $request, $id)
    {
        try {
            // Check if user has permission
            if (!$request->user()->hasPermission('users.edit')) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $request->validate([
                'new_password' => 'required|string|min:8|confirmed'
            ]);

            $user = User::findOrFail($id);
            $user->update(['password' => Hash::make($request->new_password)]);

            // Revoke all tokens
            $user->tokens()->delete();

            return $this->sendResponse([], 'Operator password reset successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to reset operator password', $e->getMessage(), 500);
        }
    }

    /**
     * Get all operators
     */
    public function getOperators(Request $request)
    {
        try {
            // Check if user has permission
            if (!$request->user()->hasPermission('users.view')) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $roleId = $request->get('role_id');
            $isActive = $request->get('is_active');

            $query = User::with('role', 'permissions');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($roleId) {
                $query->where('role_id', $roleId);
            }

            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }

            $users = $query->paginate($perPage);

            return $this->sendResponse($users, 'Operators retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve operators', $e->getMessage(), 500);
        }
    }

    /**
     * Get operator by ID
     */
    public function getOperator(Request $request, $id)
    {
        try {
            // Check if user has permission
            if (!$request->user()->hasPermission('users.view')) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $user = User::with('role', 'permissions')->findOrFail($id);

            return $this->sendResponse($user, 'Operator retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve operator', $e->getMessage(), 500);
        }
    }

    /**
     * Delete operator
     */
    public function deleteOperator(Request $request, $id)
    {
        try {
            // Check if user has permission
            if (!$request->user()->hasPermission('users.delete')) {
                return $this->sendError('Unauthorized', [], 403);
            }

            $user = User::findOrFail($id);

            // Prevent self-deletion
            if ($user->id === $request->user()->id) {
                return $this->sendError('Cannot delete your own account', [], 422);
            }

            $user->delete();

            return $this->sendResponse([], 'Operator deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete operator', $e->getMessage(), 500);
        }
    }

    /**
     * Get available roles
     */
    public function getRoles(Request $request)
    {
        try {
            $roles = Role::all();

            return $this->sendResponse($roles, 'Roles retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve roles', $e->getMessage(), 500);
        }
    }

    /**
     * Refresh token
     */
    public function refreshToken(Request $request)
    {
        try {
            $user = $request->user();

            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            // Generate new token
            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->sendResponse([
                'user' => $user->load('role', 'permissions'),
                'token' => $token,
                'token_type' => 'Bearer'
            ], 'Token refreshed successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to refresh token', $e->getMessage(), 500);
        }
    }
}
