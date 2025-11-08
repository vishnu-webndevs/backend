<?php

namespace App\Policies;

use App\Models\Analytics;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AnalyticsPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view analytics (filtered by ownership in controller)
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Analytics $analytics): bool
    {
        // Admins can view all analytics
        if ($user->isAdmin()) {
            return true;
        }
        
        // Agencies can view analytics from campaigns they manage
        if ($user->isAgency()) {
            // For now, agencies can view all analytics
            // You might want to implement a more specific relationship
            return true;
        }
        
        // Users can only view analytics from their own campaigns
        if ($analytics->campaign) {
            return $user->id === $analytics->campaign->user_id;
        }
        
        // If analytics is tied to a video, check video's campaign ownership
        if ($analytics->video && $analytics->video->campaign) {
            return $user->id === $analytics->video->campaign->user_id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Analytics are typically created automatically, but allow authenticated users
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Analytics $analytics): bool
    {
        // Generally, analytics should not be updated after creation
        // Only admins can update analytics for data correction purposes
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Analytics $analytics): bool
    {
        // Only admins can delete analytics data
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Analytics $analytics): bool
    {
        return $this->delete($user, $analytics);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Analytics $analytics): bool
    {
        // Only admins can permanently delete analytics data
        return $user->isAdmin();
    }
}
