<?php

namespace DAO\Operator;

use DAO\Operator;

/** operator interface */
interface IOperator
{
    /** enrich record */
    public function enrich($r);
}
