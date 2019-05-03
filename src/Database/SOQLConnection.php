<?php

namespace Lester\EloquentSalesForce\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\MySqlBuilder;
use Illuminate\Database\Query\Processors\MySqlProcessor;
use Illuminate\Database\Query\Grammars\MySqlGrammar as QueryGrammar;
use Illuminate\Database\Schema\Grammars\MySqlGrammar as SchemaGrammar;
use Closure;
/** @scrutinizer ignore-call */
use Forrest;
use Carbon\Carbon;

class SOQLConnection extends Connection
{

	/**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) {

	        $statement = $this->prepare($query, $bindings);
            /** @scrutinizer ignore-call */
	        return Forrest::query($statement)['records'];

        });
    }

	protected function run($query, $bindings, Closure $callback)
	{
		$start = microtime(true);

		try {
			$result = $this->runQueryCallback($query, $bindings, $callback);
        } catch (QueryException $e) {
            $result = $this->handleQueryException(
                $e, $query, $bindings, $callback
            );
        }
        // Once we have run the query we will calculate the time that it took to run and
        // then log the query, bindings, and execution time so we will report them on
        // the event that the developer needs them. We'll log time in milliseconds.
        $this->logQuery(
            $query, $bindings, $this->getElapsedTime($start)
        );
        return $result;
	}

    private function prepare($query, $bindings)
    {
        $query = str_replace('`', '', $query);
        $bindings = array_map(function($item) {
        try {
            if (Carbon::parse($item) !== false &&
                !$this->isSalesForceId($item)) {
                    return $item;
                }
            } catch (\Exception $e) {
                return "'$item'";
            }
            return "'$item'";
        }, $bindings);

        $query = str_replace_array('?', $bindings, $query);
        return $query;
    }

    /**
     * Based on characters and length of $str, determine if it appears to be a
     * SalesForce ID.
     *
     * @param string $str String to test
     *
     * @return bool
     */
    private function isSalesForceId($str)
    {
        return \preg_match('/^[0-9a-zA-Z]{15,18}$/', $str) >= 0;
    }
}
