<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['user_id','account_number','type','solde','status'];




    public function users(){
        return $this->belongsTo(User::class);
    }
}
