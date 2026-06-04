<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('landing'));

Route::get('/kebijakan-privasi', fn () => view('legal.privacy-policy'))->name('legal.privacy');
Route::get('/syarat-layanan', fn () => view('legal.terms-of-service'))->name('legal.terms');
Route::get('/disclaimer', fn () => view('legal.disclaimer'))->name('legal.disclaimer');
Route::get('/cookie-policy', fn () => view('legal.cookie-policy'))->name('legal.cookies');
Route::get('/kontak', fn () => view('legal.contact'))->name('legal.contact');
