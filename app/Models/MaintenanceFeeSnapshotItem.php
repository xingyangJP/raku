<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceFeeSnapshotItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_fee_snapshot_id',
        'customer_name',
        'maintenance_fee',
        'status',
        'support_type',
    ];

    protected $casts = [
        'maintenance_fee' => 'float',
    ];

    public function snapshot()
    {
        return $this->belongsTo(MaintenanceFeeSnapshot::class, 'maintenance_fee_snapshot_id');
    }
}
