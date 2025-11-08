<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\PublicVideoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});

// Public routes (no authentication required)
Route::post('/analytics/track', [AnalyticsController::class, 'store']);

// Public video routes
Route::prefix('public')->group(function () {
    Route::get('/videos/popular', [PublicVideoController::class, 'popular']);
    Route::get('/videos/{slug}', [PublicVideoController::class, 'show']);
    Route::get('/videos/{slug}/metadata', [PublicVideoController::class, 'metadata']);
    Route::get('/sitemap.xml', [PublicVideoController::class, 'sitemap']);
    
    // Brand-specific routes
    Route::get('/all-campaigns', [PublicVideoController::class, 'allCampaigns']);
    Route::get('/{brand_username}/{campaign_slug}', [PublicVideoController::class, 'campaignRoundRobin']);
    Route::get('/{brand_username}/{campaign_slug}/{video_slug}', [PublicVideoController::class, 'brandVideo']);
});

// Authentication required routes
Route::middleware('auth:sanctum')->group(function () {
    // User management routes
    Route::middleware(['auth:sanctum'])->group(function () {
        // Profile routes (accessible to all authenticated users)
        Route::get('/users/profile', [UserController::class, 'profile']);
        Route::put('/users/profile', [UserController::class, 'updateProfile']);
        
        // Basic user listing (for dropdowns, etc.)
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        
        // User creation - controlled by policy, not middleware
        Route::post('/users', [UserController::class, 'store']);
    });
    
    // Admin-only user management routes
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        
        // Backup management routes
        Route::prefix('admin/backups')->name('admin.backup.')->group(function () {
            Route::get('/', [BackupController::class, 'index'])->name('index');
            Route::post('/', [BackupController::class, 'create'])->name('create');
            Route::get('/{filename}/download', [BackupController::class, 'download'])->name('download');
            Route::delete('/{filename}', [BackupController::class, 'destroy'])->name('destroy');
            Route::post('/{filename}/restore', [BackupController::class, 'restore'])->name('restore');
            Route::post('/{filename}/restore-code', [BackupController::class, 'restoreCode'])->name('restore.code');
            Route::post('/{filename}/restore-database', [BackupController::class, 'restoreDatabase'])->name('restore.database');
            // Async restore trigger
            Route::post('/{filename}/restore-async', [BackupController::class, 'startAsync'])->name('restore.async');
        });
        // Async job status (outside backups prefix so URL matches provided status_url)
        Route::get('/admin/restore-jobs/{jobId}', [BackupController::class, 'jobStatus'])->name('admin.restore.job-status');
    });
    
    // Campaign routes
    Route::apiResource('campaigns', CampaignController::class);
    
    // Video routes
    Route::apiResource('videos', VideoController::class);
    Route::post('/videos/upload', [\App\Http\Controllers\Api\VideoUploadController::class, 'upload']);
    
    // Analytics routes
    Route::get('/analytics', [AnalyticsController::class, 'index']);
    Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);
    Route::get('/analytics/campaigns/{campaign}/summary', [AnalyticsController::class, 'campaignSummary']);
    Route::get('/analytics/videos/{video}/summary', [AnalyticsController::class, 'videoSummary']);
});

// Get authenticated user info
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
// Temporary login route for testing (REMOVE IN PRODUCTION)
Route::post('/auth/test-login', function(Request $request) {
    $user = \App\Models\User::where('email', $request->email)->first();
    if ($user) {
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ]);
    }
    return response()->json(['message' => 'User not found'], 404);
});
