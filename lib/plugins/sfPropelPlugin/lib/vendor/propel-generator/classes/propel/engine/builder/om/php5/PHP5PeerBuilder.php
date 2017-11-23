<?php

/*
 *  $Id: PHP5PeerBuilder.php 1265 2009-10-29 20:26:39Z francois $
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information please see
 * <http://propel.phpdb.org>.
 */

require_once 'propel/engine/builder/om/PeerBuilder.php';

/**
 * Generates a PHP5 base Peer class for user object model (OM).
 *
 * This class produces the base peer class (e.g. BaseMyPeer) which contains all
 * the custom-built query and manipulator methods.
 *
 * @author     Hans Lellelid <hans@xmpl.org>
 * @package    propel.engine.builder.om.php5
 */
class PHP5PeerBuilder extends PeerBuilder
{
    /**
     * Validates the current table to make sure that it won't
     * result in generated code that will not parse.
     *
     * This method may emit warnings for code which may cause problems
     * and will throw exceptions for errors that will definitely cause
     * problems.
     */
    protected function validateModel()
    {
        parent::validateModel();

        $table = $this->getTable();

        // Check to see if any of the column constants are PHP reserved words.
        $colConstants = array();

        foreach ($table->getColumns() as $col)
        {
            $colConstants[] = $this->getColumnName($col);
        }

        $reservedConstants = array_map('strtoupper', ClassTools::getPhpReservedWords());

        $intersect = array_intersect($reservedConstants, $colConstants);
        if (!empty($intersect))
        {
            throw new EngineException("One or more of your column names for [" . $table->getName() . "] table conflict with a PHP reserved word (" . implode(", ", $intersect) . ")");
        }
    }

    /**
     * Returns the name of the current class being built.
     * @return     string
     */
    public function getUnprefixedClassname()
    {
        return $this->getBuildProperty('basePrefix') . $this->getStubPeerBuilder()->getUnprefixedClassname();
    }

    /**
     * Gets the package for the [base] peer classes.
     * @return     string
     */
    public function getPackage()
    {
        return parent::getPackage() . ".om";
    }

    /**
     * Adds the include() statements for files that this class depends on or utilizes.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addIncludes(&$script)
    {
    }

    /**
     * Adds class phpdoc comment and openning of class.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addClassOpen(&$script)
    {
        $extendingPeerClass = '';
        if ($this->basePeerClassname !== 'BasePeer')
        {
            $extendingPeerClass = ' extends ' . $this->basePeerClassname;
        }

        $script .= "
abstract class " . $this->getClassname() . $extendingPeerClass . "
{";
    }

    /**
     * Closes class.
     * Adds closing brace at end of class and the static map builder registration code.
     * @param      string &$script The script will be modified in this method.
     * @see        addStaticTableMapRegistration()
     */
    protected function addClassClose(&$script)
    {
        // apply behaviors
        $this->applyBehaviorModifier('staticMethods', $script, "    ");

        $script .= "}";
        $this->addStaticTableMapRegistration($script);
    }

    /**
     * Adds the static map builder registration code.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addStaticTableMapRegistration(&$script)
    {
        $script .= "

// This is the static code needed to register the TableMap for this table with the main Propel class.
" . $this->getClassName() . "::buildTableMap();

";
        $this->applyBehaviorModifier('peerFilter', $script, "");
    }

    public function getTableMapClass()
    {
        return ($this->getTable()->isAbstract() ? '' : $this->getTable()->getPhpName()) . 'TableMap';
    }

    /**
     * Adds constant and variable declarations that go at the top of the class.
     * @param      string &$script The script will be modified in this method.
     * @see        addColumnNameConstants()
     */
    protected function addConstantsAndAttributes(&$script)
    {
        $dbName       = $this->getDatabase()->getName();
        $tableName    = $this->prefixTableName($this->getTable()->getName());
        $tablePhpName = $this->getTable()->isAbstract() ? '' : $this->getTable()->getPhpName();
        $script .= "
    /** the default database name for this class */
    const DATABASE_NAME = '$dbName';

    /** the table name for this class */
    const TABLE_NAME = '$tableName';

    /** the related Propel class for this table */
    const OM_CLASS = '$tablePhpName';

    /** A class that can be returned by this peer. */
    const CLASS_DEFAULT = '" . $this->getStubObjectBuilder()->getClasspath() . "';

    /** the related TableMap class for this table */
    const TM_CLASS = '" . $this->getTableMapClass() . "';

    /** The total number of columns. */
    const NUM_COLUMNS = " . $this->getTable()->getNumColumns() . ";

    /** The number of lazy-loaded columns. */
    const NUM_LAZY_LOAD_COLUMNS = " . $this->getTable()->getNumLazyLoadColumns() . ";
";
        $this->addColumnNameConstants($script);
        $this->addInheritanceColumnConstants($script);

        $script .= "
    /**
     * An identiy map to hold any loaded instances of " . $this->getObjectClassname() . " objects.
     * This must be public so that other peer classes can access this when hydrating from JOIN
     * queries.
     * @var        array " . $this->getObjectClassname() . "[]
     */
    public static \$instances = array();

";

        // apply behaviors
        $this->applyBehaviorModifier('staticAttributes', $script, "    ");

        $this->addFieldNamesAttribute($script);
        $this->addFieldKeysAttribute($script);
    }

