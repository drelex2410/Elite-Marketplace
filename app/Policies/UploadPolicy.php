<?php

namespace App\Policies;

use App\Models\Upload;
use App\Models\User;

class UploadPolicy
{
    public function view(User $user, Upload $upload): bool
    {
        return $this->staff($user) || (int) $upload->user_id === (int) $user->id;
    }

    public function delete(User $user, Upload $upload): bool
    {
        return $this->view($user, $upload);
    }

    private function staff(User $user): bool
    {
        return $user->user_type === 'admin' || ($user->user_type === 'staff' && $user->can('manage_seller_uploads'));
    }
}
