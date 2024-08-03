<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="Hoghogi Search",
 *     version="1.0.0",
 *     description="API documention",
 *     termsOfService="https://jsonTeam.ir",
 *     contact={
 *         "name"="mahdi",  
 *         "email"="mahdishoorabi@gmail.com",
 *         "url"="https://jsonTeam.ir"
 *     },
 *     license={
 *         "name"="License Name",
 *         "url"="https://jsonTeam.ir"
 *     }
 * )
 */

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