    /**
     * Adds the COLUMN_NAME contants to the class definition.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addColumnNameConstants(&$script)
    {
        foreach ($this->getTable()->getColumns() as $col)
        {
            $script .= "
    /** the column name for the " . strtoupper($col->getName()) . " field */
    const " . $this->getColumnName($col) . " = '" . $this->prefixTablename($this->getTable()->getName()) . "." . strtoupper($col->getName()) . "';
";
        } // foreach
    }

    protected function addFieldNamesAttribute(&$script)
    {
        $table = $this->getTable();

        $tableColumns = $table->getColumns();

        $script .= "
    /**
     * holds an array of fieldnames
     *
     * first dimension keys are the type constants
     * e.g. self::\$fieldNames[self::TYPE_PHPNAME][0] = 'Id'
     */
    private static \$fieldNames = array (
        BasePeer::TYPE_PHPNAME => array (";
        foreach ($tableColumns as $col)
        {
            $script .= "'" . $col->getPhpName() . "', ";
        }
        $script .= "),
        BasePeer::TYPE_STUDLYPHPNAME => array (";
        foreach ($tableColumns as $col)
        {
            $script .= "'" . $col->getStudlyPhpName() . "', ";
        }
        $script .= "),
        BasePeer::TYPE_COLNAME => array (";
        foreach ($tableColumns as $col)
        {
            $script .= $this->getColumnConstant($col, 'self') . ", ";
        }
        $script .= "),
        BasePeer::TYPE_FIELDNAME => array (";
        foreach ($tableColumns as $col)
        {
            $script .= "'" . $col->getName() . "', ";
        }
        $script .= "),
        BasePeer::TYPE_NUM => array (";
        foreach ($tableColumns as $num => $col)
        {
            $script .= "$num, ";
        }
        $script .= ")
    );
";
    }

    protected function addFieldKeysAttribute(&$script)
    {
        $table = $this->getTable();

        $tableColumns = $table->getColumns();

        $script .= "
    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::\$fieldNames[BasePeer::TYPE_PHPNAME]['Id'] = 0
     */
    private static \$fieldKeys = array (
        BasePeer::TYPE_PHPNAME => array (";
        foreach ($tableColumns as $num => $col)
        {
            $script .= "'" . $col->getPhpName() . "' => $num, ";
        }
        $script .= "),
        BasePeer::TYPE_STUDLYPHPNAME => array (";
        foreach ($tableColumns as $num => $col)
        {
            $script .= "'" . $col->getStudlyPhpName() . "' => $num, ";
        }
        $script .= "),
        BasePeer::TYPE_COLNAME => array (";
        foreach ($tableColumns as $num => $col)
        {
            $script .= $this->getColumnConstant($col, 'self') . " => $num, ";
        }
        $script .= "),
        BasePeer::TYPE_FIELDNAME => array (";
        foreach ($tableColumns as $num => $col)
        {
            $script .= "'" . $col->getName() . "' => $num, ";
        }
        $script .= "),
        BasePeer::TYPE_NUM => array (";
        foreach ($tableColumns as $num => $col)
        {
            $script .= "$num, ";
        }
        $script .= ")
    );
";
    } // addFielKeysAttribute


    protected function addGetFieldNames(&$script)
    {
        $script .= "
    /**
     * Returns an array of field names.
     *
     * @param      string \$type The type of fieldnames to return:
     *                      One of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME
     *                      BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM
     * @return     array A list of field names
     */

    static public function getFieldNames(\$type = BasePeer::TYPE_PHPNAME)
    {
        if (!array_key_exists(\$type, self::\$fieldNames)) {
            throw new PropelException('Method getFieldNames() expects the parameter \$type to be one of the class constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME, BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM. ' . \$type . ' was given.');
        }
        return self::\$fieldNames[\$type];
    }
";

    } // addGetFieldNames()

    protected function addTranslateFieldName(&$script)
    {
        $script .= "
    /**
     * Translates a fieldname to another type
     *
     * @param      string \$name field name
     * @param      string \$fromType One of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME
     *                         BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM
     * @param      string \$toType   One of the class type constants
     * @return     string translated name of the field.
     * @throws     PropelException - if the specified name could not be found in the fieldname mappings.
     */
    static public function translateFieldName(\$name, \$fromType, \$toType)
    {
        \$toNames = self::getFieldNames(\$toType);
        \$key = isset(self::\$fieldKeys[\$fromType][\$name]) ? self::\$fieldKeys[\$fromType][\$name] : null;
        if (\$key === null) {
            throw new PropelException(\"'\$name' could not be found in the field names of type '\$fromType'. These are: \" . print_r(self::\$fieldKeys[\$fromType], true));
        }
        return \$toNames[\$key];
    }
";
    } // addTranslateFieldName()

    /**
     * Adds the buildTableMap() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addBuildTableMap(&$script)
    {
        $script .= "
    /**
     * Add a TableMap instance to the database for this peer class.
     */
    public static function buildTableMap()
    {
      \$dbMap = Propel::getDatabaseMap(" . $this->getClassname() . "::DATABASE_NAME);
      if (!\$dbMap->hasTable(" . $this->getClassname() . "::TABLE_NAME))
      {
        \$dbMap->addTableObject(new " . $this->getTableMapClass() . "());
      }
    }
";
    }

    /**
     * Adds the CLASSKEY_* and CLASSNAME_* constants used for inheritance.
     * @param      string &$script The script will be modified in this method.
     */
    public function addInheritanceColumnConstants(&$script)
    {
        if ($this->getTable()->getChildrenColumn())
        {

            $col = $this->getTable()->getChildrenColumn();
            $cfc = $col->getPhpName();

            if ($col->isEnumeratedClasses())
            {

                if ($col->isPhpPrimitiveNumericType())
                {
                    $quote = "";
                }
                else
                {
                    $quote = '"';
                }

                foreach ($col->getChildren() as $child)
                {
                    $childBuilder = $this->getMultiExtendObjectBuilder();
                    $childBuilder->setChild($child);

                    $script .= "
    /** A key representing a particular subclass */
    const CLASSKEY_" . strtoupper($child->getKey()) . " = '" . $child->getKey() . "';
";

                    if (strtoupper($child->getClassname()) != strtoupper($child->getKey()))
                    {
                        $script .= "
    /** A key representing a particular subclass */
    const CLASSKEY_" . strtoupper($child->getClassname()) . " = '" . $child->getKey() . "';
";
                    }

                    $script .= "
    /** A class that can be returned by this peer. */
    const CLASSNAME_" . strtoupper($child->getKey()) . " = '" . $childBuilder->getClasspath() . "';
";
                } /* foreach children */
            } /* if col->isenumerated...() */
        } /* if table->getchildrencolumn() */

    } //

    /**
     * Adds the alias() utility method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addAlias(&$script)
    {
        $script .= "
    /**
     * Convenience method which changes table.column to alias.column.
     *
     * Using this method you can maintain SQL abstraction while using column aliases.
     * <code>
     *        \$c->addAlias(\"alias1\", TablePeer::TABLE_NAME);
     *        \$c->addJoin(TablePeer::alias(\"alias1\", TablePeer::PRIMARY_KEY_COLUMN), TablePeer::PRIMARY_KEY_COLUMN);
     * </code>
     * @param      string \$alias The alias for the current table.
     * @param      string \$column The column name for current table. (i.e. " . $this->getPeerClassname() . "::COLUMN_NAME).
     * @return     string
     */
    public static function alias(\$alias, \$column)
    {
        return str_replace(" . $this->getPeerClassname() . "::TABLE_NAME.'.', \$alias.'.', \$column);
    }
";
    } // addAliasMethod

    /**
     * Adds the addSelectColumns() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addAddSelectColumns(&$script)
    {
        $script .= "
    /**
     * Add all the columns needed to create a new object.
     *
     * Note: any columns that were marked with lazyLoad=\"true\" in the
     * XML schema will not be added to the select list and only loaded
     * on demand.
     *
     * @param      criteria object containing the columns to add.
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function addSelectColumns(Criteria \$criteria)
    {";
        foreach ($this->getTable()->getColumns() as $col)
        {
            if (!$col->isLazyLoad())
            {
                $script .= "
        \$criteria->addSelectColumn(" . $this->getPeerClassname() . "::" . $this->getColumnName($col) . ");";
            } // if !col->isLazyLoad
        } // foreach
        $script .= "
    }
";
    } // addAddSelectColumns()

    /**
     * Adds the doCount() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoCount(&$script)
    {
        $script .= "
    /**
     * Returns the number of rows matching criteria.
     *
     * @param      Criteria \$criteria
     * @param      boolean \$distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO \$con
     * @return     int Number of matching rows.
     */
    public static function doCount(Criteria \$criteria, \$distinct = false, PropelPDO \$con = null)
    {
        // we may modify criteria, so copy it first
        \$criteria = clone \$criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        \$criteria->setPrimaryTableName(" . $this->getPeerClassname() . "::TABLE_NAME);

        if (\$distinct && !in_array(Criteria::DISTINCT, \$criteria->getSelectModifiers())) {
            \$criteria->setDistinct();
        }

        if (!\$criteria->hasSelectClause()) {
            " . $this->getPeerClassname() . "::addSelectColumns(\$criteria);
        }

        \$criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count
        \$criteria->setDbName(self::DATABASE_NAME); // Set the correct dbName

        if (\$con === null) {
            \$con = Propel::getConnection(" . $this->getPeerClassname() . "::DATABASE_NAME, Propel::CONNECTION_READ);
        }
";

        // apply behaviors
        $this->applyBehaviorModifier('preSelect', $script);

        $script .= "
        // BasePeer returns a PDOStatement
        \$stmt = " . $this->basePeerClassname . "::doCount(\$criteria, \$con);

        if (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
            \$count = (int) \$row[0];
        } else {
            \$count = 0; // no rows returned; we infer that means 0 matches.
        }
        \$stmt->closeCursor();
        return \$count;
    }
";
    }

    /**
     * Adds the doSelectOne() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoSelectOne(&$script)
    {
        $script .= "
    /**
     * Method to select one object from the DB.
     *
     * @param      Criteria \$criteria object used to create the SELECT statement.
     * @param      PropelPDO \$con
     * @return     " . $this->getObjectClassname() . "
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doSelectOne(Criteria \$criteria, PropelPDO \$con = null)
    {
        \$critcopy = clone \$criteria;
        \$critcopy->setLimit(1);
        \$objects = " . $this->getPeerClassname() . "::doSelect(\$critcopy, \$con);
        if (\$objects) {
            return \$objects[0];
        }
        return null;
    }
";
    }

    /**
     * Adds the exists() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addExists(&$script)
    {
        $script .= "
    /**
     * Method to do select exists(...)
     *
     * @param      Criteria \$criteria The Criteria object used to build the SELECT EXISTS(...) statement.
     * @param      PropelPDO \$con
     * @return     bool
     * @throws     PropelException Any exceptions caught during processing will be rethrown wrapped into a PropelException.
     */
    public static function exists(Criteria \$criteria, PropelPDO \$con = null)
    {
        if (\$con === null)
        {
            \$con = Propel::getConnection(" . $this->getPeerClassname() . "::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        if (!\$criteria->hasSelectClause())
        {
            \$criteria = clone \$criteria;
            " . $this->getPeerClassname() . "::addSelectColumns(\$criteria);
        }

        \$criteria->setDbName(self::DATABASE_NAME);

        \$dbMap = Propel::getDatabaseMap(\$criteria->getDbName());
        \$db = Propel::getDB(\$criteria->getDbName());

        \$stmt = null;
        try
        {
            \$params = array();
            \$sql = BasePeer::createSelectSql(\$criteria, \$params);
            \$sql = \"SELECT EXISTS(\" .  \$sql . \") AS exists_check\";
            \$stmt = \$con->prepare(\$sql);
            BasePeer::populateStmtValues(\$stmt, \$params, \$dbMap, \$db);
            \$stmt->execute();
            if (\$criteria->isUseTransaction())
            {
                \$con->commit();
            }
        }
        catch (Exception \$e)
        {
            if (\$stmt)
            {
                \$stmt = null;
            } // close
            if (\$criteria->isUseTransaction())
            {
                \$con->rollBack();
            }
            Propel::log(\$e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(\$e);
        }

        \$exists = (bool)\$stmt->fetch(PDO::FETCH_COLUMN);
        \$stmt->closeCursor();
        \$stmt = null;
        return \$exists;
    }
";
    }

    /**
     * Adds the existsByPK() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addExistsByPK(&$script)
    {
        $table = $this->getTable();
        $pks   = $table->getPrimaryKey();
        $col   = $pks[0];

        $script .= "
    /**
     * Check a single object by pkey.
     *
     * @param " . $col->getPhpType() . " \$pk the primary key.
     * @param PropelPDO \$con the connection to use
     * @return bool
     */
    public static function existsByPK(\$pk, PropelPDO \$con = null)
    {
        if (null !== (\$obj = self::getInstanceFromPool(" . $this->getInstancePoolKeySnippet('$pk') . ")))
        {
            return true;
        }

        if (\$pk === null)
        {
            return false; // avoid unnecessary query
        }

        if (\$con === null)
        {
            \$con = Propel::getConnection(self::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        \$criteria = new Criteria(self::DATABASE_NAME);
        \$criteria->add(" . $this->getColumnConstant($col) . ", \$pk);

        return self::exists(\$criteria, \$con);
    }
";
    }

    /**
     * Adds the doSelect() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoSelect(&$script)
    {
        $script .= "
    /**
     * Method to do selects.
     *
     * @param      Criteria \$criteria The Criteria object used to build the SELECT statement.
     * @param      PropelPDO \$con
     * @return     " . $this->getObjectClassname() . "[] Array of selected Objects
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doSelect(Criteria \$criteria, PropelPDO \$con = null)
    {
        return " . $this->getPeerClassname() . "::populateObjects(" . $this->getPeerClassname() . "::doSelectStmt(\$criteria, \$con));
    }
";
    }

    /**
     * Adds the doSelectStmt() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoSelectStmt(&$script)
    {

        $script .= "
    /**
     * Prepares the Criteria object and uses the parent doSelect() method to execute a PDOStatement.
     *
     * Use this method directly if you want to work with an executed statement durirectly (for example
     * to perform your own object hydration).
     *
     * @param      Criteria \$criteria The Criteria object used to build the SELECT statement.
     * @param      PropelPDO \$con The connection to use
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     * @return     PDOStatement The executed PDOStatement object.
     * @see        " . $this->basePeerClassname . "::doSelect()
     */
    public static function doSelectStmt(Criteria \$criteria, PropelPDO \$con = null)
    {
        if (\$con === null) {
            \$con = Propel::getConnection(" . $this->getPeerClassname() . "::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        if (!\$criteria->hasSelectClause()) {
            \$criteria = clone \$criteria;
            " . $this->getPeerClassname() . "::addSelectColumns(\$criteria);
        }

        // Set the correct dbName
        \$criteria->setDbName(self::DATABASE_NAME);";
        // apply behaviors
        if ($this->hasBehaviorModifier('preSelect'))
        {
            $this->applyBehaviorModifier('preSelect', $script);
        }
        $script .= "

        // BasePeer returns a PDOStatement
        return " . $this->basePeerClassname . "::doSelect(\$criteria, \$con);
    }
";
    }

    /**
     * Adds the PHP code to return a instance pool key for the passed-in primary key variable names.
     *
     * @param      array $pkphp An array of PHP var names / method calls representing complete pk.
     */
    protected function getInstancePoolKeySnippet($pkphp)
    {
        $pkphp  = (array)$pkphp; // make it an array if it is not.
        $script = "";
        if (count($pkphp) > 1)
        {
            $script .= "serialize(array(";
            $i = 0;
            foreach ($pkphp as $pkvar)
            {
                $script .= ($i++ ? ', ' : '') . "(string) $pkvar";
            }
            $script .= "))";
        }
        else
        {
            $script .= "(string) " . $pkphp[0];
        }
        return $script;
    }

    /**
     * Creates a convenience method to add objects to an instance pool.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addAddInstanceToPool(&$script)
    {
        $table = $this->getTable();
        $script .= "
    /**
     * Adds an object to the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database.  In some cases -- especially when you override doSelect*()
     * methods in your stub classes -- you may need to explicitly add objects
     * to the cache in order to ensure that the same objects are always returned by doSelect*()
     * and retrieveByPK*() calls.
     *
     * @param      " . $this->getObjectClassname() . " \$value A " . $this->getObjectClassname() . " object.
     * @param      string \$key (optional) key to use for instance map (for performance boost if key was already calculated externally).
     */
    public static function addInstanceToPool(" . $this->getObjectClassname() . " \$obj, \$key = null)
    {
        if (Propel::isInstancePoolingEnabled()) {
            if (\$key === null) {";

        $pks = $this->getTable()->getPrimaryKey();

        $php = array();
        foreach ($pks as $pk)
        {
            $php[] = '$obj->get' . $pk->getPhpName() . '()';
        }
        $script .= "
                \$key = " . $this->getInstancePoolKeySnippet($php) . ";";
        $script .= "
            } // if key === null
            self::\$instances[\$key] = \$obj;
        }
    }
";
    } // addAddInstanceToPool()

    /**
     *  Creates a convenience method to remove objects form an instance pool.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addRemoveInstanceFromPool(&$script)
    {
        $table = $this->getTable();
        $script .= "
    /**
     * Removes an object from the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database.  In some cases -- especially when you override doDelete
     * methods in your stub classes -- you may need to explicitly remove objects
     * from the cache in order to prevent returning objects that no longer exist.
     *
     * @param      mixed \$value A " . $this->getObjectClassname() . " object or a primary key value.
     */
    public static function removeInstanceFromPool(\$value)
    {";
        $script .= "
        if (Propel::isInstancePoolingEnabled() && \$value !== null) {";
        $pks = $table->getPrimaryKey();

        $script .= "
            if (is_object(\$value) && \$value instanceof " . $this->getObjectClassname() . ") {";

        $php = array();
        foreach ($pks as $pk)
        {
            $php[] = '$value->get' . $pk->getPhpName() . '()';
        }
        $script .= "
                \$key = " . $this->getInstancePoolKeySnippet($php) . ";";

        $script .= "
            } elseif (" . (count($pks) > 1 ? "is_array(\$value) && count(\$value) === " . count($pks) : "is_scalar(\$value)") . ") {
                // assume we've been passed a primary key";

        if (count($pks) > 1)
        {
            $php = array();
            for ($i = 0; $i < count($pks); $i++)
            {
                $php[] = "\$value[$i]";
            }
        }
        else
        {
            $php = '$value';
        }
        $script .= "
                \$key = " . $this->getInstancePoolKeySnippet($php) . ";";
        $script .= "
            } else {
                \$e = new PropelException(\"Invalid value passed to removeInstanceFromPool().  Expected primary key or " . $this->getObjectClassname() . " object; got \" . (is_object(\$value) ? get_class(\$value) . ' object.' : var_export(\$value,true)));
                throw \$e;
            }

            unset(self::\$instances[\$key]);
        }
    } // removeInstanceFromPool()
";
    } // addRemoveFromInstancePool()

    /**
     * Adds method to clear the instance pool.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addClearInstancePool(&$script)
    {
        $script .= "
    /**
     * Clear the instance pool.
     *
     * @return     void
     */
    public static function clearInstancePool()
    {
        self::\$instances = array();
    }
";
    }

    /**
     * Adds method to clear the instance pool of related tables.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addClearRelatedInstancePool(&$script)
    {
        $table = $this->getTable();
        $script .= "
    /**
     * Method to invalidate the instance pool of all tables related to " . $table->getName() . "
     * by a foreign key with ON DELETE CASCADE
     */
    public static function clearRelatedInstancePool()
    {";
        // Handle ON DELETE CASCADE for updating instance pool

        foreach ($table->getReferrers() as $fk)
        {

            // $fk is the foreign key in the other table, so localTableName will
            // actually be the table name of other table
            $tblFK = $fk->getTable();

            $joinedTablePeerBuilder = $this->getNewPeerBuilder($tblFK);
            $tblFKPackage           = $joinedTablePeerBuilder->getStubPeerBuilder()->getPackage();

            if (!$tblFK->isForReferenceOnly())
            {
                // we can't perform operations on tables that are
                // not within the schema (i.e. that we have no map for, etc.)

                $fkClassName = $joinedTablePeerBuilder->getObjectClassname();

                // i'm not sure whether we can allow delete cascade for foreign keys
                // within the same table?  perhaps we can?
                if (($fk->getOnDelete() == ForeignKey::CASCADE || $fk->getOnDelete() == ForeignKey::SETNULL)
                    && $tblFK->getName() != $table->getName()
                )
                {
                    $script .= "
        // invalidate objects in " . $joinedTablePeerBuilder->getPeerClassname() . " instance pool, since one or more of them may be deleted by ON DELETE CASCADE rule.
        " . $joinedTablePeerBuilder->getPeerClassname() . "::clearInstancePool();
";
                } // if fk is on delete cascade
            } // if (! for ref only)
        } // foreach
        $script .= "
    }
";
    }

    /**
     * Adds method to get an the instance from the pool, given a key.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addGetInstanceFromPool(&$script)
    {
        $script .= "
    /**
     * Retrieves a string version of the primary key from the DB resultset row that can be used to uniquely identify a row in this table.
     *
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, a serialize()d version of the primary key will be returned.
     *
     * @param      string \$key The key (@see getPrimaryKeyHash()) for this instance.
     * @return     " . $this->getObjectClassname() . " Found object or NULL if 1) no instance exists for specified key or 2) instance pooling has been disabled.
     * @see        getPrimaryKeyHash()
     */
    public static function getInstanceFromPool(\$key)
    {
        if (Propel::isInstancePoolingEnabled()) {
            if (isset(self::\$instances[\$key])) {
                return self::\$instances[\$key];
            }
        }
        return null; // just to be explicit
    }
";
    }

    /**
     * Adds method to get a version of the primary key that can be used as a unique key for identifier map.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addGetPrimaryKeyHash(&$script)
    {
        $script .= "
    /**
     * Retrieves a string version of the primary key from the DB resultset row that can be used to uniquely identify a row in this table.
     *
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, a serialize()d version of the primary key will be returned.
     *
     * @param      array \$row PropelPDO resultset row.
     * @param      int \$startcol The 0-based offset for reading from the resultset row.
     * @return     string A string version of PK or NULL if the components of primary key in result array are all null.
     */
    public static function getPrimaryKeyHashFromRow(\$row, \$startcol = 0)
    {";

        // We have to iterate through all the columns so that we know the offset of the primary
        // key columns.
        $n    = 0;
        $pk   = array();
        $cond = array();
        foreach ($this->getTable()->getColumns() as $col)
        {
            if (!$col->isLazyLoad())
            {
                if ($col->isPrimaryKey())
                {
                    $part   = $n ? "\$row[\$startcol + $n]" : "\$row[\$startcol]";
                    $cond[] = $part . " === null";
                    $pk[]   = $part;
                }
                $n++;
            }
        }

        $script .= "
        // If the PK cannot be derived from the row, return NULL.
        if (" . implode(' && ', $cond) . ") {
            return null;
        }
        return " . $this->getInstancePoolKeySnippet($pk) . ";
    }
";
    } // addGetPrimaryKeyHash

    /**
     * Adds the populateObjects() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addPopulateObjects(&$script)
    {
        $table = $this->getTable();
        $script .= "
    /**
     * The returned array will contain objects of the default type or
     * objects that inherit from the default.
     *
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function populateObjects(PDOStatement \$stmt)
    {
        \$results = array();
";
        if (!$table->getChildrenColumn())
        {
            $script .= "
        // set the class once to avoid overhead in the loop
        \$cls = " . $this->getPeerClassname() . "::getOMClass(false);";
        }

        $script .= "
        // populate the object(s)
        while (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
            \$key = " . $this->getPeerClassname() . "::getPrimaryKeyHashFromRow(\$row, 0);
            if (null !== (\$obj = " . $this->getPeerClassname() . "::getInstanceFromPool(\$key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://propel.phpdb.org/trac/ticket/509
                // \$obj->hydrate(\$row, 0, true); // rehydrate
                \$results[] = \$obj;
            } else {";
        if ($table->getChildrenColumn())
        {
            $script .= "
                // class must be set each time from the record row
                \$cls = " . $this->getPeerClassname() . "::getOMClass(\$row, 0);
                \$cls = substr('.'.\$cls, strrpos('.'.\$cls, '.') + 1);
                " . $this->buildObjectInstanceCreationCode('$obj', '$cls') . "
                \$obj->hydrate(\$row);
                \$results[] = \$obj;
                " . $this->getPeerClassname() . "::addInstanceToPool(\$obj, \$key);";
        }
        else
        {
            $script .= "
                " . $this->buildObjectInstanceCreationCode('$obj', '$cls') . "
                \$obj->hydrate(\$row);
                \$results[] = \$obj;
                " . $this->getPeerClassname() . "::addInstanceToPool(\$obj, \$key);";
        }
        $script .= "
            } // if key exists
        }
        \$stmt->closeCursor();
        return \$results;
    }
";
    }

    /**
     * Adds a getOMClass() for non-abstract tables that have inheritance.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addGetOMClass_Inheritance(&$script)
    {
        $col = $this->getTable()->getChildrenColumn();
        $script .= "
    /**
     * The returned Class will contain objects of the default type or
     * objects that inherit from the default.
     *
     * @param      array \$row PropelPDO result row.
     * @param      int \$colnum Column to examine for OM class information (first is 0).
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function getOMClass(\$row, \$colnum)
    {
        try {
";
        if ($col->isEnumeratedClasses())
        {
            $script .= "
            \$omClass = null;
            \$classKey = \$row[\$colnum + " . ($col->getPosition() - 1) . "];

            switch(\$classKey) {
";
            foreach ($col->getChildren() as $child)
            {
                $script .= "
                case self::CLASSKEY_" . strtoupper($child->getKey()) . ":
                    \$omClass = self::CLASSNAME_" . strtoupper($child->getKey()) . ";
                    break;
";
            } /* foreach */
            $script .= "
                default:
                    \$omClass = self::CLASS_DEFAULT;
";
            $script .= "
            } // switch
";
        }
        else
        { /* if not enumerated */
            $script .= "
            \$omClass = \$row[\$colnum + " . ($col->getPosition() - 1) . "];
            \$omClass = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
        }
        $script .= "
        } catch (Exception \$e) {
            throw new PropelException('Unable to get OM class.', \$e);
        }
        return \$omClass;
    }
";
    }

    /**
     * Adds a getOMClass() signature for abstract tables that have inheritance.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addGetOMClass_Inheritance_Abstract(&$script)
    {
        $script .= "
    /**
     * The returned Class will contain objects of the default type or
     * objects that inherit from the default.
     *
     * This method must be overridden by the stub subclass, because
     * " . $this->getObjectClassname() . " is declared abstract in the schema.
     *
     * @param      ResultSet \$rs ResultSet with pointer to record containing om class.
     * @param      int \$colnum Column to examine for OM class information (first is 1).
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    abstract public static function getOMClass();
";
    }

    /**
     * Adds a getOMClass() for non-abstract tables that do note use inheritance.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addGetOMClass_NoInheritance(&$script)
    {
        $script .= "
    /**
     * The class that the Peer will make instances of.
     *
     * If \$withPrefix is true, the returned path
     * uses a dot-path notation which is tranalted into a path
     * relative to a location on the PHP include_path.
     * (e.g. path.to.MyClass -> 'path/to/MyClass.php')
     *
     * @param      boolean \$withPrefix Whether or not to return the path wit hthe class name
     * @return     string path.to.ClassName
     */
    public static function getOMClass(\$withPrefix = true)
    {
        return \$withPrefix ? " . $this->getPeerClassname() . "::CLASS_DEFAULT : " . $this->getPeerClassname() . "::OM_CLASS;
    }
";
    }

    /**
     * Adds a getOMClass() signature for abstract tables that do not have inheritance.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addGetOMClass_NoInheritance_Abstract(&$script)
    {
        $script .= "
    /**
     * The class that the Peer will make instances of.
     *
     * This method must be overridden by the stub subclass, because
     * " . $this->getObjectClassname() . " is declared abstract in the schema.
     */
    abstract public static function getOMClass(\$withPrefix = true);
";
    }

    /**
     * Adds the doInsert() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoInsert(&$script)
    {
        $table = $this->getTable();
        $script .= "
    /**
     * Method perform an INSERT on the database, given a " . $this->getObjectClassname() . " or Criteria object.
     *
     * @param      mixed \$values Criteria or " . $this->getObjectClassname() . " object containing data that is used to create the INSERT statement.
     * @param      PropelPDO \$con the PropelPDO connection to use
     * @return     mixed The new primary key.
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert(\$values, PropelPDO \$con = null)
    {
        if (\$con === null) {
            \$con = Propel::getConnection(" . $this->getPeerClassname() . "::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        if (\$values instanceof Criteria) {
            \$criteria = clone \$values; // rename for clarity
        } else {
            \$criteria = \$values->buildCriteria(); // build Criteria from " . $this->getObjectClassname() . " object
        }
";

        foreach ($table->getColumns() as $col)
        {
            $cfc = $col->getPhpName();
            if ($col->isPrimaryKey() && $col->isAutoIncrement() && $table->getIdMethod() != "none" && !$table->isAllowPkInsert())
            {
                $script .= "
        if (\$criteria->containsKey(" . $this->getColumnConstant($col) . ") && \$criteria->keyContainsValue(" . $this->getColumnConstant($col) . ") ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('." . $this->getColumnConstant($col) . ".')');
        }
";
                if (!$this->getPlatform()->supportsInsertNullPk())
                {
                    $script .= "
        // remove pkey col since this table uses auto-increment and passing a null value for it is not valid
        \$criteria->remove(" . $this->getColumnConstant($col) . ");
";
                }
            }
            elseif ($col->isPrimaryKey() && $col->isAutoIncrement() && $table->getIdMethod() != "none" && $table->isAllowPkInsert() && !$this->getPlatform()->supportsInsertNullPk())
            {
                $script .= "
        // remove pkey col if it is null since this table does not accept that
        if (\$criteria->containsKey(" . $this->getColumnConstant($col) . ") && !\$criteria->keyContainsValue(" . $this->getColumnConstant($col) . ") ) {
            \$criteria->remove(" . $this->getColumnConstant($col) . ");
        }
";
            }
        }
        $script .= "
        // Set the correct dbName
        \$criteria->setDbName(self::DATABASE_NAME);

        try {
            // use transaction because \$criteria could contain info
            // for more than one table (I guess, conceivably)
            \$con->beginTransaction();
            \$pk = " . $this->basePeerClassname . "::doInsert(\$criteria, \$con);
            \$con->commit();
        } catch(PropelException \$e) {
            \$con->rollBack();
            throw \$e;
        }

        return \$pk;
    }
";
    }

    /**
     * Adds the doUpdate() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoUpdate(&$script)
    {
        $table = $this->getTable();
        $script .= "
    /**
     * Method perform an UPDATE on the database, given a " . $this->getObjectClassname() . " or Criteria object.
     *
     * @param      mixed \$values Criteria or " . $this->getObjectClassname() . " object containing data that is used to create the UPDATE statement.
     * @param      PropelPDO \$con The connection to use (specify PropelPDO connection object to exert more control over transactions).
     * @return     int The number of affected rows (if supported by underlying database driver).
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doUpdate(\$values, PropelPDO \$con = null)
    {
        if (\$con === null) {
            \$con = Propel::getConnection(" . $this->getPeerClassname() . "::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        \$selectCriteria = new Criteria(self::DATABASE_NAME);

        if (\$values instanceof Criteria) {
            \$criteria = clone \$values; // rename for clarity
";
        foreach ($table->getColumns() as $col)
        {
            if ($col->isPrimaryKey())
            {
                $script .= "
            \$comparison = \$criteria->getComparison(" . $this->getColumnConstant($col) . ");
            \$selectCriteria->add(" . $this->getColumnConstant($col) . ", \$criteria->remove(" . $this->getColumnConstant($col) . "), \$comparison);
";
            }  /* if col is prim key */
        } /* foreach */

        $script .= "
        } else { // \$values is " . $this->getObjectClassname() . " object
            \$criteria = \$values->buildCriteria(); // gets full criteria
            \$selectCriteria = \$values->buildPkeyCriteria(); // gets criteria w/ primary key(s)
        }

        // set the correct dbName
        \$criteria->setDbName(self::DATABASE_NAME);

        return {$this->basePeerClassname}::doUpdate(\$selectCriteria, \$criteria, \$con);
    }
