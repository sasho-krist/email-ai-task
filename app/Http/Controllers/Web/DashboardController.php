<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TaskDraft;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        try {
            $drafts = TaskDraft::query()
                ->with('incomingEmail')
                ->latest()
                ->limit(20)
                ->get();
        } catch (\Throwable $exception) {
            report($exception);

            $drafts = collect();
        }

        return view('dashboard', [
            'drafts' => $drafts,
        ]);
    }
}
