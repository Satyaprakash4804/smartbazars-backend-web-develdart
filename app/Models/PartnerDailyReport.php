<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerDailyReport extends Model
{
    protected $fillable = ['network_member_id', 'report_date', 'content', 'customers_met', 'kits_sold'];
    protected $casts = ['report_date' => 'date'];

    public function member() { return $this->belongsTo(NetworkMember::class, 'network_member_id'); }
}