";
    }

    /**
     * Adds the doDeleteAll() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoDeleteAll(&$script)
    {
        $table = $this->getTable();
        $script .= "
    /**
     * Method to DELETE all rows from the " . $table->getName() . " table.
     *
     * @return     int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(\$con = null)
    {
        if (\$con === null) {
            \$con = Propel::getConnection(" . $this->getPeerClassname() . "::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }
        \$affectedRows = 0; // initialize var to track total num of affected rows
        try {
            // use transaction because \$criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            \$con->beginTransaction();
        ";
        if ($this->isDeleteCascadeEmulationNeeded())
        {
            $script .= "\$affectedRows += " . $this->getPeerClassname() . "::doOnDeleteCascade(new Criteria(" . $this->getPeerClassname() . "::DATABASE_NAME), \$con);
        ";
        }
        if ($this->isDeleteSetNullEmulationNeeded())
        {
            $script .= $this->getPeerClassname() . "::doOnDeleteSetNull(new Criteria(" . $this->getPeerClassname() . "::DATABASE_NAME), \$con);
        ";
        }
        $script .= "\$affectedRows += {$this->basePeerClassname}::doDeleteAll(" . $this->getPeerClassname() . "::TABLE_NAME, \$con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            " . $this->getPeerClassname() . "::clearInstancePool();
            " . $this->getPeerClassname() . "::clearRelatedInstancePool();
            \$con->commit();
            return \$affectedRows;
        } catch (PropelException \$e) {
            \$con->rollBack();
            throw \$e;
        }
    }
";
    }

    /**
     * Adds the doDelete() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoDelete(&$script)
    {
        $table = $this->getTable();
        $script .= "
    /**
     * Method perform a DELETE on the database, given a " . $this->getObjectClassname() . " or Criteria object OR a primary key value.
     *
     * @param      mixed \$values Criteria or " . $this->getObjectClassname() . " object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param      PropelPDO \$con the connection to use
     * @return     int     The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                if supported by native driver or if emulated using Propel.
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
     public static function doDelete(\$values, PropelPDO \$con = null)
     {
        if (\$con === null) {
            \$con = Propel::getConnection(" . $this->getPeerClassname() . "::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        if (\$values instanceof Criteria) {
            // invalidate the cache for all objects of this type, since we have no
            // way of knowing (without running a query) what objects should be invalidated
            // from the cache based on this Criteria.
            " . $this->getPeerClassname() . "::clearInstancePool();

            // rename for clarity
            \$criteria = clone \$values;
        } elseif (\$values instanceof " . $this->getObjectClassname() . ") {
            // invalidate the cache for this single object
            " . $this->getPeerClassname() . "::removeInstanceFromPool(\$values);";

        if (count($table->getPrimaryKey()) > 0)
        {
            $script .= "
            // create criteria based on pk values
            \$criteria = \$values->buildPkeyCriteria();";
        }
        else
        {
            $script .= "
            // create criteria based on pk value
            \$criteria = \$values->buildCriteria();";
        }

        $script .= "
        } else {
            // it must be the primary key
            \$criteria = new Criteria(self::DATABASE_NAME);";

        if (count($table->getPrimaryKey()) === 1)
        {
            $pkey = $table->getPrimaryKey();
            $col  = array_shift($pkey);
            $script .= "
            \$criteria->add(" . $this->getColumnConstant($col) . ", (array) \$values, Criteria::IN);

            foreach ((array) \$values as \$singleval) {
                // we can invalidate the cache for this single object
                " . $this->getPeerClassname() . "::removeInstanceFromPool(\$singleval);
            }";

        }
        else
        {
            $script .= "
            // primary key is composite; we therefore, expect
            // the primary key passed to be an array of pkey
            // values
            if (count(\$values) == count(\$values, COUNT_RECURSIVE)) {
                // array is not multi-dimensional
                \$values = array(\$values);
            }

            foreach (\$values as \$value) {
";
            $i = 0;
            foreach ($table->getPrimaryKey() as $col)
            {
                if ($i == 0)
                {
                    $script .= "
                \$criterion = \$criteria->getNewCriterion(" . $this->getColumnConstant($col) . ", \$value[$i]);";
                }
                else
                {
                    $script .= "
                \$criterion->addAnd(\$criteria->getNewCriterion(" . $this->getColumnConstant($col) . ", \$value[$i]));";
                }
                $i++;
            }
            $script .= "
                \$criteria->addOr(\$criterion);

                // we can invalidate the cache for this single PK
                " . $this->getPeerClassname() . "::removeInstanceFromPool(\$value);
            }";
        } /* if count(table->getPrimaryKeys()) */

        $script .= "
        }

        // Set the correct dbName
        \$criteria->setDbName(self::DATABASE_NAME);

        \$affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because \$criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            \$con->beginTransaction();
        ";

        if ($this->isDeleteCascadeEmulationNeeded())
        {
            $script .= "\$affectedRows += " . $this->getPeerClassname() . "::doOnDeleteCascade(\$criteria, \$con);
        ";
        }
        if ($this->isDeleteSetNullEmulationNeeded())
        {
            $script .= $this->getPeerClassname() . "::doOnDeleteSetNull(\$criteria, \$con);
        ";
        }

        if ($this->isDeleteCascadeEmulationNeeded() || $this->isDeleteSetNullEmulationNeeded())
        {
            $script .= "
                // Because this db requires some delete cascade/set null emulation, we have to
                // clear the cached instance *after* the emulation has happened (since
                // instances get re-added by the select statement contained therein).
                if (\$values instanceof Criteria) {
                    " . $this->getPeerClassname() . "::clearInstancePool();
                } else { // it's a PK or object
                    " . $this->getPeerClassname() . "::removeInstanceFromPool(\$values);
                }
        ";
        }

        $script .= "
            \$affectedRows += {$this->basePeerClassname}::doDelete(\$criteria, \$con);
            " . $this->getPeerClassname() . "::clearRelatedInstancePool();
            \$con->commit();
            return \$affectedRows;
        } catch (PropelException \$e) {
            \$con->rollBack();
            throw \$e;
        }
    }
