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
    ->generate('https://ncsr.nicrat.gov.ng/front/bloom');

return response($qr)->header('Content-Type', 'image/png');
    }


}