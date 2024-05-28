<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\StudentController;

Route::group([
    'middleware' => 'api'
], function () {
    // Admin Routes
    Route::post('/admin-login', [AuthController::class, 'adminLogin']);

    // Student Routes
    Route::post('/student-login', [AuthController::class, 'studentLogin']);
    Route::post('/student-register', [AuthController::class, 'studentRegister']);
});

Route::group([
    'middleware' => ['api', 'auth:admin'],
], function () {
    // Admin Routes
    Route::post('/admin-logout', [AuthController::class, 'adminLogout']);
    Route::get('/admin-dashboard', [AdminController::class, 'adminDashboard']);
    Route::patch('/admin-update', [AdminController::class, 'adminUpdate']);

    Route::post('/admin-add-book', [AdminController::class, 'adminAddBook']);
    Route::patch('/admin-edit-book/{id}', [AdminController::class, 'adminEditBook']);
    Route::get('/admin-view-books', [AdminController::class, 'adminViewBooks']);
    Route::delete('/books/{id}', [AdminController::class, 'adminDeleteBook']);

    Route::post('/admin-add-student', [AdminController::class, 'adminAddStudent']);
    Route::delete('/admin-delete-student/{id}', [AdminController::class, 'adminDeleteStudent']);
    Route::patch('/admin-edit-student/{id}', [AdminController::class, 'adminEditStudent']);
    Route::get('/admin-view-students', [AdminController::class, 'adminViewStudents']);
    Route::get('/admin-view-borrowed-books', [AdminController::class, 'adminViewBorrowedBooks']);
    Route::get('/admin-view-returned-books', [AdminController::class, 'adminViewReturnedBooks']);
});

Route::group([
    'middleware' => ['api', 'auth:student'],
], function () {
    // Student Routes
    Route::post('/student-logout', [AuthController::class, 'studentLogout']);
    Route::get('/student-dashboard', [StudentController::class, 'studentDashboard']);
    Route::patch('/student-update', [StudentController::class, 'studentUpdate']);

    Route::post('/student-borrow-book', [StudentController::class, 'studentBorrowBook']);
    Route::get('/student-view-borrowed-books', [StudentController::class, 'studentViewBorrowedBooks']);

    Route::post('/student-return-book', [StudentController::class, 'studentReturnBook']);
    Route::post('/student-scan-barcode', [StudentController::class, 'studentScanBarcode']);
    Route::get('/view-book-library', [StudentController::class, 'viewBookLibrary']);
});
