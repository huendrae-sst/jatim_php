<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targetOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'target_organization_id');
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            // 1. Directly targeted to this user
            $q->where('user_id', $user->id);

            // 2. Targeted to role (and optionally organization)
            $q->orWhere(function ($sub) use ($user) {
                $sub->where(function ($u) use ($user) {
                    $u->whereNull('user_id')->orWhere('user_id', $user->id);
                });

                if (! $user->isSuperAdmin()) {
                    $sub->where('target_role', $user->role)
                        ->where(function ($orgSub) use ($user) {
                            $orgSub->whereNull('target_organization_id');
                            if ($user->organization_id) {
                                $orgSub->orWhere('target_organization_id', $user->organization_id);
                            }
                        });
                } else {
                    $sub->whereNotNull('target_role');
                }
            });

            // 3. Global broadcast announcements (no user_id and no target_role)
            $q->orWhere(function ($sub) use ($user) {
                $sub->whereNull('user_id')
                    ->whereNull('target_role')
                    ->where(function ($orgSub) use ($user) {
                        $orgSub->whereNull('target_organization_id');
                        if ($user->organization_id) {
                            $orgSub->orWhere('target_organization_id', $user->organization_id);
                        }
                    });
            });
        });
    }

    public function scopeUnreadForUser($query, User $user)
    {
        return $query->forUser($user)->where('is_read', false);
    }
}
