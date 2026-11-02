@extends('layouts.admin.app', ['page' => 'sb_program', 'sub_page' => 'sb_settings'])
@section('title', 'Program Settings')
@section('header_data')
    @php $page_title = 'SmartBazars Program Settings'; $page_pretitle = 'Commissions, coins & user card'; @endphp
@endsection
@section('admin-content')
    @include('smartbazars._errors')
    <form method="POST" action="{{ route('admin.smartbazars.settings.save') }}">
        @csrf
        <div class="row row-cards">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">BSO commission on vendor sales</h3></div>
                    <div class="card-body">
                        <p class="text-secondary small">Credited when an item from the vendor is delivered; reversed if returned.</p>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label">Direct agent (%)</label><input type="number" step="0.01" name="agent_direct_percent" value="{{ $s['agent_direct_percent'] }}" class="form-control"></div>
                            <div class="col-6"><label class="form-label">Upline agent (%)</label><input type="number" step="0.01" name="agent_upline_percent" value="{{ $s['agent_upline_percent'] }}" class="form-control"></div>
                            <div class="col-12"><label class="form-label">Calculated on</label>
                                <select name="agent_commission_base" class="form-select">
                                    <option value="item_subtotal" @selected($s['agent_commission_base']==='item_subtotal')>Full item sale value</option>
                                    <option value="admin_commission" @selected($s['agent_commission_base']==='admin_commission')>SmartBazars' commission on the item (profit)</option>
                                </select></div>
                        </div>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header"><h3 class="card-title">Welcome kit commission (₹ per kit)</h3></div>
                    <div class="card-body row g-2">
                        <div class="col-6"><label class="form-label">BJO (referral code)</label><input type="number" name="kit_worker_amount" value="{{ $s['kit_worker_amount'] }}" class="form-control"></div>
                        <div class="col-6"><label class="form-label">BJO's vendor</label><input type="number" name="kit_vendor_amount" value="{{ $s['kit_vendor_amount'] }}" class="form-control"></div>
                        <div class="col-6"><label class="form-label">Vendor's agent</label><input type="number" name="kit_agent_amount" value="{{ $s['kit_agent_amount'] }}" class="form-control"></div>
                        <div class="col-6"><label class="form-label">BSO's upline</label><input type="number" name="kit_agent_upline_amount" value="{{ $s['kit_agent_upline_amount'] }}" class="form-control"></div>
                        <div class="col-12"><label class="form-check form-switch mt-2">
                            <input type="checkbox" class="form-check-input" name="kit_requires_referral_code" value="1" @checked($s['kit_requires_referral_code'])>
                            <span class="form-check-label">Referral code is compulsory to buy the kit</span></label></div>
                    </div>
                </div>
            </div>
            <div class="col-12 order-last">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Repeat-purchase commission (customer trail)</h3></div>
                    <div class="card-body row g-3">
                        <div class="col-12 d-flex flex-wrap gap-4">
                            <label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="repurchase_enabled" value="1" @checked($s['repurchase_enabled'])><span class="form-check-label">Pay commission on every delivered order of a referred customer</span></label>
                            <label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="repurchase_requires_welcome_kit" value="1" @checked($s['repurchase_requires_welcome_kit'])><span class="form-check-label">Only for customers who bought the Welcome Kit</span></label>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">BJO who referred the customer</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" name="repurchase_worker_value" value="{{ $s['repurchase_worker_value'] }}" class="form-control">
                                <select name="repurchase_worker_type" class="form-select" style="max-width:150px">
                                    <option value="percent" @selected($s['repurchase_worker_type']==='percent')>% of item</option>
                                    <option value="fixed" @selected($s['repurchase_worker_type']==='fixed')>₹ per order</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Vendor (of that worker / QR)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" name="repurchase_vendor_value" value="{{ $s['repurchase_vendor_value'] }}" class="form-control">
                                <select name="repurchase_vendor_type" class="form-select" style="max-width:150px">
                                    <option value="percent" @selected($s['repurchase_vendor_type']==='percent')>% of item</option>
                                    <option value="fixed" @selected($s['repurchase_vendor_type']==='fixed')>₹ per order</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Vendor's agent</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" name="repurchase_agent_value" value="{{ $s['repurchase_agent_value'] }}" class="form-control">
                                <select name="repurchase_agent_type" class="form-select" style="max-width:150px">
                                    <option value="percent" @selected($s['repurchase_agent_type']==='percent')>% of item</option>
                                    <option value="fixed" @selected($s['repurchase_agent_type']==='fixed')>₹ per order</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">BSO's upline</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" name="repurchase_agent_upline_value" value="{{ $s['repurchase_agent_upline_value'] }}" class="form-control">
                                <select name="repurchase_agent_upline_type" class="form-select" style="max-width:150px">
                                    <option value="percent" @selected($s['repurchase_agent_upline_type']==='percent')>% of item</option>
                                    <option value="fixed" @selected($s['repurchase_agent_upline_type']==='fixed')>₹ per order</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 small text-secondary">"% of item" is paid on each delivered item's value; "₹ per order" is paid once per order. Reversed automatically if an item is returned or cancelled.</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Cashback coins</h3></div>
                    <div class="card-body row g-2">
                        <div class="col-12"><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="coins_enabled" value="1" @checked($s['coins_enabled'])><span class="form-check-label">Coins enabled</span></label></div>
                        <div class="col-6"><label class="form-label">Value of 1 coin (₹)</label><input type="number" step="0.01" name="coin_value" value="{{ $s['coin_value'] }}" class="form-control"></div>
                        <div class="col-6"><label class="form-label">Max % of order payable by coins</label><input type="number" name="max_coin_redeem_percent" value="{{ $s['max_coin_redeem_percent'] }}" class="form-control"></div>
                        <div class="col-12 small text-secondary">Set coins per product in <a href="{{ route('admin.smartbazars.products') }}">All Products & Cashback</a>.</div>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header"><h3 class="card-title">Joining fee (Razorpay)</h3></div>
                    <div class="card-body row g-2">
                        <div class="col-12"><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="joining_fee_enabled" value="1" @checked($s['joining_fee_enabled'])><span class="form-check-label">Collect joining fee from new BSOs, BJOs &amp; Vendors (OFF = nobody pays)</span></label></div>
                        <div class="col-4"><label class="form-label">BSO fee (₹)</label><input type="number" step="0.01" min="0" name="joining_fee_agent" value="{{ $s['joining_fee_agent'] }}" class="form-control"></div>
                        <div class="col-4"><label class="form-label">BJO fee (₹)</label><input type="number" step="0.01" min="0" name="joining_fee_worker" value="{{ $s['joining_fee_worker'] }}" class="form-control"></div>
                        <div class="col-4"><label class="form-label">Vendor fee (₹)</label><input type="number" step="0.01" min="0" name="joining_fee_vendor" value="{{ $s['joining_fee_vendor'] }}" class="form-control"></div>
                        <div class="col-12"><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="joining_fee_reuse_on_reapply" value="1" @checked($s['joining_fee_reuse_on_reapply'])><span class="form-check-label">Re-use the fee already paid when a rejected applicant applies again</span></label></div>
                        <div class="col-12"><label class="form-label">Vendor "Apply now" link</label><input type="url" name="vendor_apply_url" value="{{ $s['vendor_apply_url'] }}" class="form-control"></div>
                        <div class="col-12 small text-secondary">Fees go fully to SmartBazars — they are never split as commission.</div>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header"><h3 class="card-title">User card & payouts</h3></div>
                    <div class="card-body row g-2">
                        <div class="col-6"><label class="form-label">Card number prefix</label><input name="card_prefix" value="{{ $s['card_prefix'] }}" class="form-control" maxlength="4"></div>
                        <div class="col-6"><label class="form-label">Card validity (years)</label><input type="number" name="card_validity_years" value="{{ $s['card_validity_years'] }}" class="form-control"></div>
                        <input type="hidden" name="card_requires_welcome_kit" value="1">
                        <div class="col-6"><label class="form-label">Min. partner withdrawal (₹)</label><input type="number" name="min_withdrawal_amount" value="{{ $s['min_withdrawal_amount'] }}" class="form-control"></div>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header"><h3 class="card-title">Cash on Delivery advance</h3></div>
                    <div class="card-body row g-2">
                        <div class="col-12"><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="cod_advance_enabled" value="1" @checked($s['cod_advance_enabled'])><span class="form-check-label">Customers must pay an online advance for COD orders</span></label></div>
                        <div class="col-6"><label class="form-label">Advance (% of order)</label><div class="input-group"><input type="number" step="0.01" min="0" max="100" name="cod_advance_percent" value="{{ $s['cod_advance_percent'] }}" class="form-control"><span class="input-group-text">%</span></div></div>
                        <div class="col-12 small text-secondary">The advance is paid from the customer's SmartBazars wallet (recharged online via Razorpay / Stripe etc.). The rest is collected on delivery. Cancelled items refund their share of the advance to the wallet.</div>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header"><h3 class="card-title">Public links</h3></div>
                    <div class="card-body">
                        <label class="form-label">Customer website URL (used on QR landing page)</label>
                        <input name="public_site_url" value="{{ $s['public_site_url'] }}" class="form-control" placeholder="https://smartbazars.in">
                    </div>
                </div>
                <button class="btn btn-primary w-100 mt-3">Save settings</button>
            </div>
        </div>
    </form>
    @include('admin.smartbazars._app_download')
@endsection
