<?php

namespace App\Http\Controllers;

class SmtpStatusController extends Controller
{
    public function __invoke()
    {
        $up = false;
        $port = (int) config('services.smtp.port', 25);

        try {
            $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 2);
            if ($conn) {
                $banner = fgets($conn, 512);
                $up = str_starts_with(trim($banner), '220');
                fwrite($conn, "QUIT\r\n");
                fclose($conn);
            }
        } catch (\Throwable) {
            $up = false;
        }

        return response()->json(['up' => $up]);
    }
}
