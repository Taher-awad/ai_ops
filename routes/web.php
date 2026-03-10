<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/metrics', function () {
    $metrics = DB::table('metrics')->get();
    
    $output = "";
    $grouped = [];
    foreach ($metrics as $m) {
        $grouped[$m->name][] = $m;
    }
    
    foreach ($grouped as $name => $items) {
        if (str_ends_with($name, '_total')) {
            $output .= "# TYPE {$name} counter\n";
        } elseif (str_ends_with($name, '_seconds')) {
            $output .= "# TYPE {$name} histogram\n";
        }
        
        foreach ($items as $item) {
            $labels = json_decode($item->labels, true);
            $labelStrings = [];
            foreach ($labels as $k => $v) {
                $vStr = addslashes($v);
                $labelStrings[] = "{$k}=\"{$vStr}\"";
            }
            $labelStr = implode(',', $labelStrings);
            $val = $item->value;
            $output .= "{$name}{{$labelStr}} {$val}\n";
        }
    }
    
    return response($output)->header('Content-Type', 'text/plain; version=0.0.4');
});
