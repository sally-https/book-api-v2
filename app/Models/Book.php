<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $fillable = ['title', 'author', 'barcode', 'quantity'];

    // Add any additional book-specific logic

    // In the Book model
public function borrowedBooks()
{
    return $this->hasMany(BorrowedBook::class);
}
}
