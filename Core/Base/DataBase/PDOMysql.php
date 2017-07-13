<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  carlos@facturascripts.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Core\Base\DataBase;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Clase para conectar a MySQL utilizando pdo_mysql.
 *
 * Basado en: http://culttt.com/2012/10/01/roll-your-own-pdo-php-class/
 *
 * @author Francesc Pineda Segarra <francesc.pineda.segarra@gmail.com>
 */
class PDOMysql implements DatabaseEngine
{
    /**
     * Database Handler
     * @var PDO
     */
    private $dbh;
    /**
     * Errores devueltos
     * @var string
     */
    private $error;
    /**
     * Contiene la declaración
     * @var PDOStatement|PDOException
     */
    private $stmt;

    /**
     * Conecta a la base de datos.
     *
     * @param string $error
     *
     * @return PDO
     */
    public function connect(&$error)
    {
        if (!extension_loaded('pdo')) {
            $this->error = 'No tienes instalada la extensión de PHP para PDO.';
            $error = $this->error;
            return null;
        }
        if (!extension_loaded('pdo_mysql')) {
            $this->error = 'No tienes instalada la extensión de PHP para PDO MySQL.';
            $error = $this->error;
            return null;
        }

        $dsn = 'mysql:host=' . FS_DB_HOST . ';port=' . FS_DB_PORT . ';dbname=' . FS_DB_NAME;
        $options = [
            // Or add to $dsn ". ';charset=utf8'"
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

        // Creamos una nueva instancia PDO
        try {
            $this->dbh = new PDO($dsn, FS_DB_USER, FS_DB_PASS, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $error = $this->error;
            return null;
        }

        /// Desactivamos las claves ajenas
        if (FS_FOREIGN_KEYS !== '1') {
            $this->query('SET foreign_key_checks = 0;');
            $this->execute();
        }

        return $this->dbh;
    }

    /**
     * Prepares a statement for execution and returns a statement object
     *
     * @param string $queryString <p>This must be a valid SQL statement for the target database server.</p>
     */
    public function query($queryString)
    {
        $this->stmt = $this->dbh->prepare($queryString);
    }

    /**
     * Binds a value to a parameter
     *
     * @param mixed $param <p>Parameter identifier. For a prepared statement using named
     * placeholders, this will be a parameter name of the form
     * :name. For a prepared statement using
     * question mark placeholders, this will be the 1-indexed position of
     * the parameter.</p>
     * @param mixed $value <p>The value to bind to the parameter.</p>
     * @param int $type [optional] <p>Explicit data type for the parameter using the PDO::PARAM_*
     * constants.
     */
    public function bind($param, $value, $type = PDO::PARAM_STR)
    {
        if ($type === null) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case ($value === null):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
    }

    /**
     * Executes a prepared statement
     *
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function execute()
    {
        return $this->stmt->execute();
    }

    /**
     * Returns an array containing all of the result set rows
     *
     * @return array <b>PDOStatement::fetchAll</b> returns an array containing
     * all of the remaining rows in the result set. The array represents each
     * row as either an array of column values or an object with properties
     * corresponding to each column name.
     */
    public function resultSet()
    {
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches the next row from a result set
     *
     * @return mixed The return value of this function on success depends on the fetch type. In
     * all cases, <b>FALSE</b> is returned on failure.
     */
    public function single()
    {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Returns the number of rows affected by the last SQL statement
     *
     * @return int the number of rows.
     */
    public function rowCount()
    {
        return $this->stmt->rowCount();
    }

    /**
     * Returns the ID of the last inserted row or sequence value
     *
     * @return string representing the row ID of the last row that was inserted into the database.
     */
    public function lastInsertId()
    {
        return $this->dbh->lastInsertId();
    }

    /**
     * Initiates a transaction
     *
     * @param PDO $link
     *
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function beginTransaction($link)
    {
        return $this->dbh->beginTransaction();
    }

    /**
     * Commits a transaction
     *
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function endTransaction()
    {
        return $this->dbh->commit();
    }

    /**
     * Rolls back a transaction
     *
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function cancelTransaction()
    {
        return $this->dbh->rollBack();
    }

    /**
     * Dump an SQL prepared command
     *
     * @return bool No value is returned.
     */
    public function debugDumpParams()
    {
        return $this->stmt->debugDumpParams();
    }

    /**
     * Elimina código problemático de postgresql.
     *
     * @param string $sql
     *
     * @return string
     */
    private function fixPostgresql($sql)
    {
        $search = ['::character varying', 'without time zone', 'now()', 'CURRENT_TIMESTAMP', 'CURRENT_DATE'];
        $replace = ['', '', "'00:00'", "'" . date('Y-m-d') . " 00:00:00'", date("'Y-m-d'")];
        return str_replace($search, $replace, $sql);
    }

    /**
     * Genera el SQL para establecer las restricciones proporcionadas.
     *
     * @param array $xmlCons
     *
     * @return string
     */
    public function generateTableConstraints($xmlCons)
    {
        $sql = '';
        foreach ($xmlCons as $res) {
            $sql .= ', CONSTRAINT ' . $res['nombre'] . ' ' . $res['consulta'];
        }

        return $this->fixPostgresql($sql);
    }

    /**
     * TODO
     *
     * @param array $colData
     *
     * @return string
     */
    private function getConstraints($colData)
    {
        $notNull = ($colData['nulo'] === 'NO');
        $result = ' NULL';
        if ($notNull) {
            $result = ' NOT' . $result;
        }

        $defaultNull = ($colData['defecto'] === null);
        if ($defaultNull && !$notNull) {
            $result .= ' DEFAULT NULL';
        } else {
            if ($colData['defecto'] !== '') {
                $result .= ' DEFAULT ' . $colData['defecto'];
            }
        }

        return $result;
    }

    /**
     * Genera el SQL con el tipo de campo y las constraints DEFAULT y NULL
     *
     * @param array $colData
     *
     * @return string
     */
    private function getTypeAndConstraints($colData)
    {
        $type = stripos('integer,serial', $colData['tipo']) === false ? strtolower($colData['tipo']) : FS_DB_INTEGER;
        switch (true) {
            case ($type === 'serial'):
            case (stripos($colData['defecto'], 'nextval(') !== false):
                $contraints = ' NOT NULL AUTO_INCREMENT';
                break;

            default:
                $contraints = $this->getConstraints($colData);
                break;
        }
        return ' ' . $type . $contraints;
    }

    /**
     * Convierte los datos leidos del sqlColumns a estructura de trabajo
     *
     * @param array $colData
     *
     * @return array
     */
    public function columnFromData($colData)
    {
        $result = array_change_key_case($colData);
        $result['is_nullable'] = $result['null'];
        $result['name'] = $result['field'];

        unset($result['null'], $result['field']);

        return $result;
    }

    /**
     * Información sobre el motor de base de datos
     *
     * @param PDO $link
     *
     * @return string
     */
    public function version($link)
    {
        return 'MYSQL ' . $this->dbh->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * Cierra la conexión con la base de datos
     *
     * @param PDO $link
     */
    public function close($link)
    {
        $this->cancelTransaction();
        $this->dbh = null;
    }

    /**
     * Último mensaje de error generado un operación con la BD
     *
     * @param PDO $link
     *
     * @return string
     */
    public function errorMessage($link)
    {
        return $this->error;
    }

    /**
     * Confirma las operaciones realizadas sobre la conexión
     * desde el beginTransaction
     *
     * @param PDO $link
     */
    public function commit($link)
    {
        $this->endTransaction();
    }

    /**
     * Deshace las operaciones realizadas sobre la conexión
     * desde el beginTransaction
     *
     * @param PDO $link
     */
    public function rollback($link)
    {
        $this->cancelTransaction();
    }

    /**
     * Indica si la conexión tiene una transacción abierta
     *
     * @param PDO $link
     *
     * @return bool
     */
    public function inTransaction($link)
    {
        // TODO: Implement inTransaction() method.
        return false;
    }

    /**
     * Ejecuta una sentencia SQL de tipo select, y devuelve un array con los resultados,
     * o array vacío en caso de fallo.
     *
     * @param PDO $link
     * @param string $sql
     *
     * @return array
     */
    public function select($link, $sql)
    {
        $result = [];
        $this->beginTransaction($link);
        $this->query($sql);
        $aux = $this->resultSet();
        $this->endTransaction();
        if (!empty($aux)) {
            foreach ($aux as $row) {
                $result[] = $row;
            }
        }
        unset($aux);

        return $result;
    }

    /**
     * Ejecuta una sentencia DDL sobre la conexión.
     * Si no hay transacción abierta crea una y la finaliza
     *
     * @param PDO $link
     * @param string $sql
     *
     * @return bool
     */
    public function exec($link, $sql)
    {
        $this->query($sql);
        return (!$this->execute());
    }

    /**
     * Compara los tipos de datos de una columna numerica.
     *
     * @param string $dbType
     * @param string $xmlType
     *
     * @return bool
     */
    private function compareDataTypeNumeric($dbType, $xmlType)
    {
        return (0 === strpos($dbType, 'int(') && $xmlType === 'INTEGER') ||
            (0 === strpos($dbType, 'double') && $xmlType === 'double precision');
    }

    /**
     * Compara los tipos de datos de una columna alfanumerica.
     *
     * @param string $dbType
     * @param string $xmlType
     *
     * @return bool
     */
    private function compareDataTypeChar($dbType, $xmlType)
    {
        $result = 0 === strpos($xmlType, 'character varying(');
        if ($result) {
            $result = (0 === strpos($dbType, 'varchar(')) || (0 === strpos($dbType, 'char('));
        }
        return $result;
    }

    /**
     * Compara las columnas indicadas en los arrays
     *
     * @param string $dbType
     * @param string $xmlType
     *
     * @return bool
     */
    public function compareDataTypes($dbType, $xmlType)
    {
        $result = (
            ($dbType === $xmlType) ||
            ($dbType === 'tinyint(1)' && $xmlType === 'boolean') ||
            (substr($dbType, 8, -1) === substr($xmlType, 18, -1)) ||
            (substr($dbType, 5, -1) === substr($xmlType, 18, -1))
        );

        if (!$result) {
            $result = $this->compareDataTypeNumeric($dbType, $xmlType);
        }

        if (!$result) {
            $result = $this->compareDataTypeChar($dbType, $xmlType);
        }

        return $result;
    }

    /**
     * Devuelve un array con los nombres de las tablas de la base de datos.
     *
     * @param PDO $link
     *
     * @return array
     */
    public function listTables($link)
    {
        $tables = [];

        $this->query('SHOW TABLES;');
        $aux = $this->resultSet();
        if ($aux) {
            foreach ($aux as $a) {
                $key = 'Tables_in_' . FS_DB_NAME;
                if (isset($a[$key])) {
                    $tables[] = $a[$key];
                }
            }
        }
        return $tables;
    }

    /**
     * Escapa la cadena indicada
     *
     * @param PDO $link
     * @param string $str
     *
     * @return string
     */
    public function escapeString($link, $str)
    {
        return $this->dbh->quote($str);
    }

    /**
     * Indica el formato de fecha que utiliza la BD
     *
     * @return string
     */
    public function dateStyle()
    {
        return 'Y-m-d';
    }

    /**
     * Indica el SQL a usar para convertir la columna en Integer
     *
     * @param string $colName
     *
     * @return string
     */
    public function sql2int($colName)
    {
        return 'CAST(' . $colName . ' as UNSIGNED)';
    }

    /**
     * Comprueba la existencia de una secuencia
     * A partir del campo default de una tabla
     * comprueba si se refiere a una secuencia, y si es así
     * comprueba la existencia de la secuencia. Si no la encuentra
     * la crea.
     *
     * @param PDO $link
     * @param string $tableName
     * @param string $default
     * @param string $colname
     *
     * @return bool
     */
    public function checkSequence($link, $tableName, $default, $colname)
    {
        return true;
    }

    /**
     * Comprobación adicional a la existencia de una tabla
     *
     * @param PDO $link
     * @param string $tableName
     * @param string $error
     *
     * @return bool
     */
    public function checkTableAux($link, $tableName, &$error)
    {
        $result = true;

        /// ¿La tabla no usa InnoDB?
        $data = $this->select($link, 'SHOW TABLE STATUS FROM `' . FS_DB_NAME . "` LIKE '" . $tableName . "';");
        if (!empty($data) && $data[0]['Engine'] !== 'InnoDB') {
            $result = $this->exec($link, 'ALTER TABLE ' . $tableName . ' ENGINE=InnoDB;');
            if ($result) {
                $this->error = 'Imposible convertir la tabla ' . $tableName . ' a InnoDB.'
                    . ' Imprescindible para FacturaScripts.';
                $error = $this->error;
            }
        }

        return $result;
    }

    /**
     * Sentencia SQL para obtener el último valor de una secuencia o ID
     *
     * @return string
     */
    public function sqlLastValue()
    {
        return $this->lastInsertId();
    }

    /**
     * Sentencia SQL para obtener las columnas de una tabla
     *
     * @param string $tableName
     *
     * @return string
     */
    public function sqlColumns($tableName)
    {
        return 'SHOW COLUMNS FROM `' . $tableName . '`;';
    }

    /**
     * Sentencia SQL para obtener las constraints de una tabla
     *
     * @param string $tableName
     *
     * @return string
     */
    public function sqlConstraints($tableName)
    {
        $sql = 'SELECT CONSTRAINT_NAME as name, CONSTRAINT_TYPE as type'
            . ' FROM information_schema.table_constraints '
            . ' WHERE table_schema = schema()'
            . " AND table_name = '" . $tableName . "';";
        return $sql;
    }

    /**
     * Sentencia SQL para obtener las constraints (extendidas) de una tabla
     *
     * @param string $tableName
     *
     * @return string
     */
    public function sqlConstraintsExtended($tableName)
    {
        $sql = 'SELECT t1.constraint_name as name,'
            . ' t1.constraint_type as type,'
            . ' t2.column_name,'
            . ' t2.referenced_table_name AS foreign_table_name,'
            . ' t2.referenced_column_name AS foreign_column_name,'
            . ' t3.update_rule AS on_update,'
            . ' t3.delete_rule AS on_delete'
            . ' FROM information_schema.table_constraints t1'
            . ' LEFT JOIN information_schema.key_column_usage t2'
            . ' ON t1.table_schema = t2.table_schema'
            . ' AND t1.table_name = t2.table_name'
            . ' AND t1.constraint_name = t2.constraint_name'
            . ' LEFT JOIN information_schema.referential_constraints t3'
            . ' ON t3.constraint_schema = t1.table_schema'
            . ' AND t3.constraint_name = t1.constraint_name'
            . ' WHERE t1.table_schema = SCHEMA()'
            . " AND t1.table_name = '" . $tableName . "'"
            . ' ORDER BY type DESC, name ASC;';
        return $sql;
    }

    /**
     * Sentencia SQL para obtener los indices de una tabla
     *
     * @param string $tableName
     *
     * @return string
     */
    public function sqlIndexes($tableName)
    {
        return 'SHOW INDEXES FROM ' . $tableName . ';';
    }

    /**
     * Sentencia SQL para crear una tabla
     *
     * @param string $tableName
     * @param array $columns
     * @param array $constraints
     *
     * @return string
     */
    public function sqlCreateTable($tableName, $columns, $constraints)
    {
        $fields = '';
        foreach ($columns as $col) {
            $fields .= ', `' . $col['nombre'] . '` ' . $this->getTypeAndConstraints($col);
        }

        $sql = $this->fixPostgresql(substr($fields, 2));
        return 'CREATE TABLE ' . $tableName . ' (' . $sql
            . $this->generateTableConstraints($constraints) . ') '
            . 'ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;';
    }

    /**
     * Sentencia SQL para añadir una columna a una tabla
     *
     * @param string $tableName
     * @param array $colData
     *
     * @return string
     */
    public function sqlAlterAddColumn($tableName, $colData)
    {
        $sql = 'ALTER TABLE ' . $tableName . ' ADD `' . $colData['nombre'] . '` '
            . $this->getTypeAndConstraints($colData) . ';';

        return $sql;
    }

    /**
     * Sentencia SQL para modificar la definición de una columna de una tabla
     *
     * @param string $tableName
     * @param array $colData
     *
     * @return string
     */
    public function sqlAlterModifyColumn($tableName, $colData)
    {
        $sql = 'ALTER TABLE ' . $tableName
            . ' MODIFY `' . $colData['nombre'] . '` '
            . $this->getTypeAndConstraints($colData) . ';';

        return $this->fixPostgresql($sql);
    }

    /**
     * Sentencia SQL para modificar valor por defecto de una columna de una tabla
     *
     * @param string $tableName
     * @param array $colData
     *
     * @return string
     */
    public function sqlAlterConstraintDefault($tableName, $colData)
    {
        $result = '';
        if ($colData['tipo'] !== 'serial') {
            $result = $this->sqlAlterModifyColumn($tableName, $colData);
        }
        return $result;
    }

    /**
     * Sentencia SQL para modificar un constraint null de una columna de una tabla
     *
     * @param string $tableName
     * @param array $colData
     *
     * @return string
     */
    public function sqlAlterConstraintNull($tableName, $colData)
    {
        return $this->sqlAlterModifyColumn($tableName, $colData);
    }

    /**
     * Sentencia SQL para eliminar una constraint de una tabla
     *
     * @param string $tableName
     * @param array $colData
     *
     * @return string
     */
    public function sqlDropConstraint($tableName, $colData)
    {
        $start = 'ALTER TABLE ' . $tableName . ' DROP';
        switch ($colData['type']) {
            case 'FOREIGN KEY':
                $sql = $start . ' FOREIGN KEY ' . $colData['name'] . ';';
                break;

            case 'UNIQUE':
                $sql = $start . ' INDEX ' . $colData['name'] . ';';
                break;

            default:
                $sql = '';
        }
        return $sql;
    }

    /**
     * Sentencia SQL para añadir una constraint de una tabla
     *
     * @param string $tableName
     * @param string $constraintName
     * @param string $sql
     *
     * @return string
     */
    public function sqlAddConstraint($tableName, $constraintName, $sql)
    {
        return 'ALTER TABLE ' . $tableName
            . ' ADD CONSTRAINT ' . $constraintName . ' '
            . $this->fixPostgresql($sql) . ';';
    }

    /**
     * Sentencia para crear una secuencia
     *
     * @param string $seqName
     *
     * @return string
     */
    public function sqlSequenceExists($seqName)
    {
        return '';
    }
}