<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        // ChatService::unreadTotal() memoizes per actor key "for the request".
        // PHPUnit runs the whole suite in one process, and on SQLite rolled
        // back rowids are reused, so actor keys (u-1, s-1, ...) repeat across
        // tests. Without this reset a stale unread count leaks into the next
        // test that happens to mint the same ids.
        \App\Support\ChatService::$unreadMemo = [];

        parent::tearDown();
    }
}
