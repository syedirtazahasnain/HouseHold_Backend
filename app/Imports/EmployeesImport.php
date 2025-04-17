<?php

namespace App\Imports;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class EmployeesImport implements ToModel, WithHeadingRow, WithChunkReading, ShouldQueue
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {

        $emp_id = $row['Emp Id'] ?? $row['emp_id'] ?? $row['employee_id'] ?? null;
        if (!$emp_id) {
            Log::channel('employee_import')->error("Missing employee ID", [
                'row' => $row
            ]);
            return null;
        }

        $user = User::updateOrCreate(
            ['emp_id'        => $emp_id ],
            [
                'name'       => $row['Name'] ?? $row['name'] ?? 'Unknown',
                'email'      => $row['Email'] ?? $row['email'] ?? null,
                'password'   => bcrypt('test@123'),
                'd_o_j'      => $row['date_of_joining'] ?? $row['Date Of Joining'] ?? $row['date of joining'] ?? null,
                'status'     => $row['status'] ?? 'Probation',
                'location'   => $row['office_location'] ?? $row['location'] ?? null,
                'is_admin'   => 3,
            ]
        );

        Log::channel('employee_import')->info("Employee Inserted/Updated", [
            'emp_id' => $emp_id,
            'name' => $user->name,
            'd_o_j' => $user->d_o_j,
        ]);

        return $user;
    }

    public function chunkSize(): int
    {
        return 100;
    }
}
