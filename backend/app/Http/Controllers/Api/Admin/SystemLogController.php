<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;

class SystemLogController extends Controller
{
    /**
     * Reads the last N lines of the current Laravel log file.
     * Extremely lightweight to avoid memory spikes from large logs.
     */
    public function index()
    {
        $logPath = storage_path('logs/laravel.log');

        if (!File::exists($logPath)) {
            return response()->json(['logs' => []]);
        }

        $lines = 50;
        $logs = [];
        $file = fopen($logPath, 'r');

        if ($file) {
            fseek($file, -1, SEEK_END);
            $pos = ftell($file);
            $linesFound = 0;
            $content = '';

            // Read backwards character by character
            while ($pos >= 0 && $linesFound < $lines) {
                fseek($file, $pos);
                $char = fgetc($file);
                $content = $char . $content;

                if ($char === "\n") {
                    $linesFound++;
                }
                $pos--;
            }
            fclose($file);

            // Split into an array, filter empty lines, and reverse so newest is at the bottom
            $logLines = array_filter(explode("\n", trim($content)));
            foreach ($logLines as $line) {
                $logs[] = $line;
            }
        }

        return response()->json(['logs' => $logs]);
    }
}
