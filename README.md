# Sapienstech Consultant Laravel MVP

## Requirements
PHP 8.2+, Composer, Node.js 20+, npm.

## Install
```powershell
cd D:\sapienstech-consultant-laravel
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Force database\database.sqlite
php artisan migrate
npm install
composer run dev
```
Open http://127.0.0.1:8000/consultant/dashboard

## Feature switches
Edit `config/consultant.php`:
```php
'features' => [
 'dashboard' => true,
 'blog_management' => false,
 'book_access' => true,
 'question_management' => true,
 'quiz_management' => true,
];
```
A disabled sidebar feature is hidden and its route is blocked by middleware.

Sidebar keys/routes/icons are platform-owned. Labels are tenant-configurable under `labels`. Theme has independent color, typography, shape, layout, effect, gradient, asset and preset controls.

This MVP uses demo student data so it runs without the old PHP database layer. For the real SaaS, tenant resolution, authorization, policies and tenant-scoped queries must be added before production. The project brief identifies tenant isolation/privacy as critical.
