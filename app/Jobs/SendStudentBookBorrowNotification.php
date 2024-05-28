<?php

namespace App\Jobs;

use App\Models\BorrowedBook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendStudentBookBorrowNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $borrowedBook;

    /**
     * Create a new job instance.
     *
     * @param BorrowedBook $borrowedBook
     * @return void
     */
    public function __construct(BorrowedBook $borrowedBook)
    {
        $this->borrowedBook = $borrowedBook;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
{
    $borrowedBook = $this->borrowedBook->load('book', 'student');

    $student = $borrowedBook->student;
    $book = $borrowedBook->book;

    $message = "Dear {$student->name}, you have successfully borrowed the book '{$book->title}' by {$book->author}. The book must be returned by {$borrowedBook->return_date}. Kindly contact Libarian for Return Code when returning the book.";

    $this->sendNotification($student->phone, $message);
}

    /**
     * Send the notification to the student's phone number.
     *
     * @param string $phoneNumber
     * @param string $message
     * @return void
     */
    private function sendNotification($phoneNumber, $message)
    {
        try {
            // Set the API endpoint URL
            $url = 'https://smsclone.com/api/sms/sendsms';

            // Set the API parameters
            $params = [
                'username' => 'remindme',
                'password' => 'mydzaf-dakbyg-0foxsY',
                'sender' => 'REMINDME',
                'recipient' => $phoneNumber,
                'message' => $message,
            ];

            // Make the API request
            $response = Http::get($url, $params);

            // Check the response status
            if ($response->successful()) {
                // Handle successful response
                $responseData = $response->json();
                Log::info('SMS notification sent successfully.', [
                    'phone_number' => $phoneNumber,
                    'message' => $message,
                    'response' => $responseData,
                ]);
                // Process the response data as needed
            } else {
                // Handle error response
                $errorMessage = $response->body();
                Log::error('Failed to send SMS notification.', [
                    'phone_number' => $phoneNumber,
                    'message' => $message,
                    'error' => $errorMessage,
                ]);
                // Log the error or take appropriate action
            }
        } catch (\Exception $e) {
            // Handle and log any exceptions
            Log::error('Exception occurred while sending SMS notification.', [
                'phone_number' => $phoneNumber,
                'message' => $message,
                'exception' => $e->getMessage(),
            ]);
            // You can also choose to re-throw the exception if needed
            // throw $e;
        }
    }
}
