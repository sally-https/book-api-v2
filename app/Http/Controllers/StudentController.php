<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Book;
use App\Jobs\SendStudentBookBorrowNotification;
use Picqer\Barcode\BarcodeGeneratorPNG;
use App\Models\BorrowedBook;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Str;

class StudentController extends Controller
{

    /**
 * Student dashboard
 *
 * @return \Illuminate\Http\JsonResponse
 */
public function studentDashboard()
{
    $student = auth('student')->user();

    $totalBorrowedBooks = BorrowedBook::where('student_id', $student->id)->count();
    $pendingReturnBooks = BorrowedBook::where('student_id', $student->id)
        ->where('return_status', 'borrowed')
        ->count();
    $returnedBooks = BorrowedBook::where('student_id', $student->id)
        ->where('return_status', 'returned')
        ->count();

    $incomingBookToReturn = BorrowedBook::where('student_id', $student->id)
        ->where('return_status', 'borrowed')
        ->with('book')
        ->orderBy('return_date', 'asc')
        ->first();

    $incomingBookName = null;
    $returnDeadline = null;

    if ($incomingBookToReturn) {
        $incomingBookName = $incomingBookToReturn->book->title;
        $returnDeadline = $incomingBookToReturn->return_date;
    }

    return response()->json([
        'success' => true,
        'totalBorrowedBooks' => $totalBorrowedBooks,
        'pendingReturnBooks' => $pendingReturnBooks,
        'returnedBooks' => $returnedBooks,
        'incomingBookName' => $incomingBookName,
        'returnDeadline' => $returnDeadline,
    ], 200);
}
    /**
     * Borrow a book
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function studentBorrowBook(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'book_id' => 'required|exists:books,id',
            'days' => 'required|integer|min:1|max:7',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $book = Book::findOrFail($request->input('book_id'));
        $student = auth('student')->user();
        $days = $request->input('days');

        // Check if the student has already borrowed 3 books that haven't been returned
        $borrowedBooksCount = BorrowedBook::where('student_id', $student->id)
            ->where('return_status', 'borrowed')
            ->count();

        if ($borrowedBooksCount >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'You have already borrowed the maximum number of books.',
            ], 400);
        }

        // Check if the student has borrowed 3 copies of the same book that haven't been returned
        $borrowedBookCount = BorrowedBook::where('student_id', $student->id)
            ->where('book_id', $book->id)
            ->where('return_status', 'borrowed')
            ->count();

        if ($borrowedBookCount >= 3) {
            return response()->json([
                'success' => false,
                'message' => 'You have already borrowed the maximum number of copies for this book.',
            ], 400);
        }

       // Check if the student has any overdue books that haven't been returned
        $overdueBooks = BorrowedBook::where('student_id', $student->id)
            ->where('return_date', '<', Carbon::now())
            ->where('return_status', 'borrowed')
            ->exists();

        if ($overdueBooks) {
            return response()->json([
            'success' => false,
            'message' => 'You have overdue books. Please return them before borrowing a new book.',
        ], 400);
        }

        // Check if the book is available for borrowing
        if ($book->quantity < 1) {
            return response()->json([
                'success' => false,
                'message' => 'The book is not available for borrowing.',
            ], 400);
        }

        // Create a new borrowed book record
        $borrowedBook = new BorrowedBook();
        $borrowedBook->student_id = $student->id;
        $borrowedBook->book_id = $book->id;
        $borrowedBook->borrowed_date = Carbon::now();
        $borrowedBook->return_date = Carbon::now()->addDays(intval($days));
        $borrowedBook->return_code = Str::random(8); // Generate a random return code
        $borrowedBook->save();

        // Decrement the book quantity
        $book->quantity--;
        $book->save();

        SendStudentBookBorrowNotification::dispatch($borrowedBook);


        return response()->json([
            'success' => true,
            'message' => 'Book borrowed successfully',
            'borrowedBook' => $borrowedBook,
        ], 200);
    }

   /**
 * View borrowed books for the authenticated student
 *
 * @return \Illuminate\Http\JsonResponse
 */
public function studentViewBorrowedBooks()
{
    $student = auth('student')->user();

    $borrowedBooks = BorrowedBook::where('student_id', $student->id)
        ->with('book')
        ->get()
        ->map(function ($borrowedBook) {
            return [
                'borrowed_book_id' => $borrowedBook->id,
                'book_title' => $borrowedBook->book->title,
                'borrowed_date' => $borrowedBook->borrowed_date,
                'return_date' => $borrowedBook->return_date,
                'return_status' => $borrowedBook->return_status,
            ];
        });

    return response()->json([
        'success' => true,
        'borrowedBooks' => $borrowedBooks,
    ], 200);
}

     /**
     * Return a borrowed book
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function studentReturnBook(Request $request)
    {
        $student = auth('student')->user();

        $validatedData = $request->validate([
            'borrowed_book_id' => 'required|exists:borrowed_books,id',
            'return_code' => 'required|string',
        ]);

        $borrowedBook = BorrowedBook::where('id', $validatedData['borrowed_book_id'])
            ->where('student_id', $student->id)
            ->where('return_code', $validatedData['return_code'])
            ->first();

        if (!$borrowedBook) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid borrowed book or return code.',
            ], 400);
        }

        $book = $borrowedBook->book;
        $book->quantity++;
        $book->save();



        $borrowedBook->return_status = 'returned';
        $borrowedBook->save();

        return response()->json([
            'success' => true,
            'message' => 'Book returned successfully',
        ], 200);
    }

/**
 * Scan a book barcode and retrieve the book by ID
 *
 * @param  string  $barcode
 * @return \Illuminate\Http\JsonResponse
 */
public function studentScanBarcode($barcode)
{
    $student = auth('student')->user();

    // Find the book based on the scanned barcode
    $book = Book::where('barcode', $barcode)->first();

    if (!$book) {
        return response()->json([
            'success' => false,
            'message' => 'Book not found.',
        ], 404);
    }

    return response()->json([
        'success' => true,
        'book' => $book,
    ], 200);
}
   /**
 * View the book library
 *
 * @return \Illuminate\Http\JsonResponse
 */
/**
 * View the book library with borrowing count
 *
 * @return \Illuminate\Http\JsonResponse
 */
public function viewBookLibrary()
{
    $student = auth('student')->user();

    $books = Book::withCount('borrowedBooks')->get()->map(function ($book) {
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
            'borrowed_count' => $book->borrowed_books_count,
        ];
    });

    return response()->json([
        'success' => true,
        'books' => $books,
    ], 200);
}
}