";
    }

    /**
     * Adds the doOnDeleteCascade() method, which provides ON DELETE CASCADE emulation.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoOnDeleteCascade(&$script)
    {
        $table = $this->getTable();
        $script .= "
    /**
     * This is a method for emulating ON DELETE CASCADE for DBs that don't support this
     * feature (like MySQL or SQLite).
     *
     * This method is not very speedy because it must perform a query first to get
     * the implicated records and then perform the deletes by calling those Peer classes.
     *
     * This method should be used within a transaction if possible.
     *
     * @param      Criteria \$criteria
     * @param      PropelPDO \$con
     * @return     int The number of affected rows (if supported by underlying database driver).
     */
    protected static function doOnDeleteCascade(Criteria \$criteria, PropelPDO \$con)
    {
        // initialize var to track total num of affected rows
        \$affectedRows = 0;

        // first find the objects that are implicated by the \$criteria
        \$objects = " . $this->getPeerClassname() . "::doSelect(\$criteria, \$con);
        foreach (\$objects as \$obj) {
";

        foreach ($table->getReferrers() as $fk)
        {

            // $fk is the foreign key in the other table, so localTableName will
            // actually be the table name of other table
            $tblFK = $fk->getTable();

            $joinedTablePeerBuilder = $this->getNewPeerBuilder($tblFK);
            $tblFKPackage           = $joinedTablePeerBuilder->getStubPeerBuilder()->getPackage();

            if (!$tblFK->isForReferenceOnly())
            {
                // we can't perform operations on tables that are
                // not within the schema (i.e. that we have no map for, etc.)

                $fkClassName = $joinedTablePeerBuilder->getObjectClassname();

                // i'm not sure whether we can allow delete cascade for foreign keys
                // within the same table?  perhaps we can?
                if ($fk->getOnDelete() == ForeignKey::CASCADE && $tblFK->getName() != $table->getName())
                {

                    // backwards on purpose
                    $columnNamesF = $fk->getLocalColumns();
                    $columnNamesL = $fk->getForeignColumns();

                    $script .= "

            // delete related $fkClassName objects
            \$criteria = new Criteria(" . $joinedTablePeerBuilder->getPeerClassname() . "::DATABASE_NAME);
        ";
                    for ($x = 0, $xlen = count($columnNamesF); $x < $xlen; $x++)
                    {
                        $columnFK = $tblFK->getColumn($columnNamesF[$x]);
                        $columnL  = $table->getColumn($columnNamesL[$x]);

                        $script .= "
            \$criteria->add(" . $joinedTablePeerBuilder->getColumnConstant($columnFK) . ", \$obj->get" . $columnL->getPhpName() . "());";
                    }

                    $script .= "
            \$affectedRows += " . $joinedTablePeerBuilder->getPeerClassname() . "::doDelete(\$criteria, \$con);";

                } // if cascade && fkey table name != curr table name

            } // if not for ref only
        } // foreach foreign keys
        $script .= "
        }
        return \$affectedRows;
    }
