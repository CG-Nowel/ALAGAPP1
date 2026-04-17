<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Response;

class DashboardController extends Controller
{
    /**
     * Redirect to role-appropriate dashboard.
     */
    public function index(): void
    {
        match ($this->userType()) {
            'ADMIN' => $this->redirect('/admin/dashboard'),
            'DOCTOR', 'DOCTOR_OWNER' => $this->redirect('/doctor/dashboard'),
            'PARENT' => $this->redirect('/parent/dashboard'),
            default => $this->redirect('/login'),
        };
    }
}
