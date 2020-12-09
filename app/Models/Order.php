<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function specifications() {
        return $this->hasMany(OrderSpecification::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attachments() {
        return $this->hasMany(OrderAttachment::class);
    }

    public function details() {
        return $this->hasMany(OrderDetail::class);
    }
}
