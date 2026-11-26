<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppRelease extends Model
{
    protected $table = 'app_releases';
    protected $fillable = [
        'version', 'versionCode', 'notes', 'apkPath', 'apkUrl', 'apkSize', 'sha256', 'token',
        'mandatory', 'published', 'publishedAt', 'downloads', 'createdById',
    ];
    protected $casts = ['mandatory' => 'boolean', 'published' => 'boolean', 'publishedAt' => 'datetime'];
}
