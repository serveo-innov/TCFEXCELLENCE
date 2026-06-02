<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="TCF Excellence API",
 *     version="1.0.0",
 *     description="API de la plateforme TCF Excellence - Preparation TCF Canada pour l'Afrique francophone"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="sanctum"
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="Serveur local"
 * )
 */
abstract class Controller
{
    //
}