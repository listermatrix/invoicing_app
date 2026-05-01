<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'role' => $this->role ?? 'user',
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'token' => $this->when($this->token, $this->token),
        ];
    }
}
