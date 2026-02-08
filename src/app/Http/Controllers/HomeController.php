<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    public function index()
    {
        var_dump('test');
        return view('welcome');
    }
}
