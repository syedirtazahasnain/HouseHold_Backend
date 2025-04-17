<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Imports\EmployeesImport;
use Maatwebsite\Excel\Facades\Excel;

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
}