";
    } // end addDoOnDeleteCascade

    /**
     * Adds the doOnDeleteSetNull() method, which provides ON DELETE SET NULL emulation.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoOnDeleteSetNull(&$script)
    {
        $table = $this->getTable();
        $script .= "
    /**
     * This is a method for emulating ON DELETE SET NULL DBs that don't support this
     * feature (like MySQL or SQLite).
     *
     * This method is not very speedy because it must perform a query first to get
     * the implicated records and then perform the deletes by calling those Peer classes.
     *
     * This method should be used within a transaction if possible.
     *
     * @param      Criteria \$criteria
     * @param      PropelPDO \$con
     * @return     void
     */
    protected static function doOnDeleteSetNull(Criteria \$criteria, PropelPDO \$con)
    {

        // first find the objects that are implicated by the \$criteria
        \$objects = " . $this->getPeerClassname() . "::doSelect(\$criteria, \$con);
        foreach (\$objects as \$obj) {
";

        // This logic is almost exactly the same as that in doOnDeleteCascade()
        // it may make sense to refactor this, provided that thigns don't
        // get too complicated.

        foreach ($table->getReferrers() as $fk)
        {

            // $fk is the foreign key in the other table, so localTableName will
            // actually be the table name of other table
            $tblFK               = $fk->getTable();
            $refTablePeerBuilder = $this->getNewPeerBuilder($tblFK);

            if (!$tblFK->isForReferenceOnly())
            {
                // we can't perform operations on tables that are
                // not within the schema (i.e. that we have no map for, etc.)

                $fkClassName = $refTablePeerBuilder->getObjectClassname();

                // i'm not sure whether we can allow delete setnull for foreign keys
                // within the same table?  perhaps we can?
                if ($fk->getOnDelete() == ForeignKey::SETNULL &&
                    $fk->getTable()->getName() != $table->getName()
                )
                {

                    // backwards on purpose
                    $columnNamesF = $fk->getLocalColumns();
                    $columnNamesL = $fk->getForeignColumns(); // should be same num as foreign
                    $script .= "
            // set fkey col in related $fkClassName rows to NULL
            \$selectCriteria = new Criteria(" . $this->getPeerClassname() . "::DATABASE_NAME);
            \$updateValues = new Criteria(" . $this->getPeerClassname() . "::DATABASE_NAME);";

                    for ($x = 0, $xlen = count($columnNamesF); $x < $xlen; $x++)
                    {
                        $columnFK = $tblFK->getColumn($columnNamesF[$x]);
                        $columnL  = $table->getColumn($columnNamesL[$x]);
                        $script .= "
            \$selectCriteria->add(" . $refTablePeerBuilder->getColumnConstant($columnFK) . ", \$obj->get" . $columnL->getPhpName() . "());
            \$updateValues->add(" . $refTablePeerBuilder->getColumnConstant($columnFK) . ", null);
";
                    }

                    $script .= "
                    {$this->basePeerClassname}::doUpdate(\$selectCriteria, \$updateValues, \$con); // use BasePeer because generated Peer doUpdate() methods only update using pkey
";
                } // if setnull && fkey table name != curr table name
            } // if not for ref only
        } // foreach foreign keys

        $script .= "
        }
    }
";
    }

    /**
     * Adds the doValidate() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoValidate(&$script)
    {
        $table = $this->getTable();
        $script .= "
    /**
     * Validates all modified columns of given " . $this->getObjectClassname() . " object.
     * If parameter \$columns is either a single column name or an array of column names
     * than only those columns are validated.
     *
     * NOTICE: This does not apply to primary or foreign keys for now.
     *
     * @param      " . $this->getObjectClassname() . " \$obj The object to validate.
     * @param      mixed \$cols Column name or array of column names.
     *
     * @return     mixed TRUE if all columns are valid or the error message of the first invalid column.
     */
    public static function doValidate(" . $this->getObjectClassname() . " \$obj, \$cols = null)
    {
        \$columns = array();

        if (\$cols) {
            \$dbMap = Propel::getDatabaseMap(" . $this->getPeerClassname() . "::DATABASE_NAME);
            \$tableMap = \$dbMap->getTable(" . $this->getPeerClassname() . "::TABLE_NAME);

            if (! is_array(\$cols)) {
                \$cols = array(\$cols);
            }

            foreach (\$cols as \$colName) {
                if (\$tableMap->containsColumn(\$colName)) {
                    \$get = 'get' . \$tableMap->getColumn(\$colName)->getPhpName();
                    \$columns[\$colName] = \$obj->\$get();
                }
            }
        } else {
";
        foreach ($table->getValidators() as $val)
        {
            $col = $val->getColumn();
            if (!$col->isAutoIncrement())
            {
                $script .= "
        if (\$obj->isNew() || \$obj->isColumnModified(" . $this->getColumnConstant($col) . "))
            \$columns[" . $this->getColumnConstant($col) . "] = \$obj->get" . $col->getPhpName() . "();
";
            } // if
        } // foreach

        $script .= "
        }

        return {$this->basePeerClassname}::doValidate(" . $this->getPeerClassname() . "::DATABASE_NAME, " . $this->getPeerClassname() . "::TABLE_NAME, \$columns);
    }
