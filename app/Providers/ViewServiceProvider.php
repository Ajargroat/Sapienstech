<?php namespace App\Providers;
use Illuminate\Support\Facades\View; use Illuminate\Support\ServiceProvider;
class ViewServiceProvider extends ServiceProvider {
 public function register():void{}
 public function boot():void{
    View::composer('layouts.consultant', function($view) {
     $c = config('consultant');
     $view->with([
         'tenant'  => $c['tenant'],
         'theme'   => $c['theme'],
         'labels'  => $c['labels'],
         'profile' => $c['profile'] ?? [],
         'filters' => $c['filters'] ?? [],
         'sidebar' => $c['sidebar'] ?? [],
     ]);
 });
}
}
