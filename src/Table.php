<?php

namespace sower\swoole;

use Swoole\Table as SwooleTable;

class Table
{
    /**
     * Registered swoole tables.
     *
     * @var array
     */
    protected $tables = [];

    /**
     * Add a swoole table to existing tables.
     *
     * @param string      $name
     * @param SwooleTable $table
     *
     * @return Table
     */
    public function add(string $name, SwooleTable $table)
    {
        $this->tables[$name] = $table;

        return $this;
    }

    /**
     * Get a swoole table by its name from existing tables.
     *
     * @param string $name
     *
     * @return SwooleTable $table
     */
    public function get(string $name)
    {
        return $this->tables[$name] ?? null;
    }

    /**
     * Get all existing swoole tables.
     *
     * @return array
     */
    public function getAll()
    {
        return $this->tables;
    }

    /**
     * Dynamically access table.
     *
     * @param  string $key
     *
     * @return SwooleTable
     */
    public function __get($key)
    {
        return $this->get($key);
    }
}
