<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'my_role' => $this->role,
            'emp_id' => $this->emp_id,
            'location' => $this->location,
            'doj' => $this->d_o_j,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
