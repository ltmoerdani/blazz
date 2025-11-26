<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ContactContactGroup extends Pivot
{
    protected $table = 'contact_contact_group';

    protected $guarded = [];

    public $timestamps = true; // Ensure timestamps are used if present in the table
}
