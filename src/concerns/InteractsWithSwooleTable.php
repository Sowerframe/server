<?php declare(strict_types = 1);
namespace sower\swoole\concerns;

use Swoole\Table as SwooleTable;
use sower\App;
use sower\Container;
use sower\swoole\Table;

/**
 * Trait InteractsWithSwooleTable
 *
 * @property Container $container
 * @property App       $app
 */
trait InteractsWithSwooleTable
{

    /**
     * @var Table
     */
    protected $currentTable;

    /**
     * Register customized swoole talbes.
     */
    protected function createTables()
    {
        $this->currentTable = new Table();
        $this->registerTables();
    }

    /**
     * Register user-defined swoole tables.
     */
    protected function registerTables()
    {
        $tables = $this->container->make('config')->get('swoole.tables', []);

        foreach ($tables as $key => $value) {
            $table   = new SwooleTable($value['size']);
            $columns = $value['columns'] ?? [];
            foreach ($columns as $column) {
                if (isset($column['size'])) {
                    $table->column($column['name'], $column['type'], $column['size']);
                } else {
                    $table->column($column['name'], $column['type']);
                }
            }
            $table->create();

            $this->currentTable->add($key, $table);
        }
    }

    /**
     * Bind swoole table to Laravel app container.
     */
    protected function bindSwooleTable()
    {
        $this->app->bind(Table::class, function () {
            return $this->currentTable;
        });

        $this->app->bind('swoole.table', Table::class);
    }
}
