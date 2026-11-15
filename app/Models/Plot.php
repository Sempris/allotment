<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plot extends Model
{
    use HasFactory;

    protected $fillable = ['number', 'tenant_name', 'tenant_email'];

    /** @return HasMany<Meter, $this> */
    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    /** @return HasMany<InvoiceDispatch, $this> */
    public function invoiceDispatches(): HasMany
    {
        return $this->hasMany(InvoiceDispatch::class);
    }
}
