<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceDocument extends Model
{
    use HasFactory;

    function doc() {
        return $this->belongsTo(Document::class, 'document_id', 'id');
    }

    function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
