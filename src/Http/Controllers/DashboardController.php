<?php

namespace MahmoudMhamed\DbLens\Http\Controllers;

use Illuminate\Routing\Controller;
use MahmoudMhamed\DbLens\Services\ConnectionManager;

class DashboardController extends Controller
{
    public function index(ConnectionManager $cm)
    {
        return redirect()->route('dblens.database.show', ['connection' => $cm->default()]);
    }
}
