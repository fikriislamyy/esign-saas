<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardInfo extends Model
{
    protected $table = 'cards_info';

    protected $fillable = [
        'organization_id',
        'card_brand',
        'card_last_four',
        'card_exp_month',
        'card_exp_year',
        'stripe_payment_method_id',
        'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
