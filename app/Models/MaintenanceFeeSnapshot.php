<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceFeeSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'total_fee',
        'total_gross',
        'source',
        'last_synced_at',
    ];

    protected $casts = [
        'month' => 'date',
        'total_fee' => 'float',
        'total_gross' => 'float',
        'last_synced_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(MaintenanceFeeSnapshotItem::class, 'maintenance_fee_snapshot_id');
    }
}
