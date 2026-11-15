<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $year
 * @property string|null $recipient_email
 * @property int $total_cents
 */
class InvoiceDispatch extends Model
{
    protected $fillable = ['plot_id', 'year', 'recipient_email', 'total_cents', 'sent_by'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Plot, $this> */
    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function wasHandedOver(): bool
    {
        return $this->recipient_email === null;
    }
}
