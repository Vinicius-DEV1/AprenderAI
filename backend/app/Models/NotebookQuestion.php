<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class NotebookQuestion extends Pivot
{
    protected $table = 'notebook_questions';
}
