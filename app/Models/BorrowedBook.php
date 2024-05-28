<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BorrowedBook extends Model
{
    protected $fillable = ['student_id', 'book_id', 'borrowed_date', 'return_date', 'return_code', 'return_status'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }


    // Add any additional borrowed book-specific logic
}
