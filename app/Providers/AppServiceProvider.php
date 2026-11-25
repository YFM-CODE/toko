<?php

namespace App\Providers;

use App\Interfaces\KategoriInterface;
use App\Interfaces\LogInterface;
use App\Interfaces\PelangganInterface;
use App\Interfaces\ProdukInterface;
use App\Interfaces\TransaksiInterface;
use App\Interfaces\UserInterface;
use App\Repositories\KategoriRepository;
use App\Repositories\LogRepository;
use App\Repositories\PelangganRepository;
use App\Repositories\ProdukRepository;
use App\Repositories\TransaksiRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserInterface::class, UserRepository::class);
        $this->app->bind(KategoriInterface::class, KategoriRepository::class);
        $this->app->bind(ProdukInterface::class, ProdukRepository::class);
        $this->app->bind(PelangganInterface::class, PelangganRepository::class);
        $this->app->bind(TransaksiInterface::class, TransaksiRepository::class);
        $this->app->bind(LogInterface::class, LogRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
