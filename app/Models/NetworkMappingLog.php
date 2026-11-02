<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkMappingLog extends Model
{
    protected $fillable = [
        'admin_id', 'entity_type', 'entity_id', 'entity_label',
        'from_id', 'from_label', 'to_id', 'to_label', 'affected',
    ];
}
