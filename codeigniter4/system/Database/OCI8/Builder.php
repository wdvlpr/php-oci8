<?php

/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014-2019 British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package    CodeIgniter
 * @author     CodeIgniter Dev Team
 * @copyright  2014-2019 British Columbia Institute of Technology (https://bcit.ca/)
 * @license    https://opensource.org/licenses/MIT	MIT License
 * @link       https://codeigniter.com
 * @since      Version 4.0.0
 * @filesource
 */

namespace CodeIgniter\Database\OCI8;

use CodeIgniter\Database\BaseBuilder;

/**
 * Builder for MySQLi
 */
class Builder extends BaseBuilder
{
	/**
	 * Identifier escape character
	 *
	 * @var string
	 */
	protected $escapeChar = '"';

	/**
	 * ORDER BY random keyword
	 *
	 * @var array
	 */
	protected $randomKeyword = [
		'DBMS_RANDOM.RANDOM'
	];

	/**
	 * COUNT string
	 *
	 * @used-by CI_DB_driver::count_all()
	 * @used-by BaseBuilder::count_all_results()
	 *
	 * @var string
	 */
	protected $countString = 'SELECT COUNT(1) ';

	/**
	 * Limit used flag
	 *
	 * If we use LIMIT, we'll add a field that will
	 * throw off num_fields later.
	 *
	 * @var boolean
	 */
	protected $limitUsed = false;

	/**
	 * Insert batch statement
	 *
	 * Generates a platform-specific insert string from the supplied data.
	 *
	 * @param string $table  Table name
	 * @param array  $keys   INSERT keys
	 * @param array  $values INSERT values
	 *
	 * @return string
	 */
	protected function _insertBatch(string $table, array $keys, array $values): string
	{
		$keys            = implode(', ', $keys);
		$has_primary_key = in_array('PRIMARY', array_column($this->db->getIndexData($table), 'type'), true);

		// ORA-00001 measures
		if ($has_primary_key)
		{
			$sql                 = 'INSERT INTO ' . $table . ' (' . $keys . ") \n SELECT * FROM (\n";
			$select_query_values = [];

			for ($i = 0, $c = count($values); $i < $c; $i++)
			{
				$select_query_values[] = 'SELECT ' . substr(substr($values[$i], 1), 0, -1) . ' FROM DUAL';
			}

			return $sql . implode("\n UNION ALL \n", $select_query_values) . "\n)";
		}

		$sql = "INSERT ALL\n";

		for ($i = 0, $c = count($values); $i < $c; $i++)
		{
			$sql .= '	INTO ' . $table . ' (' . $keys . ') VALUES ' . $values[$i] . "\n";
		}

		return $sql . 'SELECT * FROM dual';
	}

	//--------------------------------------------------------------------

	/**
	 * Replace statement
	 *
	 * Generates a platform-specific replace string from the supplied data
	 *
	 * @param string $table  The table name
	 * @param array  $keys   The insert keys
	 * @param array  $values The insert values
	 *
	 * @return string
	 */
	protected function _replace(string $table, array $keys, array $values): string
	{
		$field_names = array_map(function ($column_name) {
			return trim($column_name, '"');
		}, $keys);

		$unique_indexes     = array_filter($this->db->getIndexData($table), function ($index) use ($field_names) {
			$has_all_fields = count(array_intersect($index->fields, $field_names)) === count($index->fields);

			return (($index->type === 'PRIMARY') && $has_all_fields);
		});
		$replaceable_fields = array_filter($keys, function ($column_name) use ($unique_indexes) {
			foreach ($unique_indexes as $index)
			{
				if (in_array(trim($column_name, '"'), $index->fields, true))
				{
					return false;
				}
			}

			return true;
		});

		$sql = 'MERGE INTO ' . $table . "\n USING (SELECT ";

		$sql .= implode(', ', array_map(function ($column_name, $value) {
			return $value . ' ' . $column_name;
		}, $keys, $values));

		$sql .= ' FROM DUAL) "_replace" ON ( ';

		$on_list   = [];
		$on_list[] = '1 != 1';

		foreach ($unique_indexes as $index)
		{
			$on_list[] = '(' . implode(' AND ', array_map(function ($column_name) use ($table) {
				return $table . '."' . $column_name . '" = "_replace"."' . $column_name . '"';
			}, $index->fields)) . ')';
		}

		$sql .= implode(' OR ', $on_list) . ') WHEN MATCHED THEN UPDATE SET ';

		$sql .= implode(', ', array_map(function ($column_name) {
			return $column_name . ' = "_replace".' . $column_name;
		}, $replaceable_fields));

		$sql .= ' WHEN NOT MATCHED THEN INSERT (' . implode(', ', $replaceable_fields) . ') VALUES ';
		$sql .= ' (' . implode(', ', array_map(function ($column_name) {
			return '"_replace".' . $column_name;
		}, $replaceable_fields)) . ')';

		return $sql;
	}

	//--------------------------------------------------------------------

	/**
	 * Truncate statement
	 *
	 * Generates a platform-specific truncate string from the supplied data
	 *
	 * If the database does not support the truncate() command,
	 * then this method maps to 'DELETE FROM table'
	 *
	 * @param string $table The table name
	 *
	 * @return string
	 */
	protected function _truncate(string $table): string
	{
		return 'TRUNCATE TABLE ' . $table;
	}

	//--------------------------------------------------------------------

	/**
	 * Delete
	 *
	 * Compiles a delete string and runs the query
	 *
	 * @param mixed   $where      The where clause
	 * @param integer $limit      The limit clause
	 * @param boolean $reset_data
	 *
	 * @return mixed
	 * @throws \CodeIgniter\Database\Exceptions\DatabaseException
	 */
	public function delete($where = '', int $limit = null, bool $reset_data = true)
	{
		if (! empty($limit))
		{
			$this->QBLimit = $limit;
		}

		return parent::delete($where, null, $reset_data);
	}

	//--------------------------------------------------------------------

	/**
	 * Delete statement
	 *
	 * Generates a platform-specific delete string from the supplied data
	 *
	 * @param string $table The table name
	 *
	 * @return string
	 */
	protected function _delete(string $table): string
	{
		if ($this->QBLimit)
		{
			$this->where('rownum <= ', $this->QBLimit, false);
			$this->QBLimit = false;
		}

		return parent::_delete($table);
	}

	//--------------------------------------------------------------------

	/**
	 * Update statement
	 *
	 * Generates a platform-specific update string from the supplied data
	 *
	 * @param string $table  the Table name
	 * @param array  $values the Update data
	 *
	 * @return string
	 */
	protected function _update(string $table, array $values): string
	{
		$valStr = [];

		foreach ($values as $key => $val)
		{
			$valStr[] = $key . ' = ' . $val;
		}

		if ($this->QBLimit)
		{
			$this->where('rownum <= ', $this->QBLimit, false);
		}

		return 'UPDATE ' . $this->compileIgnore('update') . $table . ' SET ' . implode(', ', $valStr)
				. $this->compileWhereHaving('QBWhere')
				. $this->compileOrderBy();
	}

	//--------------------------------------------------------------------

	/**
	 * LIMIT string
	 *
	 * Generates a platform-specific LIMIT clause.
	 *
	 * @param string $sql SQL Query
	 *
	 * @return string
	 */
	protected function _limit(string $sql): string
	{
		if (version_compare($this->db->getVersion(), '12.1', '>='))
		{
			// OFFSET-FETCH can be used only with the ORDER BY clause
			empty($this->QBOrderBy) && $sql .= ' ORDER BY 1';

			return $sql . ' OFFSET ' . (int) $this->QBOffset . ' ROWS FETCH NEXT ' . $this->QBLimit . ' ROWS ONLY';
		}

		$this->limitUsed = true;
		return 'SELECT * FROM (SELECT inner_query.*, rownum rnum FROM (' . $sql . ') inner_query WHERE rownum < ' . ($this->QBOffset + $this->QBLimit + 1) . ')'
			. ($this->QBOffset ? ' WHERE rnum >= ' . ($this->QBOffset + 1) : '');
	}

	/**
	 * Resets the query builder values.  Called by the get() function
	 */
	protected function resetSelect()
	{
		$this->limitUsed = false;
		parent::resetSelect();
	}

	//--------------------------------------------------------------------

	/**
	 * Insert statement
	 *
	 * Generates a platform-specific insert string from the supplied data
	 *
	 * @param string $table         The table name
	 * @param array  $keys          The insert keys
	 * @param array  $unescapedKeys The insert values
	 *
	 * @return string
	 */
	protected function _insert(string $table, array $keys, array $unescapedKeys): string
	{
		// Has a strange design.
		// Processing to get the table where the last insert was performed for insertId method.
		$this->db->latestInsertedTableName = $table;

		return 'INSERT ' . $this->compileIgnore('insert') . 'INTO ' . $table . ' (' . implode(', ', $keys) . ') VALUES (' . implode(', ', $unescapedKeys) . ') RETURNING ROWID INTO :CI_OCI8_ROWID';
	}
}
