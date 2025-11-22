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
    ];

    protected $casts = [
        'month' => 'date',
        'total_fee' => 'float',
        'total_gross' => 'float',
    ];

    public function items()
    {
        return $this->hasMany(MaintenanceFeeSnapshotItem::class, 'maintenance_fee_snapshot_id');
    }
}
