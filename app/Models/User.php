<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'approval_limit' => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function hasRole(string ...$roles): bool
    {
        if ($this->role === 'SUPER_ADMIN') {
            return true;
        }

        return in_array($this->role, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'SUPER_ADMIN';
    }

    public function isBranchUser(): bool
    {
        if ($this->isSuperAdmin()) {
            return false;
        }

        return in_array($this->role, ['REQUESTER_CABANG', 'ORDER_APPROVER', 'RECEIVING_OFFICER'], true)
            || ($this->organization && in_array($this->organization->type, ['MAIN_BRANCH', 'SUB_BRANCH'], true));
    }

    public function isWarehouseUser(): bool
    {
        return in_array($this->role, ['WAREHOUSE_OFFICER', 'DISTRIBUTION_OFFICER'], true)
            || ($this->organization && $this->organization->type === 'WAREHOUSE');
    }

    public function isProcurementUser(): bool
    {
        return in_array($this->role, ['PROCUREMENT_OFFICER', 'PROCUREMENT_APPROVER'], true);
    }

    public function isFinanceUser(): bool
    {
        return in_array($this->role, ['FINANCE_OFFICER', 'FINANCE_APPROVER', 'BUDGET_OFFICER'], true);
    }

    public function isAuditor(): bool
    {
        return $this->role === 'AUDITOR';
    }

    public function isManagement(): bool
    {
        return $this->role === 'MANAGEMENT';
    }

    public function canAccessModule(string $module): bool
    {
        if ($this->role === 'SUPER_ADMIN') {
            return true;
        }

        return match ($module) {
            'orders' => in_array($this->role, ['REQUESTER_CABANG', 'ORDER_APPROVER', 'RECEIVING_OFFICER', 'AUDITOR', 'MANAGEMENT'], true),
            'order_approvals' => in_array($this->role, ['ORDER_APPROVER', 'AUDITOR', 'MANAGEMENT'], true),
            'warehouse' => in_array($this->role, ['WAREHOUSE_OFFICER', 'DISTRIBUTION_OFFICER', 'INVENTORY_OFFICER', 'AUDITOR', 'MANAGEMENT'], true),
            'warehouse_ops' => in_array($this->role, ['WAREHOUSE_OFFICER', 'AUDITOR', 'MANAGEMENT'], true),
            'distribution' => in_array($this->role, ['DISTRIBUTION_OFFICER', 'WAREHOUSE_OFFICER', 'AUDITOR', 'MANAGEMENT'], true),
            'receiving' => in_array($this->role, ['RECEIVING_OFFICER', 'REQUESTER_CABANG', 'ORDER_APPROVER', 'WAREHOUSE_OFFICER', 'AUDITOR', 'MANAGEMENT'], true),
            'inventory' => in_array($this->role, ['INVENTORY_OFFICER', 'WAREHOUSE_OFFICER', 'REQUESTER_CABANG', 'ORDER_APPROVER', 'AUDITOR', 'MANAGEMENT'], true),
            'inventory_ops' => in_array($this->role, ['INVENTORY_OFFICER', 'WAREHOUSE_OFFICER', 'AUDITOR'], true),
            'inventory_advanced' => in_array($this->role, ['INVENTORY_OFFICER', 'SWITCHING_APPROVER', 'MANAGEMENT', 'AUDITOR'], true),
            'procurement' => in_array($this->role, ['PROCUREMENT_OFFICER', 'PROCUREMENT_APPROVER', 'AUDITOR', 'MANAGEMENT'], true),
            'procurement_maker' => in_array($this->role, ['PROCUREMENT_OFFICER'], true),
            'finance' => in_array($this->role, ['FINANCE_OFFICER', 'FINANCE_APPROVER', 'BUDGET_OFFICER', 'AUDITOR', 'MANAGEMENT'], true),
            'master_data' => in_array($this->role, ['MASTER_MAKER', 'MASTER_APPROVER', 'USER_ADMIN', 'AUDITOR'], true),
            'master_items' => in_array($this->role, ['MASTER_MAKER', 'MASTER_APPROVER', 'INVENTORY_OFFICER', 'AUDITOR'], true),
            'master_budgets' => in_array($this->role, ['BUDGET_OFFICER', 'FINANCE_OFFICER', 'FINANCE_APPROVER', 'AUDITOR'], true),
            'master_vendors' => in_array($this->role, ['MASTER_MAKER', 'MASTER_APPROVER', 'PROCUREMENT_OFFICER', 'AUDITOR'], true),
            'master_users' => in_array($this->role, ['USER_ADMIN', 'IT_OPS', 'AUDITOR'], true),
            'audit' => in_array($this->role, ['AUDITOR', 'IT_OPS', 'USER_ADMIN'], true),
            'reports' => in_array($this->role, ['MANAGEMENT', 'AUDITOR', 'FINANCE_OFFICER', 'FINANCE_APPROVER', 'PROCUREMENT_OFFICER', 'INVENTORY_OFFICER'], true),
            default => false,
        };
    }

    public function getRoleDisplayNameAttribute(): string
    {
        $roleMap = [
            'SUPER_ADMIN' => 'Super Administrator',
            'USER_ADMIN' => 'User Administrator',
            'MASTER_MAKER' => 'Master Data Maker',
            'MASTER_APPROVER' => 'Master Data Approver',
            'BUDGET_OFFICER' => 'Budget Officer',
            'PROCUREMENT_OFFICER' => 'Procurement Officer',
            'PROCUREMENT_APPROVER' => 'Procurement Approver',
            'INVENTORY_OFFICER' => 'Inventory Officer',
            'WAREHOUSE_OFFICER' => 'Warehouse Officer',
            'REQUESTER_CABANG' => 'Requester Cabang/Capem',
            'ORDER_APPROVER' => 'Order Approver',
            'SWITCHING_APPROVER' => 'Switching Stock Approver',
            'DISTRIBUTION_OFFICER' => 'Distribution Officer',
            'RECEIVING_OFFICER' => 'Receiving Officer',
            'FINANCE_OFFICER' => 'Finance Officer',
            'FINANCE_APPROVER' => 'Finance Approver',
            'AUDITOR' => 'Internal Auditor',
            'MANAGEMENT' => 'Executive Management',
            'IT_OPS' => 'IT Operations',
        ];

        return $roleMap[$this->role] ?? $this->role;
    }
}