";
    } // end addDoValidate()

    /**
     * Adds the retrieveByPK method for tables with single-column primary key.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addRetrieveByPK_SinglePK(&$script)
    {
        $table = $this->getTable();
        $pks   = $table->getPrimaryKey();
        $col   = $pks[0];

        $script .= "
    /**
     * Retrieve a single object by pkey.
     *
     * @param      " . $col->getPhpType() . " \$pk the primary key.
     * @param      PropelPDO \$con the connection to use
     * @return     " . $this->getObjectClassname() . "
     */
    public static function " . $this->getRetrieveMethodName() . "(\$pk, PropelPDO \$con = null)
    {
        if (null !== (\$obj = " . $this->getPeerClassname() . "::getInstanceFromPool(" . $this->getInstancePoolKeySnippet('$pk') . "))) {
            return \$obj;
        }

        if (\$pk === null)
        {
            return null; // avoid unnecessary query
        }

        if (\$con === null) {
            \$con = Propel::getConnection(" . $this->getPeerClassname() . "::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        \$criteria = new Criteria(" . $this->getPeerClassname() . "::DATABASE_NAME);
        \$criteria->add(" . $this->getColumnConstant($col) . ", \$pk);

        \$v = " . $this->getPeerClassname() . "::doSelect(\$criteria, \$con);

        return !empty(\$v) > 0 ? \$v[0] : null;
    }
";
    }

    /**
     * Adds the retrieveByPKs method for tables with single-column primary key.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addRetrieveByPKs_SinglePK(&$script)
    {
        $table = $this->getTable();
        $script .= "
    /**
     * Retrieve multiple objects by pkey.
     *
     * @param      array \$pks List of primary keys
     * @param      PropelPDO \$con the connection to use
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function " . $this->getRetrieveMethodName() . "s(\$pks, PropelPDO \$con = null)
    {
        if (\$con === null) {
            \$con = Propel::getConnection(" . $this->getPeerClassname() . "::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        \$objs = null;
        if (empty(\$pks)) {
            \$objs = array();
        } else {
            \$criteria = new Criteria(" . $this->getPeerClassname() . "::DATABASE_NAME);";
        $k1 = $table->getPrimaryKey();
        $script .= "
            \$criteria->add(" . $this->getColumnConstant($k1[0]) . ", \$pks, Criteria::IN);";
        $script .= "
            \$objs = " . $this->getPeerClassname() . "::doSelect(\$criteria, \$con);
        }
        return \$objs;
    }
";
    }

    /**
     * Adds the retrieveByPK method for tables with multi-column primary key.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addRetrieveByPK_MultiPK(&$script)
    {
        $table = $this->getTable();
        $script .= "
    /**
     * Retrieve object using using composite pkey values.";
        foreach ($table->getPrimaryKey() as $col)
        {
            $clo    = strtolower($col->getName());
            $cptype = $col->getPhpType();
            $script .= "
     * @param      $cptype $" . $clo;
        }
        $script .= "
     * @param      PropelPDO \$con
     * @return     " . $this->getObjectClassname() . "
     */
    public static function " . $this->getRetrieveMethodName() . "(";

        $php = array();
        foreach ($table->getPrimaryKey() as $col)
        {
            $clo   = strtolower($col->getName());
            $php[] = '$' . $clo;
        } /* foreach */

        $script .= implode(', ', $php);

        $script .= ", PropelPDO \$con = null) {
        \$key = " . $this->getInstancePoolKeySnippet($php) . ";";
        $script .= "
        if (null !== (\$obj = " . $this->getPeerClassname() . "::getInstanceFromPool(\$key))) {
            return \$obj;
        }

        if (\$con === null) {
            \$con = Propel::getConnection(" . $this->getPeerClassname() . "::DATABASE_NAME, Propel::CONNECTION_READ);
        }
        \$criteria = new Criteria(" . $this->getPeerClassname() . "::DATABASE_NAME);";
        foreach ($table->getPrimaryKey() as $col)
        {
            $clo = strtolower($col->getName());
            $script .= "
        \$criteria->add(" . $this->getColumnConstant($col) . ", $" . $clo . ");";
        }
        $script .= "
        \$v = " . $this->getPeerClassname() . "::doSelect(\$criteria, \$con);

        return !empty(\$v) ? \$v[0] : null;
    }";
    }

    /**
     * Adds the getTableMap() method which is a convenience method for apps to get DB metadata.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addGetTableMap(&$script)
    {
        $script .= "
    /**
     * Returns the TableMap related to this peer.
     * This method is not needed for general use but a specific application could have a need.
     * @return     TableMap
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function getTableMap()
    {
        return Propel::getDatabaseMap(self::DATABASE_NAME)->getTable(self::TABLE_NAME);
    }
";
    }

    /**
     * Adds the complex OM methods to the base addSelectMethods() function.
     * @param      string &$script The script will be modified in this method.
     * @see        PeerBuilder::addSelectMethods()
     */
    protected function addSelectMethods(&$script)
    {
        $table = $this->getTable();

        parent::addSelectMethods($script);

        $this->addDoCountJoin($script);
        $this->addDoSelectJoin($script);

        $countFK = count($table->getForeignKeys());

        $includeJoinAll = true;

        foreach ($this->getTable()->getForeignKeys() as $fk)
        {
            $tblFK = $table->getDatabase()->getTable($fk->getForeignTableName());
            if ($tblFK->isForReferenceOnly())
            {
                $includeJoinAll = false;
            }
        }

        if ($includeJoinAll)
        {
            if ($countFK > 0)
            {
                $this->addDoCountJoinAll($script);
                $this->addDoSelectJoinAll($script);
            }
            if ($countFK > 1)
            {
                $this->addDoCountJoinAllExcept($script);
                $this->addDoSelectJoinAllExcept($script);
            }
        }

    }

    /**
     * Get the column offsets of the primary key(s) for specified table.
     *
     * @param      Table $tbl
     * @return     array int[] The column offsets of the primary key(s).
     */
    protected function getPrimaryKeyColOffsets(Table $tbl)
    {
        $offsets = array();
        $idx     = 0;
        foreach ($tbl->getColumns() as $col)
        {
            if ($col->isPrimaryKey())
            {
                $offsets[] = $idx;
            }
            $idx++;
        }
        return $offsets;
    }

    public function addCriteriaJoin($fk, $table, $joinTable, $joinedTablePeerBuilder)
    {
        $script  = '';
        $lfMap   = $fk->getLocalForeignMapping();
        $lftCols = $fk->getLocalColumns();
        if (count($lftCols) == 1)
        {
            // simple foreign key
            $lftCol = $lftCols[0];
            $script .= sprintf("
        \$criteria->addJoin(%s, %s, \$join_behavior);\n",
                               $this->getColumnConstant($table->getColumn($lftCol)),
                               $joinedTablePeerBuilder->getColumnConstant($joinTable->getColumn($lfMap[$lftCol])));
        }
        else
        {
            // composite foreign key
            $script .= "
        \$criteria->addMultipleJoin(array(\n";
            foreach ($lftCols as $columnName)
            {
                $script .= sprintf("        array(%s, %s),\n",
                                   $this->getColumnConstant($table->getColumn($columnName)),
                                   $joinedTablePeerBuilder->getColumnConstant($joinTable->getColumn($lfMap[$columnName]))
                );
            }
            $script .= "      ), \$join_behavior);\n";
        }
        return $script;
    }

    /**
     * Adds the doSelectJoin*() methods.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoSelectJoin(&$script)
    {
        $table         = $this->getTable();
        $className     = $this->getObjectClassname();
        $countFK       = count($table->getForeignKeys());
        $join_behavior = $this->getJoinBehavior();

        if ($countFK >= 1)
        {

            foreach ($table->getForeignKeys() as $fk)
            {

                $joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());

                if (!$joinTable->isForReferenceOnly())
                {

                    // This condition is necessary because Propel lacks a system for
                    // aliasing the table if it is the same table.
                    if ($fk->getForeignTableName() != $table->getName())
                    {

                        $thisTableObjectBuilder   = $this->getNewObjectBuilder($table);
                        $joinedTableObjectBuilder = $this->getNewObjectBuilder($joinTable);
                        $joinedTablePeerBuilder   = $this->getNewPeerBuilder($joinTable);

                        $joinClassName = $joinedTableObjectBuilder->getObjectClassname();

                        $script .= "
    /**
     * Selects a collection of $className objects pre-filled with their $joinClassName objects.
     * @param      Criteria  \$criteria
     * @param      PropelPDO \$con
     * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
     * @return     array Array of $className objects.
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doSelectJoin" . $thisTableObjectBuilder->getFKPhpNameAffix($fk, $plural = false) . "(Criteria \$criteria, \$con = null, \$join_behavior = $join_behavior)
    {
        \$criteria = clone \$criteria;

        // Set the correct dbName if it has not been overridden
        if (\$criteria->getDbName() == Propel::getDefaultDB()) {
            \$criteria->setDbName(self::DATABASE_NAME);
        }

        " . $this->getPeerClassname() . "::addSelectColumns(\$criteria);
        \$startcol = (" . $this->getPeerClassname() . "::NUM_COLUMNS - " . $this->getPeerClassname() . "::NUM_LAZY_LOAD_COLUMNS);
        " . $joinedTablePeerBuilder->getPeerClassname() . "::addSelectColumns(\$criteria);
";

                        $script .= $this->addCriteriaJoin($fk, $table, $joinTable, $joinedTablePeerBuilder);

                        // apply behaviors
                        $this->applyBehaviorModifier('preSelect', $script);

                        $script .= "
        \$stmt = " . $this->basePeerClassname . "::doSelect(\$criteria, \$con);
        \$results = array();

        while (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
            \$key1 = " . $this->getPeerClassname() . "::getPrimaryKeyHashFromRow(\$row, 0);
            if (null !== (\$obj1 = " . $this->getPeerClassname() . "::getInstanceFromPool(\$key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://propel.phpdb.org/trac/ticket/509
                // \$obj1->hydrate(\$row, 0, true); // rehydrate
            } else {
";
                        if ($table->getChildrenColumn())
                        {
                            $script .= "
                \$omClass = " . $this->getPeerClassname() . "::getOMClass(\$row, 0);
                \$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
                        }
                        else
                        {
                            $script .= "
                \$cls = " . $this->getPeerClassname() . "::getOMClass(false);
";
                        }
                        $script .= "
                " . $this->buildObjectInstanceCreationCode('$obj1', '$cls') . "
                \$obj1->hydrate(\$row);
                " . $this->getPeerClassname() . "::addInstanceToPool(\$obj1, \$key1);
            } // if \$obj1 already loaded

            \$key2 = " . $joinedTablePeerBuilder->getPeerClassname() . "::getPrimaryKeyHashFromRow(\$row, \$startcol);
            if (\$key2 !== null) {
                \$obj2 = " . $joinedTablePeerBuilder->getPeerClassname() . "::getInstanceFromPool(\$key2);
                if (!\$obj2) {
";
                        if ($joinTable->getChildrenColumn())
                        {
                            $script .= "
                    \$omClass = " . $joinedTablePeerBuilder->getPeerClassname() . "::getOMClass(\$row, \$startcol);
                    \$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
                        }
                        else
                        {
                            $script .= "
                    \$cls = " . $joinedTablePeerBuilder->getPeerClassname() . "::getOMClass(false);
";
                        }

                        $script .= "
                    " . $this->buildObjectInstanceCreationCode('$obj2', '$cls') . "
                    \$obj2->hydrate(\$row, \$startcol);
                    " . $joinedTablePeerBuilder->getPeerClassname() . "::addInstanceToPool(\$obj2, \$key2);
                } // if obj2 already loaded

                // Add the \$obj1 (" . $this->getObjectClassname() . ") to \$obj2 (" . $joinedTablePeerBuilder->getObjectClassname() . ")
                \$obj2->" . ($fk->isLocalPrimaryKey() ? 'set' : 'add') . $joinedTableObjectBuilder->getRefFKPhpNameAffix($fk, $plural = false) . "(\$obj1);

            } // if joined row was not null

            \$results[] = \$obj1;
        }
        \$stmt->closeCursor();
        return \$results;
    }
";
                    } // if fk table name != this table name
                } // if ! is reference only
            } // foreach column
        } // if count(fk) > 1

    } // addDoSelectJoin()

    /**
     * Adds the doCountJoin*() methods.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoCountJoin(&$script)
    {
        $table         = $this->getTable();
        $className     = $this->getObjectClassname();
        $countFK       = count($table->getForeignKeys());
        $join_behavior = $this->getJoinBehavior();

        if ($countFK >= 1)
        {

            foreach ($table->getForeignKeys() as $fk)
            {

                $joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());

                if (!$joinTable->isForReferenceOnly())
                {

                    if ($fk->getForeignTableName() != $table->getName())
                    {

                        $thisTableObjectBuilder   = $this->getNewObjectBuilder($table);
                        $joinedTableObjectBuilder = $this->getNewObjectBuilder($joinTable);
                        $joinedTablePeerBuilder   = $this->getNewPeerBuilder($joinTable);

                        $joinClassName = $joinedTableObjectBuilder->getObjectClassname();

                        $script .= "
    /**
     * Returns the number of rows matching criteria, joining the related " . $thisTableObjectBuilder->getFKPhpNameAffix($fk, $plural = false) . " table
     *
     * @param      Criteria \$criteria
     * @param      boolean \$distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO \$con
     * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
     * @return     int Number of matching rows.
     */
    public static function doCountJoin" . $thisTableObjectBuilder->getFKPhpNameAffix($fk, $plural = false) . "(Criteria \$criteria, \$distinct = false, PropelPDO \$con = null, \$join_behavior = $join_behavior)
    {
        // we're going to modify criteria, so copy it first
        \$criteria = clone \$criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        \$criteria->setPrimaryTableName(" . $this->getPeerClassname() . "::TABLE_NAME);

        if (\$distinct && !in_array(Criteria::DISTINCT, \$criteria->getSelectModifiers())) {
            \$criteria->setDistinct();
        }

        if (!\$criteria->hasSelectClause()) {
            " . $this->getPeerClassname() . "::addSelectColumns(\$criteria);
        }

        \$criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count

        // Set the correct dbName
        \$criteria->setDbName(self::DATABASE_NAME);

        if (\$con === null) {
            \$con = Propel::getConnection(" . $this->getPeerClassname() . "::DATABASE_NAME, Propel::CONNECTION_READ);
        }
";
                        $script .= $this->addCriteriaJoin($fk, $table, $joinTable, $joinedTablePeerBuilder);

                        // apply behaviors
                        $this->applyBehaviorModifier('preSelect', $script);

                        $script .= "
        \$stmt = " . $this->basePeerClassname . "::doCount(\$criteria, \$con);

        if (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
            \$count = (int) \$row[0];
        } else {
            \$count = 0; // no rows returned; we infer that means 0 matches.
        }
        \$stmt->closeCursor();
        return \$count;
    }
";
                    } // if fk table name != this table name
                } // if ! is reference only
            } // foreach column
        } // if count(fk) > 1

    } // addDoCountJoin()

    /**
     * Adds the doSelectJoinAll() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoSelectJoinAll(&$script)
    {
        $table         = $this->getTable();
        $className     = $this->getObjectClassname();
        $join_behavior = $this->getJoinBehavior();

        $script .= "
    /**
     * Selects a collection of $className objects pre-filled with all related objects.
     *
     * @param      Criteria  \$criteria
     * @param      PropelPDO \$con
     * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
     * @return     array Array of $className objects.
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doSelectJoinAll(Criteria \$criteria, \$con = null, \$join_behavior = $join_behavior)
    {
        \$criteria = clone \$criteria;

        // Set the correct dbName if it has not been overridden
        if (\$criteria->getDbName() == Propel::getDefaultDB()) {
            \$criteria->setDbName(self::DATABASE_NAME);
        }

        " . $this->getPeerClassname() . "::addSelectColumns(\$criteria);
        \$startcol2 = (" . $this->getPeerClassname() . "::NUM_COLUMNS - " . $this->getPeerClassname() . "::NUM_LAZY_LOAD_COLUMNS);
";
        $index = 2;
        foreach ($table->getForeignKeys() as $fk)
        {

            // Want to cover this case, but the code is not there yet.
            // Propel lacks a system for aliasing tables of the same name.
            if ($fk->getForeignTableName() != $table->getName())
            {
                $joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());
                $new_index = $index + 1;

                $joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
                $joinClassName          = $joinedTablePeerBuilder->getObjectClassname();

                $script .= "
        " . $joinedTablePeerBuilder->getPeerClassname() . "::addSelectColumns(\$criteria);
        \$startcol$new_index = \$startcol$index + (" . $joinedTablePeerBuilder->getPeerClassname() . "::NUM_COLUMNS - " . $joinedTablePeerBuilder->getPeerClassname() . "::NUM_LAZY_LOAD_COLUMNS);
";
                $index = $new_index;

            } // if fk->getForeignTableName != table->getName
        } // foreach [sub] foreign keys

        foreach ($table->getForeignKeys() as $fk)
        {
            // want to cover this case, but the code is not there yet.
            if ($fk->getForeignTableName() != $table->getName())
            {
                $joinTable              = $table->getDatabase()->getTable($fk->getForeignTableName());
                $joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
                $script .= $this->addCriteriaJoin($fk, $table, $joinTable, $joinedTablePeerBuilder);
            }
        }

        // apply behaviors
        $this->applyBehaviorModifier('preSelect', $script);

        $script .= "
        \$stmt = " . $this->basePeerClassname . "::doSelect(\$criteria, \$con);
        \$results = array();

        while (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
            \$key1 = " . $this->getPeerClassname() . "::getPrimaryKeyHashFromRow(\$row, 0);
            if (null !== (\$obj1 = " . $this->getPeerClassname() . "::getInstanceFromPool(\$key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://propel.phpdb.org/trac/ticket/509
                // \$obj1->hydrate(\$row, 0, true); // rehydrate
            } else {";

        if ($table->getChildrenColumn())
        {
            $script .= "
                \$omClass = " . $this->getPeerClassname() . "::getOMClass(\$row, 0);
        \$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
        }
        else
        {
            $script .= "
                \$cls = " . $this->getPeerClassname() . "::getOMClass(false);
";
        }

        $script .= "
                " . $this->buildObjectInstanceCreationCode('$obj1', '$cls') . "
                \$obj1->hydrate(\$row);
                " . $this->getPeerClassname() . "::addInstanceToPool(\$obj1, \$key1);
            } // if obj1 already loaded
";

        $index = 1;
        foreach ($table->getForeignKeys() as $fk)
        {
            // want to cover this case, but the code is not there yet.
            // Why not? -because we'd have to alias the tables in the JOIN
            if ($fk->getForeignTableName() != $table->getName())
            {
                $joinTable = $table->getDatabase()->getTable($fk->getForeignTableName());

                $thisTableObjectBuilder   = $this->getNewObjectBuilder($table);
                $joinedTableObjectBuilder = $this->getNewObjectBuilder($joinTable);
                $joinedTablePeerBuilder   = $this->getNewPeerBuilder($joinTable);


                $joinClassName = $joinedTableObjectBuilder->getObjectClassname();
                $interfaceName = $joinClassName;

                if ($joinTable->getInterface())
                {
                    $interfaceName = $this->prefixClassname($joinTable->getInterface());
                }

                $index++;

                $script .= "
            // Add objects for joined $joinClassName rows

            \$key$index = " . $joinedTablePeerBuilder->getPeerClassname() . "::getPrimaryKeyHashFromRow(\$row, \$startcol$index);
            if (\$key$index !== null) {
                \$obj$index = " . $joinedTablePeerBuilder->getPeerClassname() . "::getInstanceFromPool(\$key$index);
                if (!\$obj$index) {
";
                if ($joinTable->getChildrenColumn())
                {
                    $script .= "
                    \$omClass = " . $joinedTablePeerBuilder->getPeerClassname() . "::getOMClass(\$row, \$startcol$index);
          \$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
                }
                else
                {
                    $script .= "
                    \$cls = " . $joinedTablePeerBuilder->getPeerClassname() . "::getOMClass(false);
";
                } /* $joinTable->getChildrenColumn() */

                $script .= "
                    " . $this->buildObjectInstanceCreationCode('$obj' . $index, '$cls') . "
                    \$obj" . $index . "->hydrate(\$row, \$startcol$index);
                    " . $joinedTablePeerBuilder->getPeerClassname() . "::addInstanceToPool(\$obj$index, \$key$index);
                } // if obj$index loaded

                // Add the \$obj1 (" . $this->getObjectClassname() . ") to the collection in \$obj" . $index . " (" . $joinedTablePeerBuilder->getObjectClassname() . ")
                " . ($fk->isLocalPrimaryKey() ?
                        "\$obj1->set" . $joinedTablePeerBuilder->getObjectClassname() . "(\$obj" . $index . ");" :
                        "\$obj" . $index . "->add" . $joinedTableObjectBuilder->getRefFKPhpNameAffix($fk, $plural = false) . "(\$obj1);") . "
            } // if joined row not null
