<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    public function normal()
    {
        return response()->json(['status' => 'ok']);
    }

    public function slow(Request $request)
    {
        if ($request->query('hard') == '1') {
            sleep(rand(5, 7));
        } else {
            usleep(rand(100000, 500000)); // 100ms - 500ms
        }
        return response()->json(['status' => 'slowly ok']);
    }

    public function error()
    {
        throw new \Exception("Simulated system error");
    }

    public function random(Request $request)
    {
        $rand = rand(1, 100);
        if ($rand <= 10) {
            return $this->error();
        } elseif ($rand <= 30) {
            return $this->slow($request);
        }
        return $this->normal();
    }

    public function db(Request $request)
    {
        if ($request->query('fail') == '1') {
            DB::select('SELECT * FROM non_existent_table_for_ai_ops_telemetry');
        } else {
            DB::select('SELECT 1');
        }
        return response()->json(['status' => 'db ok']);
    }

    public function validateData(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'age' => 'required|integer|between:18,60',
        ]);
        
        return response()->json(['status' => 'validation passed']);
    }
}
