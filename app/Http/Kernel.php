<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    // اترك middleware و middlewareGroups الافتراضية كما هي (لا داعي لنسخها هنا)
    // الأهم: لا تضع مفاتيح role/permission هنا إذا سجلناها في AppServiceProvider.
}
