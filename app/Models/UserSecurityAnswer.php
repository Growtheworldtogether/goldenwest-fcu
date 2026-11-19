<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSecurityAnswer extends Model
{
    protected $fillable = ['user_id', 'question_key', 'answer_hash'];
    protected $hidden = ['answer_hash'];
}
