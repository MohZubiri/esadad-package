<?php

namespace ESadad\PaymentGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ESadad\PaymentGateway\Facades\ESadad;
use Illuminate\Support\Facades\Log;
use Exception;

class ESadadPaymentController extends Controller
{
    /**
     * Show payment form
     *
     * @return \Illuminate\View\View
     */
    public function showPaymentForm()
    {
        return view('esadad::payment-form');
    }
    
    /**
     * Process payment
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processPayment(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'customer_id' => 'required|string',
            'customer_password' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'invoice_id' => 'required|string',
        ]);
        
        try {
            // Step 1: Payment Initiation (Trigger OTP) - Authentication is handled automatically
            $initiationResult = ESadad::initiatePayment($validated['customer_id'], $validated['customer_password']);
            
            if ($initiationResult['errorCode'] !== '000') {
                return redirect()->back()->with('error', $initiationResult['errorDescription']);
            }
            
            // Store payment data in session for the next step
            session([
                'esadad_payment' => [
                    'customer_id' => $validated['customer_id'],
                    'amount' => $validated['amount'],
                    'invoice_id' => $validated['invoice_id'],
                ]
            ]);
            
            // Redirect to OTP verification page
            return redirect()->route('esadad.otp')->with('success', 'تم إرسال رمز التحقق إلى هاتفك المحمول');
            
        } catch (Exception $e) {
            Log::error('e-SADAD payment error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'فشلت عملية الدفع: ' . $e->getMessage());
        }
    }
    
    /**
     * Show OTP verification form
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showOtpForm()
    {
        if (!session('esadad_token') || !session('esadad_payment')) {
            return redirect()->route('esadad.form')
                ->with('error', 'انتهت جلسة الدفع. يرجى المحاولة مرة أخرى.');
        }
        
        return view('esadad::otp-form');
    }
    
    /**
     * Verify OTP and complete payment
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function verifyOtp(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'otp' => 'required|string',
        ]);
        
        // Check if session data exists
        if (!session('esadad_payment')) {
            return redirect()->route('esadad.form')
                ->with('error', 'انتهت جلسة الدفع. يرجى المحاولة مرة أخرى.');
        }
        
        $paymentData = session('esadad_payment');
        
        try {
            // Step 3: Payment Request (with OTP)
            $paymentResult = ESadad::requestPayment(
                $paymentData['customer_id'],
                $validated['otp'],
                $paymentData['invoice_id'],
                $paymentData['amount']
            );
            
            if ($paymentResult['errorCode'] !== '000') {
                return redirect()->back()->with('error', $paymentResult['errorDescription']);
            }
            
            // Extract transaction details
            $transactionDetails = [
                'bank_trx_id' => $paymentResult['bankTrxId'],
                'sep_trx_id' => $paymentResult['sepTrxId'],
                'invoice_id' => $paymentResult['invoiceId'],
                'stmt_date' => $paymentResult['stmtDate'],
            ];
            
            // Step 4: Payment Confirmation
            $confirmationResult = ESadad::confirmPayment(
                $paymentData['customer_id'],
                $transactionDetails,
                $paymentData['amount']
            );
            
            if ($confirmationResult['errorCode'] !== '000') {
                return redirect()->back()->with('error', $confirmationResult['errorDescription']);
            }
            
            // Store transaction details in session for success page
            session(['esadad_transaction' => $transactionDetails]);
            
            // Clear payment session data
            session()->forget('esadad_payment');
            
            // Redirect to success page
            return redirect()->route('esadad.success')->with('success', 'تمت عملية الدفع بنجاح');
            
        } catch (Exception $e) {
            Log::error('e-SADAD payment verification error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'فشل التحقق من رمز OTP: ' . $e->getMessage());
        }
    }
    
    /**
     * Show payment success page
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showSuccessPage()
    {
        if (!session('esadad_transaction')) {
            return redirect()->route('esadad.form')
                ->with('error', 'لا توجد معلومات عن عملية دفع ناجحة');
        }
        
        $transactionDetails = session('esadad_transaction');
        
        // Find transaction in database
        $transaction = ESadad::findTransactionByInvoiceId($transactionDetails['invoice_id']);
        
        return view('esadad::success', [
            'transaction' => $transaction,
            'transactionDetails' => $transactionDetails,
        ]);
    }
    
    /**
     * List transactions
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function listTransactions(Request $request)
    {
        $filters = [];
        
        // Apply filters from request
        if ($request->has('status')) {
            $filters['status'] = $request->status;
        }
        
        if ($request->has('customer_id')) {
            $filters['customer_id'] = $request->customer_id;
        }
        
        if ($request->has('invoice_id')) {
            $filters['invoice_id'] = $request->invoice_id;
        }
        
        if ($request->has('from_date')) {
            $filters['from_date'] = $request->from_date;
        }
        
        if ($request->has('to_date')) {
            $filters['to_date'] = $request->to_date;
        }
        
        // Get transactions
        $transactions = ESadad::getTransactions($filters);
        
        return view('esadad::transactions', [
            'transactions' => $transactions,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Show transaction details
     *
     * @param  int  $id
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showTransaction($id)
    {
        $transaction = ESadad::findTransaction($id);
        
        if (!$transaction) {
            return redirect()->route('esadad.transactions')
                ->with('error', 'لم يتم العثور على المعاملة');
        }
        
        return view('esadad::transaction-details', [
            'transaction' => $transaction,
        ]);
    }
}
