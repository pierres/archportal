<?php

namespace AppBundle\Request\Datatables;

class Order
{
    /** @var Column */
    private $column;
    /** @var string */
    private $dir;

    const ASC = 'asc';
    const DESC = 'desc';

    /**
     * @param Column $column
     * @param string $dir
     */
    public function __construct(Column $column, string $dir)
    {
        $this->column = $column;
        $this->dir = $dir;
    }

    /**
     * @return Column
     */
    public function getColumn(): Column
    {
        return $this->column;
    }

    /**
     * @return string
     */
    public function getDir(): string
    {
        return $this->dir;
    }
}