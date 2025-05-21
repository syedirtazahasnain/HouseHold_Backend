<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Exports\OrdersExport;
use App\Imports\ProductImport;
use App\Imports\EmployeesImport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class SummaryController extends Controller
{
    /**
     * This function tell the
     * user orders summary on dashboard
     */
    public function userOrderSummary()
    {
        // Order::where('user_id')
    }

    /**
     * This function is used to
     * update the employee as per
     * excel sheet
     */
    public function importEmployees(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls,csv'
            ]);
            $file = $request->file('excel_file');
            Excel::queueImport(new EmployeesImport, $file);
            return success_res(200, 'File uploaded successfully and is being processed', ['status' => 'queued']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return error_res(403, 'Validation Error', $e->errors());
        } catch (\Exception $e) {
            return error_res(500, 'An unexpected error occurred', $e->getMessage());
        }
    }


    /**
     * This function is used to
     * update the product as per
     * excel sheet
     */
    public function importProduct(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls,csv'
            ]);
            $file = $request->file('excel_file');
            Excel::queueImport(new ProductImport, $file);
            return success_res(200, 'File uploaded successfully and is being processed', ['status' => 'queued']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return error_res(403, 'Validation Error', $e->errors());
        } catch (\Exception $e) {
            return error_res(500, 'An unexpected error occurred', $e->getMessage());
        }
    }

    /**
     * This function is used to export the excel
     * file for order report
     */
    public function orderReport(Request $request)
    {
        try {
            $request->merge([
                'start_date' => $request->start_date ?: \Carbon\Carbon::now()->startOfMonth()->format('d-m-Y'),
                'end_date' => $request->end_date ?: \Carbon\Carbon::now()->endOfMonth()->format('d-m-Y'),
            ]);

            $request->validate([
                'start_date' => 'required|date_format:d-m-Y',
                'end_date' => 'required|date_format:d-m-Y|after_or_equal:start_date',
            ]);
            $start_date = \Carbon\Carbon::createFromFormat('d-m-Y', $request->start_date)->startOfDay();
            $end_date = \Carbon\Carbon::createFromFormat('d-m-Y', $request->end_date)->endOfDay();
            $filename = 'orders_report_' . now()->format('Ymd_His') . '.xlsx';
            Excel::store(new OrdersExport($start_date, $end_date), $filename, 'public');
            $url = Storage::disk('public')->url($filename);

            return success_res(200, 'Orders report generated successfully', [
                'file' => $filename,
                'url' => $url,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return error_res(403, 'Validation Error', $e->errors());
        } catch (\Exception $e) {
            return error_res(500, 'An unexpected error occurred', $e->getMessage());
        }
    }
}
