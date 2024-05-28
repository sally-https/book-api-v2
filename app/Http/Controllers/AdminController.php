<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Book;
use App\Models\Student;
use Illuminate\Support\Str;
use App\Models\BorrowedBook;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Illuminate\Support\Facades\Validator;
class AdminController extends Controller
{

    /**
 * Admin dashboard
 *
 * @return \Illuminate\Http\JsonResponse
 */
public function adminDashboard()
{
    $admin = auth('admin')->user();

    $totalStudents = Student::count();
    $totalBooks = Book::count();
    $totalBorrowedBooks = BorrowedBook::count();
    $totalReturnedBooks = BorrowedBook::where('return_status', 'returned')->count();

    return response()->json([
        'success' => true,
        'totalStudents' => $totalStudents,
        'totalBooks' => $totalBooks,
        'totalBorrowedBooks' => $totalBorrowedBooks,
        'totalReturnedBooks' => $totalReturnedBooks,
    ], 200);
}

/**
 * Delete a book
 *
 * @param \Illuminate\Http\Request $request
 * @param int $id
 * @return \Illuminate\Http\JsonResponse
 */
public function adminDeleteBook(Request $request, $id)
{
    $admin = auth('admin')->user();

    $validator = Validator::make(['id' => $id], [
        'id' => 'required|integer|exists:books,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }

    $book = Book::findOrFail($id);
    $book->delete();

    return response()->json([
        'success' => true,
        'message' => 'Book deleted successfully',
    ], 200);
}

/**
 * Add a new book
 *
 * @param \Illuminate\Http\Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function adminAddBook(Request $request)
{
    $admin = auth('admin')->user();

    $validator = Validator::make($request->all(), [
        'title' => 'required|string|max:255',
        'author' => 'required|string|max:255',
        'quantity' => 'required|integer|min:1',
        'book_image_url' => 'nullable|string|url',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }

    $validatedData = $validator->validated();

    $bookId = mt_rand(1000000, 9999999); // Generate a random 7-digit integer

    $book = new Book();
    $book->id = $bookId;
    $book->title = $validatedData['title'];
    $book->author = $validatedData['author'];
    $book->quantity = $validatedData['quantity'];
    $book->book_image_url = $validatedData['book_image_url'] ?? null;

    // Generate barcode with the unique ID
    $generator = new BarcodeGeneratorPNG();
    $barcode = $generator->getBarcode($bookId, $generator::TYPE_CODE_128);
    $book->barcode = $barcode;

    $book->save();

    return response()->json([
        'success' => true,
        'message' => 'Book added successfully',
    ], 201);
}

   /**
 * Edit a book
 *
 * @param \Illuminate\Http\Request $request
 * @param int $id
 * @return \Illuminate\Http\JsonResponse
 */
public function adminEditBook(Request $request, $id)
{
    $admin = auth('admin')->user();

    $validator = Validator::make($request->all(), [
        'title' => 'sometimes|string|max:255',
        'author' => 'sometimes|string|max:255',
        'quantity' => 'sometimes|integer|min:1',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }

    $validatedData = $validator->validated();

    $book = Book::findOrFail($id);

    if ($request->has('title')) {
        $book->title = $validatedData['title'];
    }

    if ($request->has('author')) {
        $book->author = $validatedData['author'];
    }

    if ($request->has('quantity')) {
        $book->quantity = $validatedData['quantity'];
    }

    $book->save();

    return response()->json([
        'success' => true,
        'message' => 'Book updated successfully',
        'book' => $book,
    ], 200);
}
    /**
     * View all books
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminViewBooks()
    {
        $admin = auth('admin')->user();

        $books = Book::get()->map(function ($book) {
            $barcodeBase64 = null;
            if ($book->barcode) {
                $barcodeBase64 = base64_encode($book->barcode);
            }

            return [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'quantity' => $book->quantity,
                'book_image_url' => $book->book_image_url,
                'barcode' => $barcodeBase64,
                'created_at' => $book->created_at,
                'updated_at' => $book->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'books' => $books,
        ], 200);
    }

    /**
     * Add a new student
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminAddStudent(Request $request)
    {
        $admin = auth('admin')->user();

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'student_id' => 'required|string|max:255|unique:students',
            'password' => 'required|string|min:6',
            'email' => 'required|string|email|unique:students',
        ]);

        $student = new Student();
        $student->name = $validatedData['name'];
        $student->student_id = $validatedData['student_id'];
        $student->password = bcrypt($validatedData['password']);
        $student->email = $validatedData['email'];
        $student->save();

        return response()->json([
            'success' => true,
            'message' => 'Student added successfully',
            'student' => $student,
        ], 201);
    }

    /**
     * Edit a student
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminEditStudent(Request $request, $id)
    {
        $admin = auth('admin')->user();

        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'student_id' => 'sometimes|string|max:255|unique:students,student_id,' . $id,
            'password' => 'sometimes|string|min:6',
            'email' => 'sometimes|string|email|unique:students,email,' . $id,
        ]);

        $student = Student::findOrFail($id);

        if ($request->has('name')) {
            $student->name = $validatedData['name'];
        }

        if ($request->has('student_id')) {
            $student->student_id = $validatedData['student_id'];
        }

        if ($request->has('password')) {
            $student->password = bcrypt($validatedData['password']);
        }

        if ($request->has('email')) {
            $student->email = $validatedData['email'];
        }

        $student->save();

        return response()->json([
            'success' => true,
            'message' => 'Student updated successfully',
            'student' => $student,
        ], 200);
    }

    /**
 * View all students
 *
 * @return \Illuminate\Http\JsonResponse
 */
public function adminViewStudents()
{
    $admin = auth('admin')->user();

    $students = Student::withCount(['borrowedBooks' => function ($query) {
        $query->where('return_status', 'borrowed');
    }])->get();

    return response()->json([
        'success' => true,
        'students' => $students,
    ], 200);
}
   /**
 * View all borrowed books
 *
 * @return \Illuminate\Http\JsonResponse
 */
public function adminViewBorrowedBooks()
{
    $admin = auth('admin')->user();

    $borrowedBooks = BorrowedBook::with('student', 'book')
        ->where('return_status', 'borrowed')
        ->get()
        ->map(function ($borrowedBook) {
            return [
                'id' => $borrowedBook->id,
                'borrower_name' => $borrowedBook->student->name,
                'borrowed_book_id' => $borrowedBook->id,
                'student_id' => $borrowedBook->student->student_id,
                'book_name' => $borrowedBook->book->title,
                'borrowed_date' => $borrowedBook->borrowed_date,
                'return_date' => $borrowedBook->return_date,
                'return_code' => $borrowedBook->return_code,
                'return_status' => $borrowedBook->return_status,
            ];
        });

    return response()->json([
        'success' => true,
        'borrowedBooks' => $borrowedBooks,
    ], 200);
}

public function adminViewReturnedBooks()
{
    $admin = auth('admin')->user();

    $returnedBooks = BorrowedBook::with('student', 'book')
        ->where('return_status', 'returned')
        ->get()
        ->map(function ($borrowedBook) {
            return [
                'borrower_name' => $borrowedBook->student->name,
                'borrowed_book_id' => $borrowedBook->id,
                'student_id' => $borrowedBook->student->student_id,
                'book_name' => $borrowedBook->book->title,
                'borrowed_date' => $borrowedBook->borrowed_date,
                'return_date' => $borrowedBook->return_date,
                'return_code' => $borrowedBook->return_code,
                'return_status' => $borrowedBook->return_status,
            ];
        });

    return response()->json([
        'success' => true,
        'returnedBooks' => $returnedBooks,
    ], 200);
}
}
