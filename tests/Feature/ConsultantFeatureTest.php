<?php namespace Tests\Feature; use Tests\TestCase;
class ConsultantFeatureTest extends TestCase{
 public function test_dashboard_loads():void{$r=$this->get('/consultant/dashboard');$r->assertOk()->assertSee('داشبورد مشاور');}
 public function test_enabled_blog_loads():void{$r=$this->get('/consultant/blog');$r->assertOk()->assertSee('مدیریت وبلاگ');}
 public function test_disabled_feature_is_blocked():void{config(['consultant.features.blog_management'=>false]);$this->get('/consultant/blog')->assertNotFound();}
}
