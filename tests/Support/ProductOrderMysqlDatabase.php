<?php

namespace Tests\Support;

final class ProductOrderMysqlDatabase
{
    public static function configure(): void
    {
        if (!app()->environment('testing') || getenv('PRODUCT_ORDERS_TEST_MYSQL') !== '1'
            || getenv('PRODUCT_ORDERS_TEST_DATABASE') !== 'gsmmix_product_test') {
            throw new \RuntimeException('Only the disposable product-order test database is allowed.');
        }
        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'mysql', 'url' => null, 'host' => '127.0.0.1',
                'port' => (int) getenv('PRODUCT_ORDERS_TEST_PORT'),
                'database' => 'gsmmix_product_test', 'username' => 'gsmmix_test',
                'password' => 'synthetic-test-only', 'unix_socket' => '',
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '', 'prefix_indexes' => true, 'strict' => true, 'engine' => null,
            ],
            'cache.default' => 'array',
        ]);
        app('db')->purge('mysql');
    }
}
