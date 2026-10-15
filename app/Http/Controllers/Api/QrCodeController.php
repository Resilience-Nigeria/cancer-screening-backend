<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeController extends Controller
{
    public function index()
    {

$qr = QrCode::format('png')
    ->size(300)
    ->generate('https://icw-app.nicrat.gov.ng/icw2026/certificate/');

return response($qr)->header('Content-Type', 'image/png');
    }


}