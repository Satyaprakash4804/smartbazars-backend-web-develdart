<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkWalletLedger extends Model
{
    protected $table = 'network_wallet_ledger';
    protected $fillable = ['network_member_id', 'type', 'wallet', 'amount', 'balance_after', 'reference_type', 'reference_id', 'description'];
    protected $casts = ['amount' => 'decimal:2', 'balance_after' => 'decimal:2'];
}
