<?php

namespace App\Http\Controllers;

class CaricaSocialeController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,segreteria');
    }

    // Store, update e destroy rimossi: organi e cariche sono definiti in config e sincronizzati dal seeder.
}