";

            } // $fk->getForeignTableName() != $table->getName()
        } //foreach foreign key

        $script .= "
            \$results[] = \$obj1;
        }
        \$stmt->closeCursor();
        return \$results;
    }
";

    } // end addDoSelectJoinAll()


    /**
     * Adds the doCountJoinAll() method.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoCountJoinAll(&$script)
    {
        $table         = $this->getTable();
        $join_behavior = $this->getJoinBehavior();

        $script .= "
    /**
     * Returns the number of rows matching criteria, joining all related tables
     *
     * @param      Criteria \$criteria
     * @param      boolean \$distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO \$con
     * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
     * @return     int Number of matching rows.
     */
    public static function doCountJoinAll(Criteria \$criteria, \$distinct = false, PropelPDO \$con = null, \$join_behavior = $join_behavior)
    {
        // we're going to modify criteria, so copy it first
        \$criteria = clone \$criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        \$criteria->setPrimaryTableName(" . $this->getPeerClassname() . "::TABLE_NAME);

        if (\$distinct && !in_array(Criteria::DISTINCT, \$criteria->getSelectModifiers())) {
            \$criteria->setDistinct();
        }

        if (!\$criteria->hasSelectClause()) {
            " . $this->getPeerClassname() . "::addSelectColumns(\$criteria);
        }

        \$criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count

        // Set the correct dbName
        \$criteria->setDbName(self::DATABASE_NAME);

        if (\$con === null) {
            \$con = Propel::getConnection(" . $this->getPeerClassname() . "::DATABASE_NAME, Propel::CONNECTION_READ);
        }
";

        foreach ($table->getForeignKeys() as $fk)
        {
            // want to cover this case, but the code is not there yet.
            if ($fk->getForeignTableName() != $table->getName())
            {
                $joinTable              = $table->getDatabase()->getTable($fk->getForeignTableName());
                $joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
                $script .= $this->addCriteriaJoin($fk, $table, $joinTable, $joinedTablePeerBuilder);
            } // if fk->getForeignTableName != table->getName
        } // foreach [sub] foreign keys

        // apply behaviors
        $this->applyBehaviorModifier('preSelect', $script);

        $script .= "
        \$stmt = " . $this->basePeerClassname . "::doCount(\$criteria, \$con);

        if (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
            \$count = (int) \$row[0];
        } else {
            \$count = 0; // no rows returned; we infer that means 0 matches.
        }
        \$stmt->closeCursor();
        return \$count;
    }
