<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Trait FiltersAdmins
 * 
 * Provides a global-like scope to exclude records associated with admin users from statistics.
 */
trait FiltersAdmins
{
    /**
     * Scope to exclude events/records from admin users.
     * 
     * If the record has no user_id (anonymous), it is included.
     * If the record has a user_id, it is only included if the user is not an admin.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithoutAdmins(Builder $query): Builder
    {
        // If the current model is User, filter directly by role
        if ($this instanceof \App\Models\User) {
            return $query->where('role', '!=', 'admin');
        }

        // Otherwise, filter records based on the role of the associated user (via user_id)
        return $query->where(function ($q) {
            $q->whereNull($this->getTable() . '.user_id')
              ->orWhereNotExists(function ($sub) {
                  $sub->select(DB::raw(1))
                      ->from('users')
                      ->whereColumn('users.id', $this->getTable() . '.user_id')
                      ->where('users.role', 'admin');
              });
        });
    }
}
