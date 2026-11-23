<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaInventario extends Model
{
    protected $table = 'categorias_inventario';

    protected $guarded = ['id'];

    public $timestamps = false;
}