";
    } // end addDoCountJoinAll()

    /**
     * Adds the doSelectJoinAllExcept*() methods.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoSelectJoinAllExcept(&$script)
    {
        $table         = $this->getTable();
        $join_behavior = $this->getJoinBehavior();

        // ------------------------------------------------------------------------
        // doSelectJoinAllExcept*()
        // ------------------------------------------------------------------------

        // 2) create a bunch of doSelectJoinAllExcept*() methods
        // -- these were existing in original Torque, so we should keep them for compatibility

        $fkeys = $table->getForeignKeys();  // this sep assignment is necessary otherwise sub-loops over
        // getForeignKeys() will cause this to only execute one time.
        foreach ($fkeys as $fk)
        {
            $excludedTable = $table->getDatabase()->getTable($fk->getForeignTableName());

            $thisTableObjectBuilder     = $this->getNewObjectBuilder($table);
            $excludedTableObjectBuilder = $this->getNewObjectBuilder($excludedTable);

            $excludedClassName = $excludedTableObjectBuilder->getObjectClassname();

            $script .= "
    /**
     * Selects a collection of " . $this->getObjectClassname() . " objects pre-filled with all related objects except " . $thisTableObjectBuilder->getFKPhpNameAffix($fk) . ".
     *
     * @param      Criteria  \$criteria
     * @param      PropelPDO \$con
     * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
     * @return     array Array of " . $this->getObjectClassname() . " objects.
     * @throws     PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doSelectJoinAllExcept" . $thisTableObjectBuilder->getFKPhpNameAffix($fk, $plural = false) . "(Criteria \$criteria, \$con = null, \$join_behavior = $join_behavior)
    {
        \$criteria = clone \$criteria;

        // Set the correct dbName if it has not been overridden
        // \$criteria->getDbName() will return the same object if not set to another value
        // so == check is okay and faster
        if (\$criteria->getDbName() == Propel::getDefaultDB()) {
            \$criteria->setDbName(self::DATABASE_NAME);
        }

        " . $this->getPeerClassname() . "::addSelectColumns(\$criteria);
        \$startcol2 = (" . $this->getPeerClassname() . "::NUM_COLUMNS - " . $this->getPeerClassname() . "::NUM_LAZY_LOAD_COLUMNS);
";
            $index = 2;
            foreach ($table->getForeignKeys() as $subfk)
            {
                // want to cover this case, but the code is not there yet.
                // Why not? - because we would have to alias the tables in the join
                if (!($subfk->getForeignTableName() == $table->getName()))
                {
                    $joinTable            = $table->getDatabase()->getTable($subfk->getForeignTableName());
                    $joinTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
                    $joinClassName        = $joinTablePeerBuilder->getObjectClassname();

                    if ($joinClassName != $excludedClassName)
                    {
                        $new_index = $index + 1;
                        $script .= "
        " . $joinTablePeerBuilder->getPeerClassname() . "::addSelectColumns(\$criteria);
        \$startcol$new_index = \$startcol$index + (" . $joinTablePeerBuilder->getPeerClassname() . "::NUM_COLUMNS - " . $joinTablePeerBuilder->getPeerClassname() . "::NUM_LAZY_LOAD_COLUMNS);
";
                        $index = $new_index;
                    } // if joinClassName not excludeClassName
                } // if subfk is not curr table
            } // foreach [sub] foreign keys

            foreach ($table->getForeignKeys() as $subfk)
            {
                // want to cover this case, but the code is not there yet.
                if ($subfk->getForeignTableName() != $table->getName())
                {
                    $joinTable              = $table->getDatabase()->getTable($subfk->getForeignTableName());
                    $joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
                    $joinClassName          = $joinedTablePeerBuilder->getObjectClassname();

                    if ($joinClassName != $excludedClassName)
                    {
                        $script .= $this->addCriteriaJoin($subfk, $table, $joinTable, $joinedTablePeerBuilder);
                    }
                }
            } // foreach fkeys

            // apply behaviors
            $this->applyBehaviorModifier('preSelect', $script);

            $script .= "
        \$stmt = " . $this->basePeerClassname . "::doSelect(\$criteria, \$con);
        \$results = array();

        while (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
            \$key1 = " . $this->getPeerClassname() . "::getPrimaryKeyHashFromRow(\$row, 0);
            if (null !== (\$obj1 = " . $this->getPeerClassname() . "::getInstanceFromPool(\$key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://propel.phpdb.org/trac/ticket/509
                // \$obj1->hydrate(\$row, 0, true); // rehydrate
            } else {";
            if ($table->getChildrenColumn())
            {
                $script .= "
                \$omClass = " . $this->getPeerClassname() . "::getOMClass(\$row, 0);
                \$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
            }
            else
            {
                $script .= "
                \$cls = " . $this->getPeerClassname() . "::getOMClass(false);
";
            }

            $script .= "
                " . $this->buildObjectInstanceCreationCode('$obj1', '$cls') . "
                \$obj1->hydrate(\$row);
                " . $this->getPeerClassname() . "::addInstanceToPool(\$obj1, \$key1);
            } // if obj1 already loaded
";

            $index = 1;
            foreach ($table->getForeignKeys() as $subfk)
            {
                // want to cover this case, but the code is not there yet.
                if ($subfk->getForeignTableName() != $table->getName())
                {

                    $joinTable = $table->getDatabase()->getTable($subfk->getForeignTableName());

                    $joinedTableObjectBuilder = $this->getNewObjectBuilder($joinTable);
                    $joinedTablePeerBuilder   = $this->getNewPeerBuilder($joinTable);

                    $joinClassName = $joinedTableObjectBuilder->getObjectClassname();

                    $interfaceName = $joinClassName;
                    if ($joinTable->getInterface())
                    {
                        $interfaceName = $this->prefixClassname($joinTable->getInterface());
                    }

                    if ($joinClassName != $excludedClassName)
                    {

                        $index++;

                        $script .= "
                // Add objects for joined $joinClassName rows

                \$key$index = " . $joinedTablePeerBuilder->getPeerClassname() . "::getPrimaryKeyHashFromRow(\$row, \$startcol$index);
                if (\$key$index !== null) {
                    \$obj$index = " . $joinedTablePeerBuilder->getPeerClassname() . "::getInstanceFromPool(\$key$index);
                    if (!\$obj$index) {
";

                        if ($joinTable->getChildrenColumn())
                        {
                            $script .= "
                        \$omClass = " . $joinedTablePeerBuilder->getPeerClassname() . "::getOMClass(\$row, \$startcol$index);
            \$cls = substr('.'.\$omClass, strrpos('.'.\$omClass, '.') + 1);
";
                        }
                        else
                        {
                            $script .= "
                        \$cls = " . $joinedTablePeerBuilder->getPeerClassname() . "::getOMClass(false);
";
                        } /* $joinTable->getChildrenColumn() */
                        $script .= "
                    " . $this->buildObjectInstanceCreationCode('$obj' . $index, '$cls') . "
                    \$obj" . $index . "->hydrate(\$row, \$startcol$index);
                    " . $joinedTablePeerBuilder->getPeerClassname() . "::addInstanceToPool(\$obj$index, \$key$index);
                } // if \$obj$index already loaded

                // Add the \$obj1 (" . $this->getObjectClassname() . ") to the collection in \$obj" . $index . " (" . $joinedTablePeerBuilder->getObjectClassname() . ")
                \$obj" . $index . "->" . ($subfk->isLocalPrimaryKey() ? 'set' : 'add') . $joinedTableObjectBuilder->getRefFKPhpNameAffix($subfk, $plural = false) . "(\$obj1);

            } // if joined row is not null
";
                    } // if ($joinClassName != $excludedClassName) {
                } // $subfk->getForeignTableName() != $table->getName()
            } // foreach
            $script .= "
            \$results[] = \$obj1;
        }
        \$stmt->closeCursor();
        return \$results;
    }
";
        } // foreach fk

    } // addDoSelectJoinAllExcept

    /**
     * Adds the doCountJoinAllExcept*() methods.
     * @param      string &$script The script will be modified in this method.
     */
    protected function addDoCountJoinAllExcept(&$script)
    {
        $table         = $this->getTable();
        $join_behavior = $this->getJoinBehavior();

        $fkeys = $table->getForeignKeys();  // this sep assignment is necessary otherwise sub-loops over
        // getForeignKeys() will cause this to only execute one time.
        foreach ($fkeys as $fk)
        {
            $excludedTable = $table->getDatabase()->getTable($fk->getForeignTableName());

            $thisTableObjectBuilder     = $this->getNewObjectBuilder($table);
            $excludedTableObjectBuilder = $this->getNewObjectBuilder($excludedTable);

            $excludedClassName = $excludedTableObjectBuilder->getObjectClassname();

            $script .= "
    /**
     * Returns the number of rows matching criteria, joining the related " . $thisTableObjectBuilder->getFKPhpNameAffix($fk, $plural = false) . " table
     *
     * @param      Criteria \$criteria
     * @param      boolean \$distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO \$con
     * @param      String    \$join_behavior the type of joins to use, defaults to $join_behavior
     * @return     int Number of matching rows.
     */
    public static function doCountJoinAllExcept" . $thisTableObjectBuilder->getFKPhpNameAffix($fk, $plural = false) . "(Criteria \$criteria, \$distinct = false, PropelPDO \$con = null, \$join_behavior = $join_behavior)
    {
        // we're going to modify criteria, so copy it first
        \$criteria = clone \$criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        \$criteria->setPrimaryTableName(" . $this->getPeerClassname() . "::TABLE_NAME);

        if (\$distinct && !in_array(Criteria::DISTINCT, \$criteria->getSelectModifiers())) {
            \$criteria->setDistinct();
        }

        if (!\$criteria->hasSelectClause()) {
            " . $this->getPeerClassname() . "::addSelectColumns(\$criteria);
        }

        \$criteria->clearOrderByColumns(); // ORDER BY should not affect count

        // Set the correct dbName
        \$criteria->setDbName(self::DATABASE_NAME);

        if (\$con === null) {
            \$con = Propel::getConnection(" . $this->getPeerClassname() . "::DATABASE_NAME, Propel::CONNECTION_READ);
        }
";

            foreach ($table->getForeignKeys() as $subfk)
            {
                // want to cover this case, but the code is not there yet.
                if ($subfk->getForeignTableName() != $table->getName())
                {
                    $joinTable              = $table->getDatabase()->getTable($subfk->getForeignTableName());
                    $joinedTablePeerBuilder = $this->getNewPeerBuilder($joinTable);
                    $joinClassName          = $joinedTablePeerBuilder->getObjectClassname();

                    if ($joinClassName != $excludedClassName)
                    {
                        $script .= $this->addCriteriaJoin($subfk, $table, $joinTable, $joinedTablePeerBuilder);
                    }
                }
            } // foreach fkeys

            // apply behaviors
            $this->applyBehaviorModifier('preSelect', $script);

            $script .= "
        \$stmt = " . $this->basePeerClassname . "::doCount(\$criteria, \$con);

        if (\$row = \$stmt->fetch(PDO::FETCH_NUM)) {
            \$count = (int) \$row[0];
        } else {
            \$count = 0; // no rows returned; we infer that means 0 matches.
        }
        \$stmt->closeCursor();
        return \$count;
    }
";
        } // foreach fk

    } // addDoCountJoinAllExcept

    /**
     * returns the desired join behavior as set in the build properties
     * see trac ticket #588, #491
     *
     */
    protected function getJoinBehavior()
    {
        return $this->getGeneratorConfig()->getBuildProperty('useLeftJoinsInDoJoinMethods') ? 'Criteria::LEFT_JOIN' : 'Criteria::INNER_JOIN';
    }
}